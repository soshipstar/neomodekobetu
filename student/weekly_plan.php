<?php
/**
 * 生徒用週間計画表
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 表示する週を決定（デフォルトは今週）
$targetDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
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
        created_at,
        updated_at
    FROM weekly_plans
    WHERE student_id = ? AND week_start_date = ?
");
$stmt->execute([$studentId, $weekStartDate]);
$weeklyPlan = $stmt->fetch();

$planData = $weeklyPlan ? json_decode($weeklyPlan['plan_data'], true) : [];

// 提出物を取得
$submissions = [];
if ($weeklyPlan) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            submission_item,
            due_date,
            is_completed,
            completed_at
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
                ELSE '本人'
            END as commenter_name
        FROM weekly_plan_comments wpc
        LEFT JOIN users u ON wpc.commenter_type = 'staff' AND wpc.commenter_id = u.id
        LEFT JOIN users u2 ON wpc.commenter_type = 'guardian' AND wpc.commenter_id = u2.id
        WHERE wpc.weekly_plan_id = ?
        ORDER BY wpc.created_at ASC
    ");
    $stmt->execute([$weeklyPlan['id']]);
    $comments = $stmt->fetchAll();
}

// 前週・次週の日付
$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($weekStartDate)));
$nextWeek = date('Y-m-d', strtotime('+7 days', strtotime($weekStartDate)));

// 編集モード
$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>週間計画表 - 個別支援連絡帳システム</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
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
            color: #333;
            font-size: 18px;
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
            font-size: 14px;
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

        .submission-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .submission-checkbox input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        .submission-checkbox label {
            font-size: 14px;
            cursor: pointer;
            user-select: none;
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
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .comment-form button:hover {
            background: #5568d3;
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

        @media (max-width: 768px) {
            .day-plan {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 週間計画表</h1>
            <a href="../student/index.php" class="back-btn">← トップに戻る</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                <?php if ($_GET['success'] == '1'): ?>
                    週間計画表を保存しました
                <?php elseif ($_GET['success'] == '2'): ?>
                    コメントを投稿しました
                <?php elseif ($_GET['success'] == '3'): ?>
                    提出物を更新しました
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="week-nav">
            <h2><?php echo date('Y年m月d日', strtotime($weekStartDate)); ?>の週</h2>
            <div class="week-nav-buttons">
                <a href="?date=<?php echo $prevWeek; ?>">← 前週</a>
                <a href="?date=<?php echo date('Y-m-d'); ?>">今週</a>
                <a href="?date=<?php echo $nextWeek; ?>">次週 →</a>
            </div>
        </div>

        <?php if (!$weeklyPlan && !$isEditMode): ?>
            <div class="plan-container">
                <div class="no-plan">
                    <p>この週の計画はまだ作成されていません</p>
                    <p style="font-size: 14px;">先生が計画を作成するまでお待ちください</p>
                </div>
            </div>
        <?php elseif ($isEditMode): ?>
            <!-- 編集モード（生徒は各曜日の計画のみ編集可能） -->
            <form method="POST" action="save_weekly_plan.php">
                <input type="hidden" name="week_start_date" value="<?php echo $weekStartDate; ?>">

                <div class="plan-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h2 style="color: #333; font-size: 20px;">📝 週間計画を編集</h2>
                        <div style="display: flex; gap: 10px;">
                            <a href="?date=<?php echo $targetDate; ?>" class="btn btn-secondary">キャンセル</a>
                            <button type="submit" class="btn btn-primary">保存する</button>
                        </div>
                    </div>

                    <!-- 今週の目標（表示のみ） -->
                    <div class="plan-section">
                        <h3>🎯 今週の目標</h3>
                        <div class="view-content <?php echo empty($weeklyPlan['weekly_goal']) ? 'empty' : ''; ?>">
                            <?php echo !empty($weeklyPlan['weekly_goal']) ? nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                        </div>
                    </div>

                    <!-- いっしょに決めた目標（表示のみ） -->
                    <div class="plan-section">
                        <h3>🤝 いっしょに決めた目標</h3>
                        <div class="view-content <?php echo empty($weeklyPlan['shared_goal']) ? 'empty' : ''; ?>">
                            <?php echo !empty($weeklyPlan['shared_goal']) ? nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                        </div>
                    </div>

                    <!-- やるべきこと（表示のみ） -->
                    <div class="plan-section">
                        <h3>✅ やるべきこと</h3>
                        <div class="view-content <?php echo empty($weeklyPlan['must_do']) ? 'empty' : ''; ?>">
                            <?php echo !empty($weeklyPlan['must_do']) ? nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                        </div>
                    </div>

                    <!-- やったほうがいいこと（表示のみ） -->
                    <div class="plan-section">
                        <h3>👍 やったほうがいいこと</h3>
                        <div class="view-content <?php echo empty($weeklyPlan['should_do']) ? 'empty' : ''; ?>">
                            <?php echo !empty($weeklyPlan['should_do']) ? nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                        </div>
                    </div>

                    <!-- やりたいこと（表示のみ） -->
                    <div class="plan-section">
                        <h3>💡 やりたいこと</h3>
                        <div class="view-content <?php echo empty($weeklyPlan['want_to_do']) ? 'empty' : ''; ?>">
                            <?php echo !empty($weeklyPlan['want_to_do']) ? nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                        </div>
                    </div>

                    <!-- 各曜日の計画（編集可能） -->
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
                </div>
            </form>
        <?php else: ?>
            <!-- 表示モード -->
            <div class="plan-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 style="color: #333; font-size: 20px;">📝 週間計画</h2>
                    <a href="?date=<?php echo $targetDate; ?>&edit=1" class="btn btn-edit">編集する</a>
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
                                <div class="submission-checkbox">
                                    <input
                                        type="checkbox"
                                        id="submission_<?php echo $sub['id']; ?>"
                                        <?php echo $sub['is_completed'] ? 'checked' : ''; ?>
                                        onchange="toggleSubmission(<?php echo $sub['id']; ?>, this.checked)"
                                    >
                                    <label for="submission_<?php echo $sub['id']; ?>">完了</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    <form method="POST" action="add_plan_comment.php">
                        <input type="hidden" name="weekly_plan_id" value="<?php echo $weeklyPlan['id']; ?>">
                        <input type="hidden" name="date" value="<?php echo $targetDate; ?>">
                        <textarea name="comment" placeholder="コメントを入力..." required></textarea>
                        <button type="submit">コメントを投稿</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleSubmission(submissionId, isCompleted) {
            const formData = new FormData();
            formData.append('submission_id', submissionId);
            formData.append('is_completed', isCompleted ? '1' : '0');

            fetch('toggle_submission.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラーが発生しました: ' + (data.error || '不明なエラー'));
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
                location.reload();
            });
        }
    </script>
</body>
</html>
