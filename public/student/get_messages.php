<?php
/**
 * 生徒用チャット - 新着メッセージ取得API
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// パラメータ取得
$lastMessageId = $_GET['last_message_id'] ?? 0;

// チャットルーム取得
$stmt = $pdo->prepare("SELECT id FROM student_chat_rooms WHERE student_id = ?");
$stmt->execute([$studentId]);
$room = $stmt->fetch();

if (!$room) {
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

$roomId = $room['id'];

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
