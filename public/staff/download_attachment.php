<?php
/**
 * チャット添付ファイルダウンロード（スタッフ用）
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('HTTP/1.0 403 Forbidden');
    echo '認証エラー';
    exit;
}

$pdo = getDbConnection();
$messageId = $_GET['id'] ?? null;

if (!$messageId) {
    header('HTTP/1.0 400 Bad Request');
    echo 'メッセージIDが指定されていません';
    exit;
}

try {
    // メッセージを取得（スタッフは全ファイルにアクセス可能）
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if (!$message) {
        header('HTTP/1.0 404 Not Found');
        echo 'ファイルが見つかりません';
        exit;
    }

    if (!$message['attachment_path']) {
        header('HTTP/1.0 404 Not Found');
        echo '添付ファイルがありません';
        exit;
    }

    $filePath = __DIR__ . '/../' . $message['attachment_path'];

    if (!file_exists($filePath)) {
        header('HTTP/1.0 404 Not Found');
        echo 'ファイルが見つかりません';
        exit;
    }

    // ファイルを送信
    header('Content-Type: ' . ($message['attachment_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $message['attachment_original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'データベースエラー';
}
?>
