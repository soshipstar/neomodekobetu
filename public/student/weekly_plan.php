<?php
/**
 * 生徒用週間計画表
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

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
    SELECT id, weekly_goal, shared_goal, must_do, should_do, want_to_do, plan_data, created_at, updated_at
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
        SELECT id, submission_item, due_date, is_completed, completed_at
        FROM weekly_plan_submissions WHERE weekly_plan_id = ? ORDER BY due_date ASC, id ASC
    ");
    $stmt->execute([$weeklyPlan['id']]);
    $submissions = $stmt->fetchAll();
}

// コメントを取得
$comments = [];
if ($weeklyPlan) {
    $stmt = $pdo->prepare("
        SELECT wpc.id, wpc.commenter_type, wpc.comment, wpc.created_at,
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

$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($weekStartDate)));
$nextWeek = date('Y-m-d', strtotime('+7 days', strtotime($weekStartDate)));
$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';

$_SESSION['user_type'] = 'student';
$_SESSION['full_name'] = $student['student_name'];

// ページ開始
$currentPage = 'weekly_plan';
renderPageStart('student', $currentPage, '週間計画表');
?>

<style>
.week-nav {
    background: var(--apple-bg-primary);
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-sm);
}

.week-nav h2 {
    color: var(--text-primary);
    font-size: var(--text-body);
    margin: 0;
}

.week-nav-buttons {
    display: flex;
    gap: var(--spacing-sm);
}

.plan-section {
    margin-bottom: var(--spacing-lg);
}

.plan-section h3 {
    color: var(--apple-purple);
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

.view-content {
    padding: var(--spacing-md);
    background: var(--apple-gray-6);
    border-left: 4px solid var(--apple-purple);
    border-radius: var(--radius-sm);
    line-height: 1.6;
    white-space: pre-wrap;
}

.view-content.empty {
    color: var(--text-secondary);
    font-style: italic;
}

.daily-plans { margin-top: var(--spacing-lg); }

.daily-plans h3 {
    color: var(--text-primary);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
}

.day-plan {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
    align-items: start;
}

.day-label {
    font-weight: 600;
    color: var(--apple-purple);
    padding-top: var(--spacing-md);
}

.day-date {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
}

.submissions-section {
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-lg);
    border-top: 2px solid var(--apple-gray-5);
}

.submissions-section h3 {
    color: var(--apple-red);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
}

.submission-view-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    background: var(--apple-gray-6);
    border-left: 4px solid var(--apple-orange);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
}

.submission-view-item.completed {
    opacity: 0.6;
    border-left-color: var(--apple-green);
    text-decoration: line-through;
}

.submission-info { flex: 1; }

.submission-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.submission-date {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
}

.submission-date.urgent { color: var(--apple-red); font-weight: 600; }
.submission-date.overdue { color: #721c24; font-weight: 700; }

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

.comments-section { margin-top: var(--spacing-lg); }

.comment {
    padding: var(--spacing-md);
    background: var(--apple-gray-6);
    border-left: 4px solid var(--apple-purple);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
}

.comment.staff { border-left-color: var(--apple-green); }
.comment.guardian { border-left-color: var(--apple-orange); }

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
}

.comment-author {
    font-weight: 600;
    color: var(--apple-purple);
}

.comment-date {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
}

.comment-body {
    color: var(--text-primary);
    line-height: 1.6;
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

.no-plan {
    text-align: center;
    padding: var(--spacing-3xl);
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .day-plan { grid-template-columns: 1fr; gap: 5px; }
    .week-nav { flex-direction: column; gap: var(--spacing-md); }
    .comment-form textarea { font-size: 16px; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">週間計画表</h1>
        <p class="page-subtitle">今週の計画を立てる・確認する</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php if ($_GET['success'] == '1'): ?>週間計画表を保存しました<?php elseif ($_GET['success'] == '2'): ?>コメントを投稿しました<?php elseif ($_GET['success'] == '3'): ?>提出物を更新しました<?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="week-nav">
    <h2><?= date('Y年m月d日', strtotime($weekStartDate)) ?>の週</h2>
    <div class="week-nav-buttons">
        <a href="?date=<?= $prevWeek ?>" class="btn btn-secondary btn-sm">← 前週</a>
        <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-primary btn-sm">今週</a>
        <a href="?date=<?= $nextWeek ?>" class="btn btn-secondary btn-sm">次週 →</a>
    </div>
</div>

<?php if (!$weeklyPlan && !$isEditMode): ?>
    <div class="card">
        <div class="card-body no-plan">
            <p>この週の計画はまだ作成されていません</p>
            <a href="?date=<?= $targetDate ?>&edit=1" class="btn btn-primary" style="margin-top: var(--spacing-lg);">新しい計画を作成する</a>
        </div>
    </div>
<?php elseif ($isEditMode): ?>
    <!-- 編集モード -->
    <form method="POST" action="save_weekly_plan.php">
        <input type="hidden" name="week_start_date" value="<?= $weekStartDate ?>">
        <div class="card">
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                    <h2 style="color: var(--text-primary); font-size: var(--text-title-3); margin: 0;">週間計画を編集</h2>
                    <div style="display: flex; gap: var(--spacing-sm);">
                        <a href="?date=<?= $targetDate ?>" class="btn btn-secondary">キャンセル</a>
                        <button type="submit" class="btn btn-success">保存する</button>
                    </div>
                </div>

                <div class="plan-section">
                    <h3>🎯 今週の目標</h3>
                    <textarea name="weekly_goal" rows="3" placeholder="今週達成したい目標を書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3>🤝 いっしょに決めた目標</h3>
                    <textarea name="shared_goal" rows="3" placeholder="先生や保護者と一緒に決めた目標を書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3>✅ やるべきこと</h3>
                    <textarea name="must_do" rows="3" placeholder="必ずやらなければならないことを書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3>👍 やったほうがいいこと</h3>
                    <textarea name="should_do" rows="3" placeholder="できればやった方がいいことを書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3>💡 やりたいこと</h3>
                    <textarea name="want_to_do" rows="3" placeholder="やってみたいことを書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

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
                                <div class="day-label"><?= $day ?></div>
                                <div class="day-date"><?= $date ?></div>
                            </div>
                            <textarea name="<?= $dayKey ?>" rows="2" placeholder="この日の計画や目標を記入してください"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>
<?php else: ?>
    <!-- 表示モード -->
    <div class="card">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                <h2 style="color: var(--text-primary); font-size: var(--text-title-3); margin: 0;">週間計画</h2>
                <a href="?date=<?= $targetDate ?>&edit=1" class="btn btn-primary">編集する</a>
            </div>

            <div class="plan-section">
                <h3>🎯 今週の目標</h3>
                <div class="view-content <?= empty($weeklyPlan['weekly_goal']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['weekly_goal']) ? nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3>🤝 いっしょに決めた目標</h3>
                <div class="view-content <?= empty($weeklyPlan['shared_goal']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['shared_goal']) ? nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3>✅ やるべきこと</h3>
                <div class="view-content <?= empty($weeklyPlan['must_do']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['must_do']) ? nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3>👍 やったほうがいいこと</h3>
                <div class="view-content <?= empty($weeklyPlan['should_do']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['should_do']) ? nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3>💡 やりたいこと</h3>
                <div class="view-content <?= empty($weeklyPlan['want_to_do']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['want_to_do']) ? nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

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
                            <div class="day-label"><?= $day ?></div>
                            <div class="day-date"><?= $date ?></div>
                        </div>
                        <div class="view-content <?= empty($content) ? 'empty' : '' ?>">
                            <?= !empty($content) ? nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) : '予定なし' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($submissions)): ?>
                <div class="submissions-section">
                    <h3>📋 提出物一覧</h3>
                    <?php foreach ($submissions as $sub):
                        $dueDate = new DateTime($sub['due_date']);
                        $today = new DateTime();
                        $diff = $today->diff($dueDate);
                        $daysUntilDue = (int)$diff->format('%r%a');
                        $dateClass = ($daysUntilDue < 0) ? 'overdue' : (($daysUntilDue <= 3) ? 'urgent' : '');
                    ?>
                        <div class="submission-view-item <?= $sub['is_completed'] ? 'completed' : '' ?>">
                            <div class="submission-info">
                                <div class="submission-title">
                                    <?= $sub['is_completed'] ? '✅ ' : '' ?><?= htmlspecialchars($sub['submission_item'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="submission-date <?= $dateClass ?>">
                                    期限: <?= date('Y年m月d日', strtotime($sub['due_date'])) ?>
                                    <?php if (!$sub['is_completed']): ?>
                                        <?php if ($daysUntilDue < 0): ?>（<?= abs($daysUntilDue) ?>日超過）<?php elseif ($daysUntilDue == 0): ?>（今日が期限）<?php elseif ($daysUntilDue <= 3): ?>（あと<?= $daysUntilDue ?>日）<?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="submission-checkbox">
                                <input type="checkbox" id="submission_<?= $sub['id'] ?>" <?= $sub['is_completed'] ? 'checked' : '' ?> onchange="toggleSubmission(<?= $sub['id'] ?>, this.checked)">
                                <label for="submission_<?= $sub['id'] ?>">完了</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($weeklyPlan): ?>
    <div class="card comments-section">
        <div class="card-body">
            <h3 style="margin-bottom: var(--spacing-lg);">💬 コメント</h3>

            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment <?= $comment['commenter_type'] ?>">
                        <div class="comment-header">
                            <span class="comment-author"><?= htmlspecialchars($comment['commenter_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="comment-date"><?= date('Y/m/d H:i', strtotime($comment['created_at'])) ?></span>
                        </div>
                        <div class="comment-body"><?= nl2br(htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8')) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-lg);">まだコメントはありません</p>
            <?php endif; ?>

            <div class="comment-form" style="margin-top: var(--spacing-lg);">
                <form method="POST" action="add_plan_comment.php">
                    <input type="hidden" name="weekly_plan_id" value="<?= $weeklyPlan['id'] ?>">
                    <input type="hidden" name="date" value="<?= $targetDate ?>">
                    <textarea name="comment" placeholder="コメントを入力..." required></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: var(--spacing-sm);">コメントを投稿</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$inlineJs = <<<JS
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
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
