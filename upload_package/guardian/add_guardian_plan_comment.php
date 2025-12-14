<?php
/**
 * 保護者用 - 週間計画表コメント投稿
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: weekly_plan.php');
    exit;
}

$weeklyPlanId = $_POST['weekly_plan_id'] ?? null;
$studentId = $_POST['student_id'] ?? null;
$weekStartDate = $_POST['week_start_date'] ?? null;
$comment = trim($_POST['comment'] ?? '');

if (!$weeklyPlanId || !$studentId || !$weekStartDate || empty($comment)) {
    header('Location: weekly_plan.php?error=' . urlencode('コメントを入力してください'));
    exit;
}

try {
    // 週間計画へのアクセス権限確認（自分の子どもか確認）
    $stmt = $pdo->prepare("
        SELECT wp.id
        FROM weekly_plans wp
        INNER JOIN students s ON wp.student_id = s.id
        WHERE wp.id = ? AND s.guardian_id = ?
    ");
    $stmt->execute([$weeklyPlanId, $currentUser['id']]);

    if (!$stmt->fetch()) {
        header('Location: weekly_plan.php?error=' . urlencode('アクセス権限がありません'));
        exit;
    }

    // コメントを保存
    $stmt = $pdo->prepare("
        INSERT INTO weekly_plan_comments (weekly_plan_id, commenter_type, commenter_id, comment)
        VALUES (?, 'guardian', ?, ?)
    ");
    $stmt->execute([$weeklyPlanId, $currentUser['id'], $comment]);

    header('Location: weekly_plan.php?student_id=' . $studentId . '&date=' . urlencode($weekStartDate) . '&success=1');
    exit;

} catch (Exception $e) {
    error_log("Guardian plan comment error: " . $e->getMessage());
    header('Location: weekly_plan.php?student_id=' . $studentId . '&date=' . urlencode($weekStartDate) . '&error=' . urlencode('コメント投稿中にエラーが発生しました'));
    exit;
}
