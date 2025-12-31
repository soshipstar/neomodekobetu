<?php
/**
 * 保護者への一斉送信API（ファイル添付対応）
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    echo json_encode(['success' => false, 'error' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];
$classroomId = $_SESSION['classroom_id'] ?? null;

// POSTデータを取得（FormData形式）
$message = $_POST['message'] ?? '';
$guardianIdsJson = $_POST['guardian_ids'] ?? '[]';
$guardianIds = json_decode($guardianIdsJson, true);

// バリデーション
$hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

if (empty($message) && !$hasFile) {
    echo json_encode(['success' => false, 'error' => 'メッセージまたはファイルが必要です']);
    exit;
}

if (empty($guardianIds) || !is_array($guardianIds)) {
    echo json_encode(['success' => false, 'error' => '送信先が選択されていません']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ファイルアップロード処理（1回だけ）
    $attachmentPath = null;
    $attachmentOriginalName = null;
    $attachmentSize = null;

    if ($hasFile) {
        $file = $_FILES['attachment'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($file['size'] > $maxSize) {
            throw new Exception('ファイルサイズは10MB以下にしてください');
        }

        $uploadDir = __DIR__ . '/../uploads/chat_attachments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('broadcast_') . '_' . time() . '.' . $extension;
        $attachmentPath = 'uploads/chat_attachments/' . $filename;
        $fullPath = __DIR__ . '/../' . $attachmentPath;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('ファイルのアップロードに失敗しました');
        }

        $attachmentOriginalName = $file['name'];
        $attachmentSize = $file['size'];
    }

    $successCount = 0;
    $failedCount = 0;

    foreach ($guardianIds as $guardianId) {
        // 保護者に紐づく生徒を取得（自分の教室のみ）
        if ($classroomId) {
            $stmt = $pdo->prepare("
                SELECT id FROM students
                WHERE guardian_id = ? AND is_active = 1 AND classroom_id = ?
                LIMIT 1
            ");
            $stmt->execute([$guardianId, $classroomId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id FROM students
                WHERE guardian_id = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$guardianId]);
        }
        $student = $stmt->fetch();

        if (!$student) {
            $failedCount++;
            continue;
        }

        $studentId = $student['id'];

        // チャットルームを取得または作成
        $stmt = $pdo->prepare("
            SELECT id FROM chat_rooms
            WHERE student_id = ? AND guardian_id = ?
        ");
        $stmt->execute([$studentId, $guardianId]);
        $room = $stmt->fetch();

        if ($room) {
            $roomId = $room['id'];
        } else {
            // チャットルームを作成
            $stmt = $pdo->prepare("
                INSERT INTO chat_rooms (student_id, guardian_id, last_message_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$studentId, $guardianId]);
            $roomId = $pdo->lastInsertId();
        }

        // メッセージを送信（同じファイルパスを全員に共有）
        if ($attachmentPath) {
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_id, sender_type, message, attachment_path, attachment_original_name, attachment_size, is_read, created_at)
                VALUES (?, ?, 'staff', ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$roomId, $staffId, $message, $attachmentPath, $attachmentOriginalName, $attachmentSize]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_id, sender_type, message, is_read, created_at)
                VALUES (?, ?, 'staff', ?, 0, NOW())
            ");
            $stmt->execute([$roomId, $staffId, $message]);
        }

        // チャットルームの最終メッセージ日時を更新
        $stmt = $pdo->prepare("
            UPDATE chat_rooms
            SET last_message_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$roomId]);

        $successCount++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'sent_count' => $successCount,
        'failed_count' => $failedCount
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => 'メッセージの送信に失敗しました: ' . $e->getMessage()
    ]);
}
