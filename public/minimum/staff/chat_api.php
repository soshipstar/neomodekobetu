<?php
/**
 * チャットAPI（スタッフ用）
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../../includes/email_helper.php';

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

            // メッセージを取得（削除されていないもののみ）
            $stmt = $pdo->prepare("
                SELECT
                    cm.id,
                    cm.message,
                    cm.sender_type,
                    cm.sender_id,
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

                $uploadDir = __DIR__ . '/../../uploads/chat_attachments/';
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

            $messageId = $pdo->lastInsertId();

            // メール通知を送信（スタッフが送信した場合は保護者に通知）
            if ($userType === 'staff') {
                try {
                    // ルーム情報と保護者情報を取得
                    $stmt = $pdo->prepare("
                        SELECT
                            cr.student_id,
                            cr.guardian_id,
                            s.student_name,
                            u.full_name as guardian_name,
                            u.email as guardian_email,
                            staff.full_name as staff_name
                        FROM chat_rooms cr
                        INNER JOIN students s ON cr.student_id = s.id
                        INNER JOIN users u ON cr.guardian_id = u.id
                        INNER JOIN users staff ON staff.id = ?
                        WHERE cr.id = ?
                    ");
                    $stmt->execute([$userId, $roomId]);
                    $roomInfo = $stmt->fetch();

                    if ($roomInfo && !empty($roomInfo['guardian_email'])) {
                        // チャットURLを生成
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $chatUrl = "{$protocol}://{$host}/minimum/guardian/chat.php?room_id={$roomId}";

                        // メール通知を送信
                        sendChatNotificationEmail(
                            $roomInfo['guardian_email'],
                            $roomInfo['guardian_name'],
                            $roomInfo['staff_name'],
                            $roomInfo['student_name'],
                            $message ?: '（添付ファイル）',
                            $chatUrl
                        );
                    }
                } catch (Exception $e) {
                    // メール送信エラーはログに記録するが、APIレスポンスには影響させない
                    error_log("Chat email notification failed: " . $e->getMessage());
                }
            }

            echo json_encode(['success' => true, 'message_id' => $messageId]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
        }
    }

    // 提出期限設定
    elseif ($action === 'create_submission') {
        $roomId = $_POST['room_id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dueDate = $_POST['due_date'] ?? null;
        $hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

        if (!$roomId || !$title || !$dueDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '必須項目が入力されていません']);
            exit;
        }

        try {
            // ルーム情報を取得して生徒IDと保護者IDを取得
            $stmt = $pdo->prepare("
                SELECT student_id, guardian_id
                FROM chat_rooms
                WHERE id = ?
            ");
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();

            if (!$room) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'チャットルームが見つかりません']);
                exit;
            }

            // ファイルアップロード処理
            $attachmentPath = null;
            $attachmentOriginalName = null;
            $attachmentSize = null;

            if ($hasFile) {
                $file = $_FILES['attachment'];
                $maxFileSize = 3 * 1024 * 1024; // 3MB

                if ($file['size'] > $maxFileSize) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ファイルサイズは3MB以下にしてください']);
                    exit;
                }

                $uploadDir = __DIR__ . '/../../uploads/submission_attachments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $attachmentPath = 'uploads/submission_attachments/' . $fileName;
                    $attachmentOriginalName = $file['name'];
                    $attachmentSize = $file['size'];
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'ファイルのアップロードに失敗しました']);
                    exit;
                }
            }

            // 提出期限リクエストを作成
            $stmt = $pdo->prepare("
                INSERT INTO submission_requests
                (room_id, student_id, guardian_id, created_by, title, description, due_date,
                 attachment_path, attachment_original_name, attachment_size)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $roomId,
                $room['student_id'],
                $room['guardian_id'],
                $userId,
                $title,
                $description,
                $dueDate,
                $attachmentPath,
                $attachmentOriginalName,
                $attachmentSize
            ]);

            $submissionId = $pdo->lastInsertId();

            // チャットメッセージとして通知も送信
            $notificationMessage = "<span class=\"material-symbols-outlined\" style=\"font-size: 18px; vertical-align: middle;\">assignment</span> 【提出期限のお知らせ】\n\n件名: {$title}\n";
            if ($description) {
                $notificationMessage .= "詳細: {$description}\n";
            }
            $notificationMessage .= "提出期限: " . date('Y年n月j日', strtotime($dueDate));

            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_id, sender_type, message, message_type)
                VALUES (?, ?, ?, ?, 'normal')
            ");
            $stmt->execute([$roomId, $userId, $userType, $notificationMessage]);

            // ルームの最終メッセージ時刻を更新
            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$roomId]);

            echo json_encode([
                'success' => true,
                'submission_id' => $submissionId,
                'message' => '提出期限を設定しました'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'データベースエラー: ' . $e->getMessage()]);
        }
    }

    // メッセージ削除
    elseif ($action === 'delete_message') {
        $messageId = $_POST['message_id'] ?? null;

        if (!$messageId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'メッセージIDが指定されていません']);
            exit;
        }

        try {
            // メッセージの送信者を確認（自分が送信したメッセージのみ削除可能）
            $stmt = $pdo->prepare("
                SELECT sender_id, sender_type
                FROM chat_messages
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch();

            if (!$message) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'メッセージが見つかりません']);
                exit;
            }

            // 自分が送信したメッセージかチェック
            if ($message['sender_id'] != $userId || $message['sender_type'] != $userType) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => '削除権限がありません']);
                exit;
            }

            // メッセージを削除（is_deletedフラグを立てる）
            $stmt = $pdo->prepare("
                UPDATE chat_messages
                SET is_deleted = 1
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);

            echo json_encode(['success' => true, 'message' => 'メッセージを削除しました']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'データベースエラー: ' . $e->getMessage()]);
        }
    }

    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無効なアクション']);
    }
}

else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '許可されていないメソッド']);
}
?>
