<?php
/**
 * スタッフ用 - 生徒チャット添付ファイルダウンロード
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$messageId = $_GET['id'] ?? null;

if (!$messageId) {
    http_response_code(400);
    echo 'メッセージIDが指定されていません';
    exit;
}

// メッセージ情報を取得（アクセス権限チェック含む）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT scm.attachment_path, scm.attachment_original_name, scm.attachment_size
        FROM student_chat_messages scm
        INNER JOIN student_chat_rooms scr ON scm.room_id = scr.id
        INNER JOIN students s ON scr.student_id = s.id
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE scm.id = ? AND g.classroom_id = ?
    ");
    $stmt->execute([$messageId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT scm.attachment_path, scm.attachment_original_name, scm.attachment_size
        FROM student_chat_messages scm
        WHERE scm.id = ?
    ");
    $stmt->execute([$messageId]);
}

$message = $stmt->fetch();

if (!$message || !$message['attachment_path']) {
    http_response_code(404);
    echo 'ファイルが見つかりません';
    exit;
}

$filePath = __DIR__ . '/../' . $message['attachment_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'ファイルが存在しません';
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $message['attachment_original_name'] . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
