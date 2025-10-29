<?php
/**
 * スタッフ用 - 生徒週間計画表詳細
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>の週間計画表 - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 24px;
            color: #333;
        }

        .back-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .week-nav {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .week-nav h2 {
            font-size: 18px;
            color: #333;
        }

        .week-nav-buttons {
            display: flex;
            gap: 10px;
        }

        .week-nav-buttons a {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
        }

        .week-nav-buttons a:hover {
            background: #5568d3;
        }

        .plan-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .plan-section {
            margin-bottom: 25px;
        }

        .plan-section h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .plan-section textarea {
            width: 100%;
            min-height: 60px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
        }

        .plan-section .view-content {
            padding: 12px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .plan-section .view-content.empty {
            color: #999;
            font-style: italic;
        }

        .daily-plans {
            margin-top: 20px;
        }

        .daily-plans h3 {
            color: #333;
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
            color: #667eea;
            padding-top: 12px;
        }

        .day-date {
            font-size: 12px;
            color: #666;
        }

        .submissions-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }

        .submissions-section h3 {
            color: #dc3545;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .submission-item {
            display: grid;
            grid-template-columns: 1fr 150px 100px 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .submission-item input[type="text"],
        .submission-item input[type="date"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
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
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }

        .submission-item .remove-btn:hover {
            background: #c82333;
        }

        .add-submission-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }

        .add-submission-btn:hover {
            background: #218838;
        }

        .submission-view-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .submission-view-item.completed {
            opacity: 0.6;
            border-left-color: #28a745;
            text-decoration: line-through;
        }

        .submission-info {
            flex: 1;
        }

        .submission-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .submission-date {
            font-size: 12px;
            color: #666;
        }

        .submission-date.urgent {
            color: #dc3545;
            font-weight: 600;
        }

        .submission-date.overdue {
            color: #721c24;
            font-weight: 700;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-edit {
            background: #667eea;
            color: white;
        }

        .btn-edit:hover {
            background: #5568d3;
        }

        .comments-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .comments-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .comment {
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .comment.staff {
            border-left-color: #28a745;
        }

        .comment.student {
            border-left-color: #667eea;
        }

        .comment.guardian {
            border-left-color: #ffc107;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: #667eea;
        }

        .comment-date {
            font-size: 12px;
            color: #999;
        }

        .comment-body {
            color: #333;
            line-height: 1.6;
        }

        .comment-form {
            margin-top: 20px;
        }

        .comment-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }

        .comment-form button {
            margin-top: 10px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .comment-form button:hover {
            background: #218838;
        }

        .message {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .no-plan {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-plan p {
            font-size: 16px;
            margin-bottom: 20px;
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
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 900px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .achievement-modal-header {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .achievement-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .achievement-section h4 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .goal-content {
            padding: 10px;
            background: white;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            margin-bottom: 15px;
            min-height: 40px;
            line-height: 1.5;
        }

        .goal-content.empty {
            color: #999;
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
            font-size: 14px;
        }

        .achievement-radios input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .achievement-comment {
            width: 100%;
            min-height: 60px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
        }

        .achievement-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .achievement-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .achievement-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .achievement-btn-cancel:hover {
            background: #5a6268;
        }

        .achievement-btn-submit {
            background: #28a745;
            color: white;
        }

        .achievement-btn-submit:hover {
            background: #218838;
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
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんの週間計画表</h1>
            <a href="student_weekly_plans.php" class="back-btn">← 一覧に戻る</a>
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
                    <button type="button" onclick="openAchievementModal()" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600;">
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
                        <h2 style="color: #333; font-size: 20px;">📝 週間計画を編集</h2>
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
                                                <span style="font-size: 13px;">完了</span>
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
                                            <span style="font-size: 13px;">完了</span>
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
                    <h2 style="color: #333; font-size: 20px;">📝 週間計画</h2>
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>&edit=1" class="btn btn-edit">編集する</a>
                </div>

                <!-- 今週の目標 -->
                <div class="plan-section">
                    <h3>🎯 今週の目標</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['weekly_goal']) ? 'empty' : ''; ?>">
                        <?php echo !empty($weeklyPlan['weekly_goal']) ? nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <!-- いっしょに決めた目標 -->
                <div class="plan-section">
                    <h3>🤝 いっしょに決めた目標</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['shared_goal']) ? 'empty' : ''; ?>">
                        <?php echo !empty($weeklyPlan['shared_goal']) ? nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <!-- やるべきこと -->
                <div class="plan-section">
                    <h3>✅ やるべきこと</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['must_do']) ? 'empty' : ''; ?>">
                        <?php echo !empty($weeklyPlan['must_do']) ? nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <!-- やったほうがいいこと -->
                <div class="plan-section">
                    <h3>👍 やったほうがいいこと</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['should_do']) ? 'empty' : ''; ?>">
                        <?php echo !empty($weeklyPlan['should_do']) ? nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <!-- やりたいこと -->
                <div class="plan-section">
                    <h3>💡 やりたいこと</h3>
                    <div class="view-content <?php echo empty($weeklyPlan['want_to_do']) ? 'empty' : ''; ?>">
                        <?php echo !empty($weeklyPlan['want_to_do']) ? nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
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
                            <div class="view-content <?php echo empty($content) ? 'empty' : ''; ?>">
                                <?php echo !empty($content) ? nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) : '予定なし'; ?>
                            </div>
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

                <!-- 達成度評価表示 -->
                <?php if ($weeklyPlan && $weeklyPlan['evaluated_at']): ?>
                    <div class="achievement-display-section" style="margin-top: 30px; padding: 20px; background: #f0f8ff; border: 2px solid #4a90e2; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="color: #4a90e2; margin: 0; font-size: 18px;">⭐ 達成度評価</h3>
                            <div style="font-size: 12px; color: #666;">
                                評価日: <?php echo date('Y年m月d日', strtotime($weeklyPlan['evaluated_at'])); ?>
                            </div>
                        </div>

                        <?php
                        $achievementLabels = [
                            0 => '未評価',
                            1 => '未達成',
                            2 => '一部達成',
                            3 => '達成'
                        ];
                        $achievementColors = [
                            0 => '#999',
                            1 => '#e74c3c',
                            2 => '#f39c12',
                            3 => '#27ae60'
                        ];
                        ?>

                        <!-- 今週の目標の達成度 -->
                        <?php if (!empty($weeklyPlan['weekly_goal'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 8px;">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #333;">🎯 今週の目標</div>
                                <div style="font-size: 14px; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['weekly_goal_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['weekly_goal_comment'])): ?>
                                    <div style="font-size: 13px; color: #555; margin-top: 8px; padding: 8px; background: #fffbf0; border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['weekly_goal_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- いっしょに決めた目標の達成度 -->
                        <?php if (!empty($weeklyPlan['shared_goal'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 8px;">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #333;">🤝 いっしょに決めた目標</div>
                                <div style="font-size: 14px; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['shared_goal_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['shared_goal_comment'])): ?>
                                    <div style="font-size: 13px; color: #555; margin-top: 8px; padding: 8px; background: #fffbf0; border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['shared_goal_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- やるべきことの達成度 -->
                        <?php if (!empty($weeklyPlan['must_do'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 8px;">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #333;">✅ やるべきこと</div>
                                <div style="font-size: 14px; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['must_do_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['must_do_comment'])): ?>
                                    <div style="font-size: 13px; color: #555; margin-top: 8px; padding: 8px; background: #fffbf0; border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['must_do_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- やったほうがいいことの達成度 -->
                        <?php if (!empty($weeklyPlan['should_do'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 8px;">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #333;">👍 やったほうがいいこと</div>
                                <div style="font-size: 14px; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['should_do_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['should_do_comment'])): ?>
                                    <div style="font-size: 13px; color: #555; margin-top: 8px; padding: 8px; background: #fffbf0; border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['should_do_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- やりたいことの達成度 -->
                        <?php if (!empty($weeklyPlan['want_to_do'])): ?>
                            <div class="achievement-item" style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 8px;">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #333;">💡 やりたいこと</div>
                                <div style="font-size: 14px; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <?php
                                $achievement = $weeklyPlan['want_to_do_achievement'] ?? 0;
                                $color = $achievementColors[$achievement];
                                $label = $achievementLabels[$achievement];
                                ?>
                                <div style="display: inline-block; padding: 4px 12px; background: <?php echo $color; ?>; color: white; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                                    <?php echo $label; ?>
                                </div>
                                <?php if (!empty($weeklyPlan['want_to_do_comment'])): ?>
                                    <div style="font-size: 13px; color: #555; margin-top: 8px; padding: 8px; background: #fffbf0; border-left: 3px solid #f39c12; border-radius: 4px;">
                                        💬 <?php echo nl2br(htmlspecialchars($weeklyPlan['want_to_do_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- 各曜日の達成度 -->
                        <?php if (!empty($dailyAchievement)): ?>
                            <div class="daily-achievement-display" style="margin-top: 20px;">
                                <h4 style="color: #333; font-size: 16px; margin-bottom: 12px;">📅 各曜日の達成度</h4>
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
                                    <div style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-weight: 600; color: #333;"><?php echo $days[$index]; ?></span>
                                            <span style="padding: 3px 10px; background: <?php echo $color; ?>; color: white; border-radius: 3px; font-size: 12px; font-weight: 600;">
                                                <?php echo $label; ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($comment)): ?>
                                            <div style="font-size: 12px; color: #555; margin-top: 6px; padding-left: 10px; border-left: 2px solid <?php echo $color; ?>;">
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
                            <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #4a90e2;">
                                <div style="font-weight: 600; color: #4a90e2; margin-bottom: 8px;">📝 週全体の総合コメント</div>
                                <div style="font-size: 14px; color: #333; line-height: 1.6;">
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
                    <p style="color: #999; text-align: center; padding: 20px;">まだコメントはありません</p>
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

                    <!-- 今週の目標 -->
                    <?php if (!empty($prevWeekPlan['weekly_goal'])): ?>
                        <div class="achievement-section">
                            <h4>🎯 今週の目標</h4>
                            <div class="goal-content">
                                <?php echo nl2br(htmlspecialchars($prevWeekPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                            <div class="achievement-radios">
                                <label><input type="radio" name="weekly_goal_achievement" value="1"> 未達成</label>
                                <label><input type="radio" name="weekly_goal_achievement" value="2"> 一部達成</label>
                                <label><input type="radio" name="weekly_goal_achievement" value="3" checked> 達成</label>
                            </div>
                            <textarea name="weekly_goal_comment" class="achievement-comment" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- いっしょに決めた目標 -->
                    <?php if (!empty($prevWeekPlan['shared_goal'])): ?>
                        <div class="achievement-section">
                            <h4>🤝 いっしょに決めた目標</h4>
                            <div class="goal-content">
                                <?php echo nl2br(htmlspecialchars($prevWeekPlan['shared_goal'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                            <div class="achievement-radios">
                                <label><input type="radio" name="shared_goal_achievement" value="1"> 未達成</label>
                                <label><input type="radio" name="shared_goal_achievement" value="2"> 一部達成</label>
                                <label><input type="radio" name="shared_goal_achievement" value="3" checked> 達成</label>
                            </div>
                            <textarea name="shared_goal_comment" class="achievement-comment" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- やるべきこと -->
                    <?php if (!empty($prevWeekPlan['must_do'])): ?>
                        <div class="achievement-section">
                            <h4>✅ やるべきこと</h4>
                            <div class="goal-content">
                                <?php echo nl2br(htmlspecialchars($prevWeekPlan['must_do'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                            <div class="achievement-radios">
                                <label><input type="radio" name="must_do_achievement" value="1"> 未達成</label>
                                <label><input type="radio" name="must_do_achievement" value="2"> 一部達成</label>
                                <label><input type="radio" name="must_do_achievement" value="3" checked> 達成</label>
                            </div>
                            <textarea name="must_do_comment" class="achievement-comment" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- やったほうがいいこと -->
                    <?php if (!empty($prevWeekPlan['should_do'])): ?>
                        <div class="achievement-section">
                            <h4>👍 やったほうがいいこと</h4>
                            <div class="goal-content">
                                <?php echo nl2br(htmlspecialchars($prevWeekPlan['should_do'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                            <div class="achievement-radios">
                                <label><input type="radio" name="should_do_achievement" value="1"> 未達成</label>
                                <label><input type="radio" name="should_do_achievement" value="2"> 一部達成</label>
                                <label><input type="radio" name="should_do_achievement" value="3" checked> 達成</label>
                            </div>
                            <textarea name="should_do_comment" class="achievement-comment" placeholder="コメント（任意）"></textarea>
                        </div>
                    <?php endif; ?>

                    <!-- やりたいこと -->
                    <?php if (!empty($prevWeekPlan['want_to_do'])): ?>
                        <div class="achievement-section">
                            <h4>💡 やりたいこと</h4>
                            <div class="goal-content">
                                <?php echo nl2br(htmlspecialchars($prevWeekPlan['want_to_do'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                            <div class="achievement-radios">
                                <label><input type="radio" name="want_to_do_achievement" value="1"> 未達成</label>
                                <label><input type="radio" name="want_to_do_achievement" value="2"> 一部達成</label>
                                <label><input type="radio" name="want_to_do_achievement" value="3" checked> 達成</label>
                            </div>
                            <textarea name="want_to_do_comment" class="achievement-comment" placeholder="コメント（任意）"></textarea>
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
                        <div class="achievement-section">
                            <h4>📅 各曜日の計画達成度</h4>
                            <?php foreach ($days as $index => $day):
                                $dayKey = "day_$index";
                                if (!empty($prevPlanData[$dayKey])):
                            ?>
                                <div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 5px;">
                                    <div style="font-weight: 600; color: #667eea; margin-bottom: 8px;"><?php echo $day; ?></div>
                                    <div class="goal-content" style="margin-bottom: 10px;">
                                        <?php echo nl2br(htmlspecialchars($prevPlanData[$dayKey], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                    <div class="achievement-radios">
                                        <label><input type="radio" name="daily_achievement[<?php echo $dayKey; ?>]" value="1"> 未達成</label>
                                        <label><input type="radio" name="daily_achievement[<?php echo $dayKey; ?>]" value="2"> 一部達成</label>
                                        <label><input type="radio" name="daily_achievement[<?php echo $dayKey; ?>]" value="3" checked> 達成</label>
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
                        <span style="font-size: 13px;">完了</span>
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
</body>
</html>
