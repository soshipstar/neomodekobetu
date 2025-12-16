<?php
/**
 * チャットリアルタイム更新API（保護者用）
 * 新着メッセージと未読カウントを返す
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];

try {
    $roomId = $_GET['room_id'] ?? null;
    $lastMessageId = (int)($_GET['last_message_id'] ?? 0);
    $checkUnreadOnly = isset($_GET['check_unread']);

    // 特定のルームの新着メッセージを取得
    if ($roomId) {
        // このルームが保護者のものか確認
        $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE id = ? AND guardian_id = ?");
        $stmt->execute([$roomId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'アクセス権限がありません']);
            exit;
        }

        // 最後のメッセージID以降の新着メッセージを取得
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
        $stmt->execute([$roomId, $lastMessageId]);
        $newMessages = $stmt->fetchAll();

        // スタッフからの未読メッセージ数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM chat_messages
            WHERE room_id = ? AND sender_type = 'staff' AND is_read = 0
        ");
        $stmt->execute([$roomId]);
        $unreadInfo = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'new_messages' => $newMessages,
            'unread_count' => $unreadInfo['unread_count']
        ]);
    }
    // 全ルームの未読カウントのみ取得
    elseif ($checkUnreadOnly) {
        $stmt = $pdo->prepare("
            SELECT
                cr.id as room_id,
                s.student_name,
                COUNT(cm.id) as unread_count,
                MAX(cm.created_at) as last_unread_at
            FROM chat_rooms cr
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN chat_messages cm ON cr.id = cm.room_id
            WHERE cr.guardian_id = ?
            AND cm.sender_type = 'staff'
            AND cm.is_read = 0
            GROUP BY cr.id, s.student_name
            HAVING unread_count > 0
            ORDER BY last_unread_at DESC
        ");
        $stmt->execute([$userId]);
        $unreadRooms = $stmt->fetchAll();

        // 全体の未読数
        $totalUnread = array_sum(array_column($unreadRooms, 'unread_count'));

        echo json_encode([
            'success' => true,
            'total_unread' => $totalUnread,
            'unread_rooms' => $unreadRooms
        ]);
    }
    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無効なリクエスト']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー']);
}
?>
