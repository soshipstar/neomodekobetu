<?php
/**
 * スタッフ用 - 生徒チャットメッセージ削除API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

$messageId = $_POST['message_id'] ?? null;

if (!$messageId) {
    echo json_encode(['success' => false, 'error' => 'メッセージIDが必要です']);
    exit;
}

try {
    // メッセージの送信者を確認（自分が送信したメッセージのみ削除可能）
    $stmt = $pdo->prepare("
        SELECT sender_id, sender_type
        FROM student_chat_messages
        WHERE id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'メッセージが見つかりません']);
        exit;
    }

    // 自分が送信したメッセージかチェック
    if ($message['sender_id'] != $currentUser['id'] || $message['sender_type'] != 'staff') {
        echo json_encode(['success' => false, 'error' => '削除権限がありません']);
        exit;
    }

    // メッセージを削除（is_deletedフラグを立てる）
    $stmt = $pdo->prepare("
        UPDATE student_chat_messages
        SET is_deleted = 1
        WHERE id = ?
    ");
    $stmt->execute([$messageId]);

    echo json_encode(['success' => true, 'message' => 'メッセージを削除しました']);

} catch (Exception $e) {
    error_log("Delete student chat message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '削除中にエラーが発生しました']);
}
