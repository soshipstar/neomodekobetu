<?php
/**
 * 週間計画表へのコメント投稿
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: weekly_plan.php');
    exit;
}

$weeklyPlanId = $_POST['weekly_plan_id'] ?? null;
$comment = trim($_POST['comment'] ?? '');

if (!$weeklyPlanId || empty($comment)) {
    header('Location: weekly_plan.php?error=' . urlencode('コメントを入力してください'));
    exit;
}

try {
    // 週間計画が自分のものか確認
    $stmt = $pdo->prepare("
        SELECT week_start_date FROM weekly_plans
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$weeklyPlanId, $studentId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        header('Location: weekly_plan.php?error=' . urlencode('アクセス権限がありません'));
        exit;
    }

    // コメントを保存
    $stmt = $pdo->prepare("
        INSERT INTO weekly_plan_comments (weekly_plan_id, commenter_type, commenter_id, comment)
        VALUES (?, 'student', ?, ?)
    ");
    $stmt->execute([$weeklyPlanId, $studentId, $comment]);

    header('Location: weekly_plan.php?date=' . urlencode($plan['week_start_date']) . '&success=1');
    exit;

} catch (Exception $e) {
    error_log("Weekly plan comment error: " . $e->getMessage());
    header('Location: weekly_plan.php?error=' . urlencode('コメント投稿中にエラーが発生しました'));
    exit;
}
