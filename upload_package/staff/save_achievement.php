<?php
/**
 * 週間計画表の達成度評価保存
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = '不正なリクエストです';
    header('Location: renrakucho_activities.php');
    exit;
}

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$userType = $_SESSION['user_type'] ?? 'staff';

$weeklyPlanId = $_POST['weekly_plan_id'] ?? null;
$studentId = $_POST['student_id'] ?? null;
$returnDate = $_POST['return_date'] ?? null;

if (!$weeklyPlanId || !$studentId) {
    $_SESSION['error_message'] = '必要なパラメータが不足しています';
    header('Location: renrakucho_activities.php');
    exit;
}

try {
    // 週間計画が存在し、アクセス権限があるか確認
    $stmt = $pdo->prepare("
        SELECT wp.id, wp.student_id
        FROM weekly_plans wp
        INNER JOIN students s ON wp.student_id = s.id
        WHERE wp.id = ? AND s.id = ?
    ");
    $stmt->execute([$weeklyPlanId, $studentId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $_SESSION['error_message'] = 'アクセス権限がありません';
        header('Location: renrakucho_activities.php');
        exit;
    }

    // 教室ベースのアクセス制御（管理者・スタッフ共通）
    if ($userType !== 'master_admin') {
        $classroomId = $_SESSION['classroom_id'] ?? null;
        if ($classroomId) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM students s
                INNER JOIN users g ON s.guardian_id = g.id
                WHERE s.id = ? AND g.classroom_id = ?
            ");
            $stmt->execute([$studentId, $classroomId]);
            $access = $stmt->fetch();
            if ($access['count'] == 0) {
                $_SESSION['error_message'] = 'アクセス権限がありません';
                header('Location: renrakucho_activities.php');
                exit;
            }
        }
    }

    // 達成度データを収集
    $weeklyGoalAchievement = isset($_POST['weekly_goal_achievement']) ? (int)$_POST['weekly_goal_achievement'] : 0;
    $weeklyGoalComment = $_POST['weekly_goal_comment'] ?? null;

    $sharedGoalAchievement = isset($_POST['shared_goal_achievement']) ? (int)$_POST['shared_goal_achievement'] : 0;
    $sharedGoalComment = $_POST['shared_goal_comment'] ?? null;

    $mustDoAchievement = isset($_POST['must_do_achievement']) ? (int)$_POST['must_do_achievement'] : 0;
    $mustDoComment = $_POST['must_do_comment'] ?? null;

    $shouldDoAchievement = isset($_POST['should_do_achievement']) ? (int)$_POST['should_do_achievement'] : 0;
    $shouldDoComment = $_POST['should_do_comment'] ?? null;

    $wantToDoAchievement = isset($_POST['want_to_do_achievement']) ? (int)$_POST['want_to_do_achievement'] : 0;
    $wantToDoComment = $_POST['want_to_do_comment'] ?? null;

    // 各曜日の達成度データをJSON形式で収集
    $dailyAchievement = [];
    $dayKeys = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];
    $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    // フォームから送られてくる daily_achievement 配列を処理
    $postedAchievement = $_POST['daily_achievement'] ?? [];

    foreach ($dayKeys as $index => $dayKey) {
        $dayName = $dayNames[$index];
        $achievement = isset($postedAchievement[$dayKey]) ? (int)$postedAchievement[$dayKey] : 0;
        $comment = $_POST["daily_comment_{$dayKey}"] ?? null;

        $dailyAchievement[$dayName] = [
            'achievement' => $achievement,
            'comment' => $comment
        ];
    }
    $dailyAchievementJson = json_encode($dailyAchievement, JSON_UNESCAPED_UNICODE);

    $overallComment = $_POST['overall_comment'] ?? null;

    // 達成度評価を保存
    $stmt = $pdo->prepare("
        UPDATE weekly_plans
        SET weekly_goal_achievement = ?,
            weekly_goal_comment = ?,
            shared_goal_achievement = ?,
            shared_goal_comment = ?,
            must_do_achievement = ?,
            must_do_comment = ?,
            should_do_achievement = ?,
            should_do_comment = ?,
            want_to_do_achievement = ?,
            want_to_do_comment = ?,
            daily_achievement = ?,
            overall_comment = ?,
            evaluated_at = NOW(),
            evaluated_by_type = ?,
            evaluated_by_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $weeklyGoalAchievement,
        $weeklyGoalComment,
        $sharedGoalAchievement,
        $sharedGoalComment,
        $mustDoAchievement,
        $mustDoComment,
        $shouldDoAchievement,
        $shouldDoComment,
        $wantToDoAchievement,
        $wantToDoComment,
        $dailyAchievementJson,
        $overallComment,
        $userType === 'admin' ? 'admin' : 'staff',
        $currentUser['id'],
        $weeklyPlanId
    ]);

    // リダイレクト先を決定
    if ($returnDate) {
        header("Location: student_weekly_plan_detail.php?student_id={$studentId}&date={$returnDate}&success=2");
    } else {
        header("Location: student_weekly_plan_detail.php?student_id={$studentId}&success=2");
    }
    exit;

} catch (Exception $e) {
    error_log("Save achievement error: " . $e->getMessage());
    $_SESSION['error_message'] = '達成度評価の保存中にエラーが発生しました';

    if ($returnDate) {
        header("Location: student_weekly_plan_detail.php?student_id={$studentId}&date={$returnDate}");
    } else {
        header("Location: renrakucho_activities.php");
    }
    exit;
}
