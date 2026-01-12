<?php
/**
 * チャット添付ファイルダウンロード（保護者用）
 * きづり（通常版）専用
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('HTTP/1.0 403 Forbidden');
    echo '認証エラー';
    exit;
}

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$messageId = $_GET['id'] ?? null;
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室のservice_typeを確認（minimum版ユーザーはアクセス不可）
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT service_type FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();

    if ($classroom && $classroom['service_type'] === 'minimum') {
        header('HTTP/1.0 403 Forbidden');
        echo 'この機能は「きづり」専用です。「かけはし」版からアクセスしてください。';
        exit;
    }
}

if (!$messageId) {
    header('HTTP/1.0 400 Bad Request');
    echo 'メッセージIDが指定されていません';
    exit;
}

try {
    // メッセージを取得し、保護者のアクセス権限を確認
    $stmt = $pdo->prepare("
        SELECT cm.*, cr.guardian_id
        FROM chat_messages cm
        INNER JOIN chat_rooms cr ON cm.room_id = cr.id
        WHERE cm.id = ? AND cr.guardian_id = ?
    ");
    $stmt->execute([$messageId, $userId]);
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
    $filename = $message['attachment_original_name'];
    $encodedFilename = rawurlencode($filename);

    header('Content-Type: ' . ($message['attachment_type'] ?: 'application/octet-stream'));
    header("Content-Disposition: attachment; filename=\"{$encodedFilename}\"; filename*=UTF-8''{$encodedFilename}");
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'データベースエラー';
}
?>
