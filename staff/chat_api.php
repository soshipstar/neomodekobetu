<?php
/**
 * チャットAPI（スタッフ用）
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// GETリクエスト: メッセージ取得
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $roomId = $_GET['room_id'] ?? null;
    $lastId = $_GET['last_id'] ?? 0;

    if ($action === 'get_messages' && $roomId) {
        try {
            // スタッフは全てのルームにアクセス可能

            // メッセージを取得
            $stmt = $pdo->prepare("
                SELECT
                    cm.id,
                    cm.message,
                    cm.sender_type,
                    cm.message_type,
                    cm.created_at,
                    cm.attachment_path,
                    cm.attachment_original_name,
                    cm.attachment_size,
                    u.full_name as sender_name
                FROM chat_messages cm
                LEFT JOIN users u ON cm.sender_id = u.id
                WHERE cm.room_id = ? AND cm.id > ?
                ORDER BY cm.created_at ASC, cm.id ASC
                LIMIT 50
            ");
            $stmt->execute([$roomId, $lastId]);
            $messages = $stmt->fetchAll();

            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'データベースエラー']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無効なリクエスト']);
    }
}

// POSTリクエスト: メッセージ送信
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $roomId = $_POST['room_id'] ?? null;
        $message = trim($_POST['message'] ?? '');
        $hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

        if (!$roomId || (!$message && !$hasFile)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ルームIDまたはメッセージが指定されていません']);
            exit;
        }

        try {
            // スタッフは全てのルームにアクセス可能

            // ファイルアップロード処理
            $attachmentPath = null;
            $attachmentOriginalName = null;
            $attachmentSize = null;
            $attachmentType = null;

            if ($hasFile) {
                $file = $_FILES['attachment'];
                $maxFileSize = 3 * 1024 * 1024; // 3MB

                if ($file['size'] > $maxFileSize) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ファイルサイズは3MB以下にしてください']);
                    exit;
                }

                $uploadDir = __DIR__ . '/../uploads/chat_attachments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('chat_', true) . '.' . $extension;
                $uploadPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $attachmentPath = 'uploads/chat_attachments/' . $filename;
                    $attachmentOriginalName = $file['name'];
                    $attachmentSize = $file['size'];
                    $attachmentType = $file['type'];
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'ファイルのアップロードに失敗しました']);
                    exit;
                }
            }

            // メッセージを保存
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (
                    room_id, sender_id, sender_type, message,
                    attachment_path, attachment_original_name, attachment_size, attachment_type
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $roomId, $userId, $userType, $message,
                $attachmentPath, $attachmentOriginalName, $attachmentSize, $attachmentType
            ]);

            // ルームの最終メッセージ時刻を更新
            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$roomId]);

            echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無効なアクション']);
    }
}

else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '許可されていないメソッド']);
}
?>
