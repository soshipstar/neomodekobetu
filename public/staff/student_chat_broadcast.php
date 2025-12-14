<?php
/**
 * スタッフ用 - 生徒チャット一斉送信API
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;
$userType = $_SESSION['user_type'] ?? 'staff';

// パラメータ取得
$studentIdsStr = $_POST['student_ids'] ?? '';
$message = trim($_POST['message'] ?? '');

if (empty($studentIdsStr) || empty($message)) {
    echo json_encode(['success' => false, 'error' => '必要なパラメータが不足しています']);
    exit;
}

$studentIds = array_map('intval', explode(',', $studentIdsStr));

if (empty($studentIds)) {
    echo json_encode(['success' => false, 'error' => '送信先の生徒が選択されていません']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ファイル処理
    $attachmentPath = null;
    $attachmentOriginalName = null;
    $attachmentSize = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $maxSize = 3 * 1024 * 1024; // 3MB

        if ($file['size'] > $maxSize) {
            throw new Exception('ファイルサイズは3MB以下にしてください');
        }

        $uploadDir = __DIR__ . '/../uploads/student_chat_attachments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $attachmentPath = 'uploads/student_chat_attachments/' . $filename;
        $fullPath = __DIR__ . '/../' . $attachmentPath;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('ファイルのアップロードに失敗しました');
        }

        $attachmentOriginalName = $file['name'];
        $attachmentSize = $file['size'];
    }

    $sentCount = 0;

    foreach ($studentIds as $studentId) {
        // 生徒が存在し、アクセス権限があるか確認
        if ($classroomId) {
            $stmt = $pdo->prepare("
                SELECT s.id
                FROM students s
                INNER JOIN users g ON s.guardian_id = g.id
                WHERE s.id = ? AND g.classroom_id = ?
            ");
            $stmt->execute([$studentId, $classroomId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
            $stmt->execute([$studentId]);
        }

        $student = $stmt->fetch();
        if (!$student) {
            continue; // アクセス権限がない生徒はスキップ
        }

        // チャットルームを取得または作成
        $stmt = $pdo->prepare("SELECT id FROM student_chat_rooms WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $room = $stmt->fetch();

        if (!$room) {
            // ルームを作成
            $stmt = $pdo->prepare("INSERT INTO student_chat_rooms (student_id, created_at) VALUES (?, NOW())");
            $stmt->execute([$studentId]);
            $roomId = $pdo->lastInsertId();
        } else {
            $roomId = $room['id'];
        }

        // メッセージを送信
        if ($attachmentPath) {
            $stmt = $pdo->prepare("
                INSERT INTO student_chat_messages
                (room_id, sender_type, sender_id, message, attachment_path, attachment_original_name, attachment_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $roomId,
                $userType,
                $currentUser['id'],
                $message,
                $attachmentPath,
                $attachmentOriginalName,
                $attachmentSize
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO student_chat_messages
                (room_id, sender_type, sender_id, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $roomId,
                $userType,
                $currentUser['id'],
                $message
            ]);
        }

        $sentCount++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'sent_count' => $sentCount,
        'message' => "{$sentCount}名の生徒にメッセージを送信しました"
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Broadcast error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
