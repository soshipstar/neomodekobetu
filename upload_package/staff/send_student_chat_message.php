<?php
/**
 * スタッフ用 - 生徒チャットメッセージ送信API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

$studentId = $_POST['student_id'] ?? null;
$message = trim($_POST['message'] ?? '');

if (!$studentId || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'メッセージを入力してください']);
    exit;
}

try {
    // 生徒へのアクセス権限確認
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

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
        exit;
    }

    // チャットルームを取得または作成
    $stmt = $pdo->prepare("SELECT id FROM student_chat_rooms WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $room = $stmt->fetch();

    if ($room) {
        $roomId = $room['id'];
    } else {
        // チャットルームを新規作成
        $stmt = $pdo->prepare("INSERT INTO student_chat_rooms (student_id) VALUES (?)");
        $stmt->execute([$studentId]);
        $roomId = $pdo->lastInsertId();
    }

    $attachmentPath = null;
    $attachmentOriginalName = null;
    $attachmentSize = null;

    // ファイルアップロード処理
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/student_chat_attachments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $maxSize = 3 * 1024 * 1024;
        if ($_FILES['attachment']['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'ファイルサイズは3MB以下にしてください']);
            exit;
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $fileExtension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(['success' => false, 'error' => '許可されていないファイル形式です']);
            exit;
        }

        $attachmentOriginalName = $_FILES['attachment']['name'];
        $attachmentSize = $_FILES['attachment']['size'];
        $uniqueFileName = uniqid('student_chat_', true) . '.' . $fileExtension;
        $attachmentPath = 'uploads/student_chat_attachments/' . $uniqueFileName;
        $fullPath = __DIR__ . '/../' . $attachmentPath;

        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
            echo json_encode(['success' => false, 'error' => 'ファイルのアップロードに失敗しました']);
            exit;
        }
    }

    // メッセージを保存
    $stmt = $pdo->prepare("
        INSERT INTO student_chat_messages
        (room_id, sender_type, sender_id, message_type, message, attachment_path, attachment_original_name, attachment_size)
        VALUES (?, 'staff', ?, 'normal', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $roomId,
        $currentUser['id'],
        $message,
        $attachmentPath,
        $attachmentOriginalName,
        $attachmentSize
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'メッセージを送信しました',
        'message_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log("Staff student chat message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '送信中にエラーが発生しました']);
}
