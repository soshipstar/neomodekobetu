<?php
/**
 * チャット添付ファイルダウンロード（スタッフ用）
 * かけはし（minimum版）専用
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    header('HTTP/1.0 403 Forbidden');
    echo '認証エラー';
    exit;
}

$pdo = getDbConnection();
$messageId = $_GET['id'] ?? null;
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$messageId) {
    header('HTTP/1.0 400 Bad Request');
    echo 'メッセージIDが指定されていません';
    exit;
}

try {
    // 教室のservice_typeを確認（minimum版のみアクセス可能）
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT service_type FROM classrooms WHERE id = ?");
        $stmt->execute([$classroomId]);
        $classroom = $stmt->fetch();

        if (!$classroom || $classroom['service_type'] !== 'minimum') {
            header('HTTP/1.0 403 Forbidden');
            echo 'この機能は「かけはし」専用です';
            exit;
        }
    }

    // メッセージを取得（同じ教室のユーザーのみアクセス可能）
    $stmt = $pdo->prepare("
        SELECT cm.*, cr.guardian_id, cr.student_id
        FROM chat_messages cm
        INNER JOIN chat_rooms cr ON cm.room_id = cr.id
        INNER JOIN users u ON cr.guardian_id = u.id
        WHERE cm.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$messageId, $classroomId]);
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

    $filePath = __DIR__ . '/../../' . $message['attachment_path'];

    if (!file_exists($filePath)) {
        header('HTTP/1.0 404 Not Found');
        echo 'ファイルが見つかりません';
        exit;
    }

    // 画像の場合はインラインで表示、それ以外はダウンロード
    $mimeType = $message['attachment_type'] ?: 'application/octet-stream';
    $isImage = strpos($mimeType, 'image/') === 0;

    header('Content-Type: ' . $mimeType);
    if ($isImage) {
        header('Content-Disposition: inline; filename="' . $message['attachment_original_name'] . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $message['attachment_original_name'] . '"');
    }
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'データベースエラー';
}
?>
