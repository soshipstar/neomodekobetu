<?php
/**
 * スタッフ用 - 週間計画表コメント投稿
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: student_weekly_plans.php');
    exit;
}

$weeklyPlanId = $_POST['weekly_plan_id'] ?? null;
$studentId = $_POST['student_id'] ?? null;
$weekStartDate = $_POST['week_start_date'] ?? null;
$comment = trim($_POST['comment'] ?? '');

if (!$weeklyPlanId || !$studentId || !$weekStartDate || empty($comment)) {
    header('Location: student_weekly_plans.php?error=' . urlencode('コメントを入力してください'));
    exit;
}

try {
    // 週間計画へのアクセス権限確認
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT wp.id
            FROM weekly_plans wp
            INNER JOIN students s ON wp.student_id = s.id
            INNER JOIN users g ON s.guardian_id = g.id
            WHERE wp.id = ? AND g.classroom_id = ?
        ");
        $stmt->execute([$weeklyPlanId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM weekly_plans WHERE id = ?");
        $stmt->execute([$weeklyPlanId]);
    }

    if (!$stmt->fetch()) {
        header('Location: student_weekly_plans.php?error=' . urlencode('アクセス権限がありません'));
        exit;
    }

    // コメントを保存
    $stmt = $pdo->prepare("
        INSERT INTO weekly_plan_comments (weekly_plan_id, commenter_type, commenter_id, comment)
        VALUES (?, 'staff', ?, ?)
    ");
    $stmt->execute([$weeklyPlanId, $currentUser['id'], $comment]);

    header('Location: student_weekly_plan_detail.php?student_id=' . $studentId . '&date=' . urlencode($weekStartDate) . '&success=3');
    exit;

} catch (Exception $e) {
    error_log("Staff plan comment error: " . $e->getMessage());
    header('Location: student_weekly_plan_detail.php?student_id=' . $studentId . '&date=' . urlencode($weekStartDate) . '&error=' . urlencode('コメント投稿中にエラーが発生しました'));
    exit;
}
