<?php
/**
 * スタッフ用 - 生徒週間計画表詳細
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_GET['student_id'] ?? null;
$targetDate = $_GET['date'] ?? date('Y-m-d');

if (!$studentId) {
    header('Location: student_weekly_plans.php');
    exit;
}

// 生徒情報を取得（アクセス権限チェック含む）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE s.id = ? AND g.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
}

$student = $stmt->fetch();

if (!$student) {
    header('Location: student_weekly_plans.php');
    exit;
}

// 週の開始日を計算
$timestamp = strtotime($targetDate);
$dayOfWeek = date('w', $timestamp);
$daysFromMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
$weekStartDate = date('Y-m-d', strtotime("-$daysFromMonday days", $timestamp));

// 今週の開始日を計算（一覧に戻るリンク用の週オフセット計算）
$today = date('Y-m-d');
$todayDayOfWeek = date('w', strtotime($today));
$todayDaysFromMonday = ($todayDayOfWeek == 0) ? 6 : $todayDayOfWeek - 1;
$currentWeekStart = date('Y-m-d', strtotime("-$todayDaysFromMonday days", strtotime($today)));
$weekOffset = (int)((strtotime($weekStartDate) - strtotime($currentWeekStart)) / (7 * 24 * 60 * 60));

// 週間計画を取得
$stmt = $pdo->prepare("
    SELECT
        id,
        weekly_goal,
        shared_goal,
        must_do,
        should_do,
        want_to_do,
        plan_data,
        weekly_goal_achievement,
        weekly_goal_comment,
        shared_goal_achievement,
        shared_goal_comment,
        must_do_achievement,
        must_do_comment,
        should_do_achievement,
        should_do_comment,
        want_to_do_achievement,
        want_to_do_comment,
        daily_achievement,
        overall_comment,
        evaluated_at,
        evaluated_by_type,
        evaluated_by_id,
        created_at,
        updated_at
    FROM weekly_plans
    WHERE student_id = ? AND week_start_date = ?
");
$stmt->execute([$studentId, $weekStartDate]);
$weeklyPlan = $stmt->fetch();

$planData = $weeklyPlan ? json_decode($weeklyPlan['plan_data'], true) : [];
$dailyAchievement = ($weeklyPlan && $weeklyPlan['daily_achievement']) ? json_decode($weeklyPlan['daily_achievement'], true) : [];

// 前週の計画を取得（達成度入力用）
$prevWeekDate = date('Y-m-d', strtotime('-7 days', strtotime($weekStartDate)));
$stmt = $pdo->prepare("
    SELECT
        id,
        week_start_date,
        weekly_goal,
        shared_goal,
        must_do,
        should_do,
        want_to_do,
        plan_data,
        weekly_goal_achievement,
        weekly_goal_comment,
        shared_goal_achievement,
        shared_goal_comment,
        must_do_achievement,
        must_do_comment,
        should_do_achievement,
        should_do_comment,
        want_to_do_achievement,
        want_to_do_comment,
        daily_achievement,
        overall_comment,
        evaluated_at
    FROM weekly_plans
    WHERE student_id = ? AND week_start_date = ?
");
$stmt->execute([$studentId, $prevWeekDate]);
$prevWeekPlan = $stmt->fetch();

$prevPlanData = $prevWeekPlan ? json_decode($prevWeekPlan['plan_data'], true) : [];
$prevDailyAchievement = ($prevWeekPlan && $prevWeekPlan['daily_achievement']) ? json_decode($prevWeekPlan['daily_achievement'], true) : [];

// 提出物を取得
$submissions = [];
if ($weeklyPlan) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            submission_item,
            due_date,
            is_completed,
            completed_at,
            completed_by_type,
            completed_by_id
        FROM weekly_plan_submissions
        WHERE weekly_plan_id = ?
        ORDER BY due_date ASC, id ASC
    ");
    $stmt->execute([$weeklyPlan['id']]);
    $submissions = $stmt->fetchAll();
}

// 個別支援計画の5領域目標を取得（編集モード用）
$domainGoals = [];
$stmt = $pdo->prepare("
    SELECT isp.id as plan_id, isp.created_date,
           ispd.sub_category, ispd.support_goal
    FROM individual_support_plans isp
    INNER JOIN individual_support_plan_details ispd ON isp.id = ispd.plan_id
    WHERE isp.student_id = ? AND isp.is_draft = 0 AND ispd.category = '本人支援'
    ORDER BY isp.created_date DESC, ispd.row_order ASC
");
$stmt->execute([$studentId]);
$allDomainGoals = $stmt->fetchAll();

// 最新の計画のみ取得
if (!empty($allDomainGoals)) {
    $latestPlanId = $allDomainGoals[0]['plan_id'];
    foreach ($allDomainGoals as $goal) {
        if ($goal['plan_id'] == $latestPlanId && !empty($goal['support_goal'])) {
            $domainGoals[] = [
                'sub_category' => $goal['sub_category'],
                'support_goal' => $goal['support_goal']
            ];
        }
    }
}

// コメントを取得
$comments = [];
if ($weeklyPlan) {
    $stmt = $pdo->prepare("
        SELECT
            wpc.id,
            wpc.commenter_type,
            wpc.comment,
            wpc.created_at,
            CASE
                WHEN wpc.commenter_type = 'staff' THEN u.full_name
                WHEN wpc.commenter_type = 'guardian' THEN u2.full_name
                WHEN wpc.commenter_type = 'student' THEN s.student_name
            END as commenter_name
        FROM weekly_plan_comments wpc
        LEFT JOIN users u ON wpc.commenter_type = 'staff' AND wpc.commenter_id = u.id
        LEFT JOIN users u2 ON wpc.commenter_type = 'guardian' AND wpc.commenter_id = u2.id
        LEFT JOIN students s ON wpc.commenter_type = 'student' AND wpc.commenter_id = s.id
        WHERE wpc.weekly_plan_id = ?
        ORDER BY wpc.created_at ASC
    ");
    $stmt->execute([$weeklyPlan['id']]);
    $comments = $stmt->fetchAll();
}

// 前週・次週の日付
$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($weekStartDate)));
$nextWeek = date('Y-m-d', strtotime('+7 days', strtotime($weekStartDate)));

// ページ開始
$currentPage = 'student_weekly_plan_detail';
$pageTitle = htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') . 'の週間計画表';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .week-nav {
            background: var(--apple-bg-primary);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .week-nav h2 {
            font-size: 18px;
            color: var(--text-primary);
        }

        .week-nav-buttons {
            display: flex;
            gap: 10px;
        }

        .week-nav-buttons a {
            padding: var(--spacing-sm) 16px;
            background: var(--primary-purple);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
        }

        .week-nav-buttons a:hover {
            background: var(--primary-purple);
        }

        .plan-container {
            background: var(--apple-bg-primary);
            border-radius: var(--radius-md);
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: var(--spacing-lg);
        }

        .plan-section {
            margin-bottom: 25px;
        }

        .plan-section h3 {
            color: var(--primary-purple);
            font-size: var(--text-callout);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .plan-section textarea {
            width: 100%;
            min-height: 60px;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
        }

        .plan-section .view-content {
            padding: var(--spacing-md);
            background: var(--apple-gray-6);
            border-left: 4px solid var(--primary-purple);
            border-radius: 4px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .plan-section .view-content.empty {
            color: var(--text-secondary);
            font-style: italic;
        }

        .daily-plans {
            margin-top: var(--spacing-lg);
        }

        .daily-plans h3 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 15px;
        }

        .day-plan {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 15px;
            margin-bottom: 15px;
            align-items: start;
        }

        .day-label {
            font-weight: 600;
            color: var(--primary-purple);
            padding-top: 12px;
        }

        .day-date {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
        }

        .submissions-section {
            margin-top: var(--spacing-2xl);
            padding-top: 30px;
            border-top: 2px solid var(--apple-gray-5);
        }

        .submissions-section h3 {
            color: var(--apple-red);
            font-size: 18px;
            margin-bottom: 15px;
        }

        .submission-item {
            display: grid;
            grid-template-columns: 1fr 150px 100px 40px;
            gap: 10px;
            margin-bottom: var(--spacing-md);
            align-items: center;
        }

        .submission-item input[type="text"],
        .submission-item input[type="date"] {
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: 4px;
            font-size: var(--text-subhead);
        }

        .submission-item .completed-check {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .submission-item .completed-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .submission-item .remove-btn {
            background: var(--apple-red);
            color: white;
            border: none;
            border-radius: 4px;
            padding: var(--spacing-sm);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }

        .submission-item .remove-btn:hover {
            background: var(--apple-red);
        }

        .add-submission-btn {
            padding: var(--spacing-md) 20px;
            background: var(--apple-green);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            margin-top: 10px;
        }

        .add-submission-btn:hover {
            background: var(--apple-green);
        }

        .submission-view-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            background: var(--apple-gray-6);
            border-left: 4px solid var(--apple-red);
            border-radius: 4px;
            margin-bottom: var(--spacing-md);
        }

        .submission-view-item.completed {
            opacity: 0.6;
            border-left-color: var(--apple-green);
            text-decoration: line-through;
        }

        .submission-info {
            flex: 1;
        }

        .submission-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .submission-date {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
        }

        .submission-date.urgent {
            color: var(--apple-red);
            font-weight: 600;
        }

        .submission-date.overdue {
            color: #721c24;
            font-weight: 700;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: var(--spacing-lg);
        }

        .btn {
            padding: var(--spacing-md) 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--apple-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--apple-green);
        }

        .btn-secondary {
            background: var(--apple-gray);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--apple-gray);
        }

        .btn-edit {
            background: var(--primary-purple);
            color: white;
        }

        .btn-edit:hover {
            background: var(--primary-purple);
        }

        .comments-section {
            background: var(--apple-bg-primary);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .comments-section h3 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 18px;
        }

        .comment {
            padding: 15px;
            background: var(--apple-gray-6);
            border-left: 4px solid var(--primary-purple);
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
        }

        .comment.staff {
            border-left-color: var(--apple-green);
        }

        .comment.student {
            border-left-color: var(--primary-purple);
        }

        .comment.guardian {
            border-left-color: var(--apple-orange);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
        }

        .comment-author {
            font-weight: 600;
            color: var(--primary-purple);
        }

        .comment-date {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
        }

        .comment-body {
            color: var(--text-primary);
            line-height: 1.6;
        }

        .comment-form {
            margin-top: var(--spacing-lg);
        }

        .comment-form textarea {
            width: 100%;
            min-height: 100px;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: var(--text-subhead);
            resize: vertical;
        }

        .comment-form button {
            margin-top: 10px;
            padding: var(--spacing-md) 20px;
            background: var(--apple-green);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .comment-form button:hover {
            background: var(--apple-green);
        }

        .message {
            padding: var(--spacing-md) 20px;
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            font-size: var(--text-subhead);
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--apple-green);
        }

        .message.error {
            background: var(--apple-bg-secondary);
            color: #721c24;
            border-left: 4px solid var(--apple-red);
        }

        .no-plan {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .no-plan p {
            font-size: var(--text-callout);
            margin-bottom: var(--spacing-lg);
        }

        /* 5段階評価チェックボックス */
        .eval-form-section {
            margin-top: var(--spacing-2xl);
            padding: var(--spacing-lg);
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border: 2px solid var(--primary-purple);
            border-radius: var(--radius-md);
        }

        .eval-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--primary-purple);
        }

        .eval-form-header h3 {
            color: var(--primary-purple);
            margin: 0;
            font-size: 18px;
        }

        .eval-item {
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--apple-bg-primary);
            border-radius: var(--radius-sm);
        }

        .eval-item-header {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .eval-item-content {
            font-size: var(--text-subhead);
            padding: var(--spacing-sm);
            background: var(--apple-gray-6);
            border-radius: 4px;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .eval-5-scale {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .eval-5-scale-label {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
            min-width: 70px;
        }

        .eval-5-scale-boxes {
            display: flex;
            gap: 8px;
        }

        .eval-5-scale-boxes label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }

        .eval-5-scale-boxes input[type="radio"] {
            display: none;
        }

        .eval-5-scale-boxes .eval-box {
            width: 36px;
            height: 36px;
            border: 2px solid var(--apple-gray-4);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--apple-gray-3);
            transition: all 0.2s ease;
            background: white;
        }

        .eval-5-scale-boxes input[type="radio"]:checked + .eval-box {
            border-color: var(--primary-purple);
            background: var(--primary-purple);
            color: white;
        }

        .eval-5-scale-boxes label:hover .eval-box {
            border-color: var(--primary-purple);
        }

        .eval-5-scale-legend {
            font-size: var(--text-caption-2);
            color: var(--text-tertiary);
            margin-left: auto;
        }

        .eval-comment-input {
            width: 100%;
            min-height: 50px;
            padding: var(--spacing-sm);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-family: inherit;
            resize: vertical;
        }

        .eval-submit-btn {
            display: block;
            width: 100%;
            padding: 15px 30px;
            background: var(--apple-green);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-body);
            font-weight: 600;
            cursor: pointer;
            transition: background var(--duration-fast);
            margin-top: var(--spacing-lg);
        }

        .eval-submit-btn:hover {
            opacity: 0.9;
        }

        .eval-overall-comment {
            margin-top: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--apple-bg-primary);
            border-radius: var(--radius-sm);
        }

        .eval-overall-comment h4 {
            color: var(--primary-purple);
            margin-bottom: 10px;
            font-size: var(--text-callout);
        }

        .eval-overall-comment textarea {
            width: 100%;
            min-height: 80px;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
        }

        /* 達成度評価モーダル */
        .achievement-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .achievement-modal.active {
            display: block;
        }

        .achievement-modal-content {
            background-color: var(--apple-bg-primary);
            margin: 50px auto;
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            max-width: 900px;
            box-shadow: var(--shadow-md);
        }

        .achievement-modal-header {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--apple-gray-5);
        }

        .achievement-section {
            margin-bottom: var(--spacing-2xl);
            padding: var(--spacing-lg);
            background: var(--apple-gray-6);
            border-radius: var(--radius-sm);
        }

        .achievement-section h4 {
            color: var(--primary-purple);
            font-size: var(--text-callout);
            margin-bottom: var(--spacing-md);
        }

        .goal-content {
            padding: var(--spacing-md);
            background: var(--apple-bg-primary);
            border-left: 4px solid var(--primary-purple);
            border-radius: 4px;
            margin-bottom: 15px;
            min-height: 40px;
            line-height: 1.5;
        }

        .goal-content.empty {
            color: var(--text-secondary);
            font-style: italic;
        }

        .achievement-radios {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .achievement-radios label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: var(--text-subhead);
        }

        .achievement-radios input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .achievement-comment {
            width: 100%;
            min-height: 60px;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
        }

        .achievement-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: var(--spacing-2xl);
            padding-top: 20px;
            border-top: 2px solid var(--apple-gray-5);
        }

        .achievement-btn {
            padding: var(--spacing-md) 24px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .achievement-btn-cancel {
            background: var(--apple-gray);
            color: white;
        }

        .achievement-btn-cancel:hover {
            background: var(--apple-gray);
        }

        .achievement-btn-submit {
            background: var(--apple-green);
            color: white;
        }

        .achievement-btn-submit:hover {
            background: var(--apple-green);
        }

        @media (max-width: 768px) {
            .day-plan {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .submission-item {
                grid-template-columns: 1fr;
            }

            .submission-item .remove-btn {
                width: 100%;
            }
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんの週間計画表</h1>
        <p class="page-subtitle"><?php echo date('Y年m月d日', strtotime($weekStartDate)); ?>の週</p>
    </div>
    <div class="page-header-actions">
        <a href="student_weekly_plans.php?week=<?php echo $weekOffset; ?>" class="btn btn-secondary">← 一覧に戻る</a>
    </div>
</div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                <?php if ($_GET['success'] == '1'): ?>
                    週間計画表を保存しました
                <?php elseif ($_GET['success'] == '2'): ?>
                    達成度評価を保存しました
                <?php elseif ($_GET['success'] == '3'): ?>
                    コメントを投稿しました
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="week-nav">
            <h2><?php echo date('Y年m月d日', strtotime($weekStartDate)); ?>の週</h2>
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if ($prevWeekPlan && !$prevWeekPlan['evaluated_at']): ?>
                    <button type="button" onclick="openAchievementModal()" style="padding: var(--spacing-sm) 16px; background: var(--apple-green); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-footnote); font-weight: 600;">
                        ⭐ 前週の達成度を入力
                    </button>
                <?php endif; ?>
                <div class="week-nav-buttons">
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $prevWeek; ?>">← 前週</a>
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo date('Y-m-d'); ?>">今週</a>
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $nextWeek; ?>">次週 →</a>
                </div>
            </div>
        </div>

        <?php
        // 編集モードかどうか
        $isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';
        ?>

        <?php if (!$weeklyPlan && !$isEditMode): ?>
            <div class="plan-container">
                <div class="no-plan">
                    <p>この週の計画はまだ作成されていません</p>
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>&edit=1" class="btn btn-edit">計画を作成する</a>
                </div>
            </div>
        <?php elseif ($isEditMode): ?>
            <!-- 編集モード -->
            <form method="POST" action="save_staff_weekly_plan.php">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <input type="hidden" name="week_start_date" value="<?php echo $weekStartDate; ?>">

                <div class="plan-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h2 style="color: var(--text-primary); font-size: 20px;">📝 週間計画を編集</h2>
                        <div style="display: flex; gap: 10px;">
                            <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>" class="btn btn-secondary">キャンセル</a>
                            <button type="submit" class="btn btn-primary">保存する</button>
                        </div>
                    </div>

                    <!-- 今週の目標 -->
                    <div class="plan-section">
                        <h3>🎯 今週の目標</h3>
                        <textarea name="weekly_goal" placeholder="今週達成したい目標を記入してください"><?php echo htmlspecialchars($weeklyPlan['weekly_goal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- いっしょに決めた目標 -->
                    <div class="plan-section">
                        <h3>🤝 いっしょに決めた目標</h3>
                        <textarea name="shared_goal" placeholder="生徒と一緒に決めた目標を記入してください"><?php echo htmlspecialchars($weeklyPlan['shared_goal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <?php if (!empty($domainGoals)): ?>
                    <!-- 個別支援計画の5領域目標（参考表示） -->
                    <div class="plan-section domain-goals-reference" style="background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border: 1px dashed #4caf50; padding: 15px; border-radius: var(--radius-md);">
                        <h3 style="color: #2e7d32; font-size: var(--text-subhead); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>📋</span> 個別支援計画の目標（参考）
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($domainGoals as $goal): ?>
                                <div style="background: white; padding: 10px 12px; border-radius: var(--radius-sm); border-left: 3px solid #4caf50;">
                                    <div style="font-size: var(--text-footnote); color: #666; margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($goal['sub_category'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div style="font-size: var(--text-subhead); color: var(--text-primary);">
                                        <?php echo htmlspecialchars($goal['support_goal'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin-top: 10px; font-size: var(--text-footnote); color: #666; text-align: center;">
                            ※ 保存後この表示は非表示になります
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- やるべきこと -->
                    <div class="plan-section">
                        <h3>✅ やるべきこと</h3>
                        <textarea name="must_do" placeholder="必ずやるべきことを記入してください"><?php echo htmlspecialchars($weeklyPlan['must_do'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- やったほうがいいこと -->
                    <div class="plan-section">
                        <h3>👍 やったほうがいいこと</h3>
                        <textarea name="should_do" placeholder="できればやったほうがいいことを記入してください"><?php echo htmlspecialchars($weeklyPlan['should_do'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- やりたいこと -->
                    <div class="plan-section">
                        <h3>💡 やりたいこと</h3>
                        <textarea name="want_to_do" placeholder="本人がやりたいと思っていることを記入してください"><?php echo htmlspecialchars($weeklyPlan['want_to_do'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 各曜日の計画 -->
                    <div class="daily-plans">
                        <h3>📅 各曜日の計画・目標</h3>
                        <?php
                        $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                        foreach ($days as $index => $day):
                            $dayKey = "day_$index";
                            $date = date('m/d', strtotime("+$index days", strtotime($weekStartDate)));
                            $content = $planData[$dayKey] ?? '';
                        ?>
                            <div class="day-plan">
                                <div>
                                    <div class="day-label"><?php echo $day; ?></div>
                                    <div class="day-date"><?php echo $date; ?></div>
                                </div>
                                <textarea name="<?php echo $dayKey; ?>" rows="2" placeholder="この日の計画や目標を記入してください"><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 提出物管理 -->
                    <div class="submissions-section">
                        <h3>📋 提出物管理</h3>
                        <div id="submissionsContainer">
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $index => $sub): ?>
                                    <div class="submission-item">
                                        <input type="text" name="submissions[<?php echo $index; ?>][item]" value="<?php echo htmlspecialchars($sub['submission_item'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="提出物名">
                                        <input type="date" name="submissions[<?php echo $index; ?>][due_date]" value="<?php echo $sub['due_date']; ?>">
                                        <div class="completed-check">
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                                <input type="checkbox" name="submissions[<?php echo $index; ?>][completed]" value="1" <?php echo $sub['is_completed'] ? 'checked' : ''; ?>>
                                                <span style="font-size: var(--text-footnote);">完了</span>
                                            </label>
                                        </div>
                                        <button type="button" class="remove-btn" onclick="removeSubmission(this)">×</button>
                                        <input type="hidden" name="submissions[<?php echo $index; ?>][id]" value="<?php echo $sub['id']; ?>">
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- 初期状態で1つの空の提出物欄を表示（提出物はオプション） -->
                                <div class="submission-item">
                                    <input type="text" name="submissions[0][item]" placeholder="提出物名（任意）">
                                    <input type="date" name="submissions[0][due_date]">
                                    <div class="completed-check">
                                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                            <input type="checkbox" name="submissions[0][completed]" value="1">
                                            <span style="font-size: var(--text-footnote);">完了</span>
                                        </label>
                                    </div>
                                    <button type="button" class="remove-btn" onclick="removeSubmission(this)">×</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-submission-btn" onclick="addSubmission()">+ 提出物を追加</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <!-- 表示モード -->
            <div class="plan-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 style="color: var(--text-primary); font-size: 20px;">📝 週間計画</h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="student_weekly_plan_pdf.php?student_id=<?php echo $studentId; ?>&date=<?php echo $weekStartDate; ?>" class="btn btn-secondary" target="_blank">PDF出力</a>
                        <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>&edit=1" class="btn btn-edit">編集する</a>
                    </div>
                </div>

                <!-- 今週の目標 -->
                <div class="plan-section">
                    <h3>🎯 今週の目標</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['weekly_goal']) ? 'empty' : ''; ?>"><?php echo !empty($weeklyPlan['weekly_goal']) ? nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?></div>
                </div>

                <!-- いっしょに決めた目標 -->
                <div class="plan-section">
                    <h3>🤝 いっしょに決めた目標</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['shared_goal']) ? 'empty' : ''; ?>"><?php echo !empty($weeklyPlan['shared_goal']) ? nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?></div>
                </div>

                <!-- やるべきこと -->
                <div class="plan-section">
                    <h3>✅ やるべきこと</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['must_do']) ? 'empty' : ''; ?>"><?php echo !empty($weeklyPlan['must_do']) ? nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?></div>
                </div>

                <!-- やったほうがいいこと -->
                <div class="plan-section">
                    <h3>👍 やったほうがいいこと</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['should_do']) ? 'empty' : ''; ?>"><?php echo !empty($weeklyPlan['should_do']) ? nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?></div>
                </div>

                <!-- やりたいこと -->
                <div class="plan-section">
                    <h3>💡 やりたいこと</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['want_to_do']) ? 'empty' : ''; ?>"><?php echo !empty($weeklyPlan['want_to_do']) ? nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?></div>
                </div>

                <!-- 各曜日の計画 -->
                <div class="daily-plans">
                    <h3>📅 各曜日の計画・目標</h3>
                    <?php
                    $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                    foreach ($days as $index => $day):
                        $dayKey = "day_$index";
                        $date = date('m/d', strtotime("+$index days", strtotime($weekStartDate)));
                        $content = $planData[$dayKey] ?? '';
                    ?>
                        <div class="day-plan">
                            <div>
                                <div class="day-label"><?php echo $day; ?></div>
                                <div class="day-date"><?php echo $date; ?></div>
                            </div>
                            <div class="view-content <?php echo empty($content) ? 'empty' : ''; ?>"><?php echo !empty($content) ? nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) : '予定なし'; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 提出物一覧 -->
                <?php if (!empty($submissions)): ?>
                    <div class="submissions-section">
                        <h3>📋 提出物一覧</h3>
                        <?php foreach ($submissions as $sub):
                            $dueDate = new DateTime($sub['due_date']);
                            $today = new DateTime();
                            $diff = $today->diff($dueDate);
                            $daysUntilDue = (int)$diff->format('%r%a');

                            $dateClass = '';
                            if ($daysUntilDue < 0) {
                                $dateClass = 'overdue';
                            } elseif ($daysUntilDue <= 3) {
                                $dateClass = 'urgent';
                            }
                        ?>
                            <div class="submission-view-item <?php echo $sub['is_completed'] ? 'completed' : ''; ?>">
                                <div class="submission-info">
                                    <div class="submission-title">
                                        <?php echo $sub['is_completed'] ? '✅ ' : ''; ?>
                                        <?php echo htmlspecialchars($sub['submission_item'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="submission-date <?php echo $dateClass; ?>">
                                        期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                                        <?php if (!$sub['is_completed']): ?>
                                            <?php if ($daysUntilDue < 0): ?>
                                                （<?php echo abs($daysUntilDue); ?>日超過）
                                            <?php elseif ($daysUntilDue == 0): ?>
                                                （今日が期限）
                                            <?php elseif ($daysUntilDue <= 3): ?>
                                                （あと<?php echo $daysUntilDue; ?>日）
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- 5段階評価フォーム（未評価の場合） -->
                <?php if ($weeklyPlan && !$weeklyPlan['evaluated_at']): ?>
                    <div class="eval-form-section">
                        <form method="POST" action="save_achievement.php">
                            <input type="hidden" name="weekly_plan_id" value="<?php echo $weeklyPlan['id']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                            <input type="hidden" name="return_date" value="<?php echo $targetDate; ?>">
                            <input type="hidden" name="is_current_week" value="1">

                            <div class="eval-form-header">
                                <h3>⭐ 一週間の振り返り評価</h3>
                                <div style="font-size: var(--text-caption-1); color: var(--text-secondary);">
                                    1=できなかった ← → 5=よくできた
                                </div>
                            </div>

                            <!-- 今週の目標 -->
                            <?php if (!empty($weeklyPlan['weekly_goal'])): ?>
                                <div class="eval-item">
                                    <div class="eval-item-header">🎯 今週の目標</div>
                                    <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="eval-5-scale">
                                        <div class="eval-5-scale-boxes">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label>
                                                    <input type="radio" name="weekly_goal_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                    <span class="eval-box"><?php echo $i; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <textarea name="weekly_goal_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                                </div>
                            <?php endif; ?>

                            <!-- いっしょに決めた目標 -->
                            <?php if (!empty($weeklyPlan['shared_goal'])): ?>
                                <div class="eval-item">
                                    <div class="eval-item-header">🤝 いっしょに決めた目標</div>
                                    <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="eval-5-scale">
                                        <div class="eval-5-scale-boxes">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label>
                                                    <input type="radio" name="shared_goal_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                    <span class="eval-box"><?php echo $i; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <textarea name="shared_goal_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                                </div>
                            <?php endif; ?>

                            <!-- やるべきこと -->
                            <?php if (!empty($weeklyPlan['must_do'])): ?>
                                <div class="eval-item">
                                    <div class="eval-item-header">✅ やるべきこと</div>
                                    <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="eval-5-scale">
                                        <div class="eval-5-scale-boxes">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label>
                                                    <input type="radio" name="must_do_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                    <span class="eval-box"><?php echo $i; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <textarea name="must_do_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                                </div>
                            <?php endif; ?>

                            <!-- やったほうがいいこと -->
                            <?php if (!empty($weeklyPlan['should_do'])): ?>
                                <div class="eval-item">
                                    <div class="eval-item-header">👍 やったほうがいいこと</div>
                                    <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="eval-5-scale">
                                        <div class="eval-5-scale-boxes">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label>
                                                    <input type="radio" name="should_do_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                    <span class="eval-box"><?php echo $i; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <textarea name="should_do_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                                </div>
                            <?php endif; ?>

                            <!-- やりたいこと -->
                            <?php if (!empty($weeklyPlan['want_to_do'])): ?>
                                <div class="eval-item">
                                    <div class="eval-item-header">💡 やりたいこと</div>
                                    <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="eval-5-scale">
                                        <div class="eval-5-scale-boxes">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label>
                                                    <input type="radio" name="want_to_do_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                    <span class="eval-box"><?php echo $i; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <textarea name="want_to_do_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                                </div>
                            <?php endif; ?>

                            <!-- 各曜日の計画 -->
                            <?php
                            $hasAnyCurrentDailyPlan = false;
                            foreach ($days as $index => $day) {
                                $dayKey = "day_$index";
                                if (!empty($planData[$dayKey])) {
                                    $hasAnyCurrentDailyPlan = true;
                                    break;
                                }
                            }
                            ?>
                            <?php if ($hasAnyCurrentDailyPlan): ?>
                                <div class="eval-item" style="background: var(--apple-gray-6);">
                                    <div class="eval-item-header" style="font-size: var(--text-callout);">📅 各曜日の計画達成度</div>
                                    <?php foreach ($days as $index => $day):
                                        $dayKey = "day_$index";
                                        if (!empty($planData[$dayKey])):
                                            $date = date('m/d', strtotime("+$index days", strtotime($weekStartDate)));
                                    ?>
                                        <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                            <div style="font-weight: 600; color: var(--primary-purple); margin-bottom: 8px;"><?php echo $day; ?> (<?php echo $date; ?>)</div>
                                            <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($planData[$dayKey], ENT_QUOTES, 'UTF-8')); ?></div>
                                            <div class="eval-5-scale">
                                                <div class="eval-5-scale-boxes">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <label>
                                                            <input type="radio" name="daily_achievement[<?php echo $dayKey; ?>]" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                            <span class="eval-box"><?php echo $i; ?></span>
                                                        </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- 総合コメント -->
                            <div class="eval-overall-comment">
                                <h4>📝 週全体の総合コメント</h4>
                                <textarea name="overall_comment" placeholder="週全体を振り返っての総合コメントを入力してください"></textarea>
                            </div>

                            <button type="submit" class="eval-submit-btn">振り返りを完了</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- 達成度評価表示（評価済みの場合） -->
                <?php if ($weeklyPlan && $weeklyPlan['evaluated_at']): ?>
                    <div class="achievement-display-section" style="margin-top: var(--spacing-2xl); padding: var(--spacing-lg); background: var(--apple-bg-secondary); border: 2px solid #4a90e2; border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                            <h3 style="color: #4a90e2; margin: 0; font-size: 18px;">⭐ 達成度評価</h3>
                            <div style="font-size: var(--text-caption-1); color: var(--text-secondary);">
                                評価日: <?php echo date('Y年m月d日', strtotime($weeklyPlan['evaluated_at'])); ?>
                            </div>
                        </div>

                        <?php
                        $achievementLabels = [
                            0 => '未評価',
                            1 => '1 - できなかった',
                            2 => '2',
                            3 => '3',
                            4 => '4',
                            5 => '5 - よくできた'
                        ];
                        $achievementColors = [
                            0 => '#999',
                            1 => '#e74c3c',
                            2 => '#f39c12',
                            3 => '#3498db',
                            4 => '#2ecc71',
                            5 => '#27ae60'
                        ];
                        ?>

                        <!-- 今週の目標の達成度 -->
                        <?php if (!empty($weeklyPlan['weekly_goal'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                <div style="font-weight: 600; margin-bottom: 5px; color: var(--text-primary);">🎯 今週の目標</div>
                                <div style="font-size: var(--text-subhead); margin-bottom: 8px; padding: var(--spacing-sm); background: var(--apple-gray-6); border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['weekly_goal_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: var(--text-footnote); font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['weekly_goal_comment'])): ?>
                                    <div style="font-size: var(--text-footnote); color: #555; margin-top: 8px; padding: var(--spacing-sm); background: var(--apple-bg-secondary); border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['weekly_goal_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- いっしょに決めた目標の達成度 -->
                        <?php if (!empty($weeklyPlan['shared_goal'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                <div style="font-weight: 600; margin-bottom: 5px; color: var(--text-primary);">🤝 いっしょに決めた目標</div>
                                <div style="font-size: var(--text-subhead); margin-bottom: 8px; padding: var(--spacing-sm); background: var(--apple-gray-6); border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['shared_goal_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: var(--text-footnote); font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['shared_goal_comment'])): ?>
                                    <div style="font-size: var(--text-footnote); color: #555; margin-top: 8px; padding: var(--spacing-sm); background: var(--apple-bg-secondary); border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['shared_goal_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- やるべきことの達成度 -->
                        <?php if (!empty($weeklyPlan['must_do'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                <div style="font-weight: 600; margin-bottom: 5px; color: var(--text-primary);">✅ やるべきこと</div>
                                <div style="font-size: var(--text-subhead); margin-bottom: 8px; padding: var(--spacing-sm); background: var(--apple-gray-6); border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['must_do_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: var(--text-footnote); font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['must_do_comment'])): ?>
                                    <div style="font-size: var(--text-footnote); color: #555; margin-top: 8px; padding: var(--spacing-sm); background: var(--apple-bg-secondary); border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['must_do_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- やったほうがいいことの達成度 -->
                        <?php if (!empty($weeklyPlan['should_do'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                <div style="font-weight: 600; margin-bottom: 5px; color: var(--text-primary);">👍 やったほうがいいこと</div>
                                <div style="font-size: var(--text-subhead); margin-bottom: 8px; padding: var(--spacing-sm); background: var(--apple-gray-6); border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['should_do_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: var(--text-footnote); font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['should_do_comment'])): ?>
                                    <div style="font-size: var(--text-footnote); color: #555; margin-top: 8px; padding: var(--spacing-sm); background: var(--apple-bg-secondary); border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['should_do_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- やりたいことの達成度 -->
                        <?php if (!empty($weeklyPlan['want_to_do'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                <div style="font-weight: 600; margin-bottom: 5px; color: var(--text-primary);">💡 やりたいこと</div>
                                <div style="font-size: var(--text-subhead); margin-bottom: 8px; padding: var(--spacing-sm); background: var(--apple-gray-6); border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['want_to_do_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: var(--text-footnote); font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['want_to_do_comment'])): ?>
                                    <div style="font-size: var(--text-footnote); color: #555; margin-top: 8px; padding: var(--spacing-sm); background: var(--apple-bg-secondary); border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['want_to_do_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- 各曜日の達成度 -->
                        <?php if (!empty($dailyAchievement)): ?>
                            <div class="daily-achievement-display" style="margin-top: var(--spacing-lg);">
                                <h4 style="color: var(--text-primary); font-size: var(--text-callout); margin-bottom: 12px;">📅 各曜日の達成度</h4>
                                <?php
                                $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                                $dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($dayKeys as $index => $dayKey):
                                    if (isset($dailyAchievement[$dayKey]) && $dailyAchievement[$dayKey]['achievement'] > 0):
                                        $dayData = $dailyAchievement[$dayKey];
                                        $achievement = $dayData['achievement'];
                                        $comment = $dayData['comment'] ?? '';
                                        $color = $achievementColors[$achievement];
                                        $label = $achievementLabels[$achievement];
                                ?>
                                    <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: 6px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo $days[$index]; ?></span>
                                            <span style="padding: 3px 10px; background: <?php echo $color; ?>; color: white; border-radius: 3px; font-size: var(--text-caption-1); font-weight: 600;">
                                                <?php echo $label; ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($comment)): ?>
                                            <div style="font-size: var(--text-caption-1); color: #555; margin-top: 6px; padding-left: 10px; border-left: 2px solid <?php echo $color; ?>;">
                                                <?php echo nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        <?php endif; ?>

                        <!-- 総合コメント -->
                        <?php if (!empty($weeklyPlan['overall_comment'])): ?>
                            <div style="margin-top: var(--spacing-lg); padding: 15px; background: var(--apple-bg-primary); border-radius: var(--radius-sm); border-left: 4px solid #4a90e2;">
                                <div style="font-weight: 600; color: #4a90e2; margin-bottom: 8px;">📝 週全体の総合コメント</div>
                                <div style="font-size: var(--text-subhead); color: var(--text-primary); line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['overall_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- コメントセクション -->
        <?php if ($weeklyPlan): ?>
            <div class="comments-section">
                <h3>💬 コメント</h3>

                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment <?php echo $comment['commenter_type']; ?>">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo htmlspecialchars($comment['commenter_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="comment-date"><?php echo date('Y/m/d H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-body">
                                <?php echo nl2br(htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-lg);">まだコメントはありません</p>
                <?php endif; ?>

                <!-- コメント投稿フォーム -->
                <div class="comment-form">
                    <form method="POST" action="add_staff_plan_comment.php">
                        <input type="hidden" name="weekly_plan_id" value="<?php echo $weeklyPlan['id']; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                        <input type="hidden" name="week_start_date" value="<?php echo $weekStartDate; ?>">
                        <textarea name="comment" placeholder="コメントを入力..." required></textarea>
                        <button type="submit">コメントを投稿</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 達成度評価モーダル -->
    <?php if ($prevWeekPlan && !$prevWeekPlan['evaluated_at']): ?>
        <div id="achievementModal" class="achievement-modal">
            <div class="achievement-modal-content">
                <h3 class="achievement-modal-header">
                    ⭐ 前週（<?php echo date('Y年m月d日', strtotime($prevWeekDate)); ?>の週）の達成度評価
                </h3>

                <form id="achievementForm" method="POST" action="save_achievement.php">
                    <input type="hidden" name="weekly_plan_id" value="<?php echo $prevWeekPlan['id']; ?>">
                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                    <input type="hidden" name="return_date" value="<?php echo $targetDate; ?>">

                    <div style="text-align: right; margin-bottom: var(--spacing-md); font-size: var(--text-caption-1); color: var(--text-secondary);">
                        1=できなかった ← → 5=よくできた
                    </div>

                    <!-- 今週の目標 -->
                    <?php if (!empty($prevWeekPlan['weekly_goal'])): ?>
                        <div class="eval-item">
                            <div class="eval-item-header">🎯 今週の目標</div>
                            <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($prevWeekPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="eval-5-scale">
                                <div class="eval-5-scale-boxes">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label>
                                            <input type="radio" name="weekly_goal_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                            <span class="eval-box"><?php echo $i; ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <textarea name="weekly_goal_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- いっしょに決めた目標 -->
                    <?php if (!empty($prevWeekPlan['shared_goal'])): ?>
                        <div class="eval-item">
                            <div class="eval-item-header">🤝 いっしょに決めた目標</div>
                            <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($prevWeekPlan['shared_goal'], ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="eval-5-scale">
                                <div class="eval-5-scale-boxes">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label>
                                            <input type="radio" name="shared_goal_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                            <span class="eval-box"><?php echo $i; ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <textarea name="shared_goal_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- やるべきこと -->
                    <?php if (!empty($prevWeekPlan['must_do'])): ?>
                        <div class="eval-item">
                            <div class="eval-item-header">✅ やるべきこと</div>
                            <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($prevWeekPlan['must_do'], ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="eval-5-scale">
                                <div class="eval-5-scale-boxes">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label>
                                            <input type="radio" name="must_do_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                            <span class="eval-box"><?php echo $i; ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <textarea name="must_do_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- やったほうがいいこと -->
                    <?php if (!empty($prevWeekPlan['should_do'])): ?>
                        <div class="eval-item">
                            <div class="eval-item-header">👍 やったほうがいいこと</div>
                            <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($prevWeekPlan['should_do'], ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="eval-5-scale">
                                <div class="eval-5-scale-boxes">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label>
                                            <input type="radio" name="should_do_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                            <span class="eval-box"><?php echo $i; ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <textarea name="should_do_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- やりたいこと -->
                    <?php if (!empty($prevWeekPlan['want_to_do'])): ?>
                        <div class="eval-item">
                            <div class="eval-item-header">💡 やりたいこと</div>
                            <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($prevWeekPlan['want_to_do'], ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="eval-5-scale">
                                <div class="eval-5-scale-boxes">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label>
                                            <input type="radio" name="want_to_do_achievement" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                            <span class="eval-box"><?php echo $i; ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <textarea name="want_to_do_comment" class="eval-comment-input" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- 各曜日の計画 -->
                    <?php
                    $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                    $hasAnyDailyPlan = false;
                    foreach ($days as $index => $day) {
                        $dayKey = "day_$index";
                        if (!empty($prevPlanData[$dayKey])) {
                            $hasAnyDailyPlan = true;
                            break;
                        }
                    }
                    ?>

                    <?php if ($hasAnyDailyPlan): ?>
                        <div class="eval-item" style="background: var(--apple-gray-6);">
                            <div class="eval-item-header" style="font-size: var(--text-callout);">📅 各曜日の計画達成度</div>
                            <?php foreach ($days as $index => $day):
                                $dayKey = "day_$index";
                                if (!empty($prevPlanData[$dayKey])):
                            ?>
                                <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: var(--radius-sm);">
                                    <div style="font-weight: 600; color: var(--primary-purple); margin-bottom: 8px;"><?php echo $day; ?></div>
                                    <div class="eval-item-content"><?php echo nl2br(htmlspecialchars($prevPlanData[$dayKey], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <div class="eval-5-scale">
                                        <div class="eval-5-scale-boxes">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label>
                                                    <input type="radio" name="daily_achievement[<?php echo $dayKey; ?>]" value="<?php echo $i; ?>" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                                    <span class="eval-box"><?php echo $i; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 総合コメント -->
                    <div class="achievement-section">
                        <h4>📝 週全体の総合コメント</h4>
                        <textarea name="overall_comment" class="achievement-comment" style="min-height: 100px;" placeholder="週全体を振り返っての総合コメントを入力してください"></textarea>
                    </div>

                    <div class="achievement-modal-footer">
                        <button type="button" class="achievement-btn achievement-btn-cancel" onclick="closeAchievementModal()">キャンセル</button>
                        <button type="submit" class="achievement-btn achievement-btn-submit">保存する</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        let submissionCounter = <?php echo !empty($submissions) ? count($submissions) : 1; ?>;

        function addSubmission() {
            const container = document.getElementById('submissionsContainer');
            const newItem = document.createElement('div');
            newItem.className = 'submission-item';
            newItem.innerHTML = `
                <input type="text" name="submissions[${submissionCounter}][item]" placeholder="提出物名">
                <input type="date" name="submissions[${submissionCounter}][due_date]">
                <div class="completed-check">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" name="submissions[${submissionCounter}][completed]" value="1">
                        <span style="font-size: var(--text-footnote);">完了</span>
                    </label>
                </div>
                <button type="button" class="remove-btn" onclick="removeSubmission(this)">×</button>
            `;
            container.appendChild(newItem);
            submissionCounter++;
        }

        function removeSubmission(button) {
            button.closest('.submission-item').remove();
        }

        // 達成度評価モーダル
        function openAchievementModal() {
            document.getElementById('achievementModal').classList.add('active');
        }

        function closeAchievementModal() {
            document.getElementById('achievementModal').classList.remove('active');
        }

        // モーダル外クリックで閉じる
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('achievementModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeAchievementModal();
                    }
                });
            }
        });
    </script>

<?php renderPageEnd(); ?>
