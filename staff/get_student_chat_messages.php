<?php
/**
 * スタッフ用 - 生徒チャットの新着メッセージ取得API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

requireLogin();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// パラメータ取得
$roomId = $_GET['room_id'] ?? 0;
$lastMessageId = $_GET['last_message_id'] ?? 0;

if (!$roomId) {
    echo json_encode(['success' => false, 'error' => 'ルームIDが必要です']);
    exit;
}

// チャットルームへのアクセス権限確認（classroom_id による制限）
$stmt = $pdo->prepare("
    SELECT scr.id
    FROM student_chat_rooms scr
    INNER JOIN students s ON scr.student_id = s.id
    INNER JOIN users u ON s.guardian_id = u.id
    WHERE scr.id = ? AND u.classroom_id = ?
");
$stmt->execute([$roomId, $_SESSION['classroom_id'] ?? null]);
$room = $stmt->fetch();

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
    exit;
}

// 最新メッセージを取得（last_message_id より新しいもの、削除されていないもののみ）
$stmt = $pdo->prepare("
    SELECT
        scm.id,
        scm.sender_type,
        scm.sender_id,
        scm.message_type,
        scm.message,
        scm.attachment_path,
        scm.attachment_original_name,
        scm.attachment_size,
        scm.created_at,
        CASE
            WHEN scm.sender_type = 'student' THEN s.student_name
            WHEN scm.sender_type = 'staff' THEN u.full_name
        END as sender_name
    FROM student_chat_messages scm
    LEFT JOIN students s ON scm.sender_type = 'student' AND scm.sender_id = s.id
    LEFT JOIN users u ON scm.sender_type = 'staff' AND scm.sender_id = u.id
    WHERE scm.room_id = ? AND scm.id > ? AND (scm.is_deleted = 0 OR scm.is_deleted IS NULL)
    ORDER BY scm.created_at ASC
");
$stmt->execute([$roomId, $lastMessageId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'messages' => $messages
]);
