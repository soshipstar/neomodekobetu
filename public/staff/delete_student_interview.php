<?php
/**
 * スタッフ用 - 生徒面談記録の削除処理
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$sessionClassroomId = $_SESSION['classroom_id'] ?? null;

$interviewId = $_GET['interview_id'] ?? null;
$studentId = $_GET['student_id'] ?? null;

if (!$interviewId || !$studentId) {
    header('Location: student_interviews.php?error=' . urlencode('必須項目が不足しています'));
    exit;
}

// アクセス権限チェック（面談記録の存在確認と生徒のclassroom_idチェック）
if ($sessionClassroomId) {
    $stmt = $pdo->prepare("
        SELECT si.id
        FROM student_interviews si
        INNER JOIN students s ON si.student_id = s.id
        WHERE si.id = ? AND si.student_id = ? AND s.classroom_id = ?
    ");
    $stmt->execute([$interviewId, $studentId, $sessionClassroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT id
        FROM student_interviews
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$interviewId, $studentId]);
}

if (!$stmt->fetch()) {
    header("Location: student_interview_detail.php?student_id=$studentId&error=" . urlencode('アクセス権限がありません'));
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM student_interviews WHERE id = ?");
    $stmt->execute([$interviewId]);

    header("Location: student_interview_detail.php?student_id=$studentId&success=2");
    exit;
} catch (Exception $e) {
    header("Location: student_interview_detail.php?student_id=$studentId&interview_id=$interviewId&error=" . urlencode($e->getMessage()));
    exit;
}
