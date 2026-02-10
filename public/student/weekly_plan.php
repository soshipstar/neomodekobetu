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
/* ========================================
   週間計画 - モバイルファースト設計
   ======================================== */

.week-nav {
    background: var(--cds-layer-01, var(--md-bg-primary));
    padding: var(--cds-spacing-04, var(--spacing-md));
    border-radius: 0;
    margin-bottom: var(--cds-spacing-05, var(--spacing-lg));
    display: flex;
    flex-direction: column;
    gap: var(--cds-spacing-04, var(--spacing-md));
    box-shadow: var(--cds-shadow, var(--shadow-sm));
}

.week-nav h2 {
    color: var(--cds-text-primary, var(--text-primary));
    font-size: 16px;
    margin: 0;
    text-align: center;
}

.week-nav-buttons {
    display: flex;
    gap: var(--cds-spacing-03, var(--spacing-sm));
    width: 100%;
}

.week-nav-buttons .btn {
    flex: 1;
    text-align: center;
    padding: 12px 8px;
    font-size: 14px;
    min-height: 44px;
}

.plan-section {
    margin-bottom: var(--cds-spacing-05, var(--spacing-lg));
}

.plan-section h3 {
    color: var(--cds-orange-40, var(--md-purple));
    font-size: 15px;
    margin-bottom: var(--cds-spacing-03, var(--spacing-md));
    display: flex;
    align-items: center;
    gap: 8px;
}

.plan-section textarea {
    width: 100%;
    min-height: 80px;
    padding: var(--cds-spacing-04, var(--spacing-md));
    border: 1px solid var(--cds-border-strong-01, var(--md-gray-5));
    border-radius: 0;
    font-size: 16px; /* iOS zoom防止 */
    font-family: inherit;
    resize: vertical;
    box-sizing: border-box;
    -webkit-appearance: none;
    appearance: none;
}

.plan-section textarea:focus {
    outline: 2px solid var(--cds-orange-40);
    outline-offset: -2px;
    border-color: var(--cds-orange-40);
}

.view-content {
    padding: var(--cds-spacing-04, var(--spacing-md));
    background: var(--cds-layer-02, var(--md-gray-6));
    border-left: 4px solid var(--cds-orange-40, var(--md-purple));
    border-radius: 0;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

.view-content.empty {
    color: var(--cds-text-secondary, var(--text-secondary));
    font-style: italic;
}

.daily-plans {
    margin-top: var(--cds-spacing-05, var(--spacing-lg));
}

.daily-plans h3 {
    color: var(--cds-text-primary, var(--text-primary));
    font-size: 16px;
    margin-bottom: var(--cds-spacing-04, var(--spacing-md));
}

.day-plan {
    display: flex;
    flex-direction: column;
    gap: var(--cds-spacing-02, 4px);
    margin-bottom: var(--cds-spacing-04, var(--spacing-md));
    padding-bottom: var(--cds-spacing-04, var(--spacing-md));
    border-bottom: 1px solid var(--cds-border-subtle-00, var(--md-gray-5));
}

.day-plan:last-child {
    border-bottom: none;
}

.day-label {
    font-weight: 600;
    color: var(--cds-orange-40, var(--md-purple));
    font-size: 15px;
}

.day-date {
    font-size: 13px;
    color: var(--cds-text-secondary, var(--text-secondary));
    margin-bottom: var(--cds-spacing-02, 4px);
}

.day-plan textarea {
    width: 100%;
    min-height: 60px;
    padding: var(--cds-spacing-03, 12px);
    border: 1px solid var(--cds-border-strong-01, var(--md-gray-5));
    border-radius: 0;
    font-size: 16px; /* iOS zoom防止 */
    font-family: inherit;
    resize: vertical;
    box-sizing: border-box;
    -webkit-appearance: none;
    appearance: none;
}

.day-plan textarea:focus {
    outline: 2px solid var(--cds-orange-40);
    outline-offset: -2px;
    border-color: var(--cds-orange-40);
}

.submissions-section {
    margin-top: var(--cds-spacing-06, var(--spacing-xl));
    padding-top: var(--cds-spacing-05, var(--spacing-lg));
    border-top: 2px solid var(--cds-border-subtle-00, var(--md-gray-5));
}

.submissions-section h3 {
    color: var(--cds-support-error, var(--md-red));
    font-size: 16px;
    margin-bottom: var(--cds-spacing-04, var(--spacing-md));
}

.submission-view-item {
    display: flex;
    flex-direction: column;
    gap: var(--cds-spacing-03, 12px);
    padding: var(--cds-spacing-04, var(--spacing-md));
    background: var(--cds-layer-02, var(--md-gray-6));
    border-left: 4px solid var(--cds-orange-40, var(--md-orange));
    border-radius: 0;
    margin-bottom: var(--cds-spacing-04, var(--spacing-md));
}

.submission-view-item.completed {
    opacity: 0.6;
    border-left-color: var(--cds-support-success, var(--md-green));
    text-decoration: line-through;
}

.submission-info { flex: 1; }

.submission-title {
    font-weight: 600;
    color: var(--cds-text-primary, var(--text-primary));
    margin-bottom: 5px;
    font-size: 15px;
}

.submission-date {
    font-size: 13px;
    color: var(--cds-text-secondary, var(--text-secondary));
}

.submission-date.urgent { color: var(--cds-support-error, var(--md-red)); font-weight: 600; }
.submission-date.overdue { color: var(--cds-support-error); font-weight: 700; }

.submission-checkbox {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
}

.submission-checkbox input[type="checkbox"] {
    width: 28px;
    height: 28px;
    cursor: pointer;
    accent-color: var(--cds-orange-40);
}

.submission-checkbox label {
    font-size: 15px;
    font-weight: 500;
}

.comments-section {
    margin-top: var(--cds-spacing-05, var(--spacing-lg));
}

.comment {
    padding: var(--cds-spacing-04, var(--spacing-md));
    background: var(--cds-layer-02, var(--md-gray-6));
    border-left: 4px solid var(--cds-orange-40, var(--md-purple));
    border-radius: 0;
    margin-bottom: var(--cds-spacing-04, var(--spacing-md));
}

.comment.staff { border-left-color: var(--cds-support-success, var(--md-green)); }
.comment.guardian { border-left-color: var(--cds-orange-40, var(--md-orange)); }

.comment-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: var(--cds-spacing-03, var(--spacing-sm));
}

.comment-author {
    font-weight: 600;
    color: var(--cds-orange-40, var(--md-purple));
    font-size: 14px;
}

.comment-date {
    font-size: 12px;
    color: var(--cds-text-secondary, var(--text-secondary));
}

.comment-body {
    color: var(--cds-text-primary, var(--text-primary));
    line-height: 1.6;
    font-size: 15px;
}

.comment-form textarea {
    width: 100%;
    min-height: 100px;
    padding: var(--cds-spacing-04, var(--spacing-md));
    border: 1px solid var(--cds-border-strong-01, var(--md-gray-5));
    border-radius: 0;
    font-family: inherit;
    font-size: 16px; /* iOS zoom防止 */
    resize: vertical;
    box-sizing: border-box;
    -webkit-appearance: none;
    appearance: none;
}

.comment-form textarea:focus {
    outline: 2px solid var(--cds-orange-40);
    outline-offset: -2px;
    border-color: var(--cds-orange-40);
}

.no-plan {
    text-align: center;
    padding: var(--cds-spacing-07, var(--spacing-3xl));
    color: var(--cds-text-secondary, var(--text-secondary));
}

/* 編集ヘッダー - モバイル対応 */
.edit-header {
    display: flex;
    flex-direction: column;
    gap: var(--cds-spacing-04, var(--spacing-md));
    margin-bottom: var(--cds-spacing-05, var(--spacing-lg));
}

.edit-header h2 {
    color: var(--cds-text-primary, var(--text-primary));
    font-size: 18px;
    margin: 0;
}

.edit-header-buttons {
    display: flex;
    gap: var(--cds-spacing-03, var(--spacing-sm));
    width: 100%;
}

.edit-header-buttons .btn {
    flex: 1;
    min-height: 48px;
    font-size: 15px;
}

/* 表示ヘッダー - モバイル対応 */
.view-header {
    display: flex;
    flex-direction: column;
    gap: var(--cds-spacing-04, var(--spacing-md));
    margin-bottom: var(--cds-spacing-05, var(--spacing-lg));
}

.view-header h2 {
    color: var(--cds-text-primary, var(--text-primary));
    font-size: 18px;
    margin: 0;
}

.view-header .btn {
    width: 100%;
    min-height: 48px;
    font-size: 15px;
}

/* デスクトップ対応 */
@media (min-width: 768px) {
    .week-nav {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .week-nav-buttons {
        width: auto;
    }

    .week-nav-buttons .btn {
        flex: none;
        padding: 8px 16px;
    }

    .day-plan {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: var(--cds-spacing-04, var(--spacing-md));
        align-items: start;
        border-bottom: none;
        padding-bottom: 0;
    }

    .day-label {
        padding-top: var(--cds-spacing-04, var(--spacing-md));
    }

    .submission-view-item {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .comment-header {
        flex-direction: row;
        justify-content: space-between;
    }

    .edit-header,
    .view-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .edit-header-buttons {
        width: auto;
    }

    .edit-header-buttons .btn,
    .view-header .btn {
        flex: none;
        width: auto;
    }
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
                <div class="edit-header">
                    <h2>週間計画を編集</h2>
                    <div class="edit-header-buttons">
                        <a href="?date=<?= $targetDate ?>" class="btn btn-secondary">キャンセル</a>
                        <button type="submit" class="btn btn-success">保存する</button>
                    </div>
                </div>

                <div class="plan-section">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">target</span> 今週の目標</h3>
                    <textarea name="weekly_goal" rows="3" placeholder="今週達成したい目標を書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span> いっしょに決めた目標</h3>
                    <textarea name="shared_goal" rows="3" placeholder="先生や保護者と一緒に決めた目標を書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> やるべきこと</h3>
                    <textarea name="must_do" rows="3" placeholder="必ずやらなければならないことを書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">thumb_up</span> やったほうがいいこと</h3>
                    <textarea name="should_do" rows="3" placeholder="できればやった方がいいことを書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="plan-section">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">lightbulb</span> やりたいこと</h3>
                    <textarea name="want_to_do" rows="3" placeholder="やってみたいことを書きましょう"><?= $weeklyPlan ? htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                </div>

                <div class="daily-plans">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 各曜日の計画・目標</h3>
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
            <div class="view-header">
                <h2>週間計画</h2>
                <a href="?date=<?= $targetDate ?>&edit=1" class="btn btn-primary">編集する</a>
            </div>

            <div class="plan-section">
                <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">target</span> 今週の目標</h3>
                <div class="view-content <?= empty($weeklyPlan['weekly_goal']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['weekly_goal']) ? nl2br(htmlspecialchars($weeklyPlan['weekly_goal'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span> いっしょに決めた目標</h3>
                <div class="view-content <?= empty($weeklyPlan['shared_goal']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['shared_goal']) ? nl2br(htmlspecialchars($weeklyPlan['shared_goal'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> やるべきこと</h3>
                <div class="view-content <?= empty($weeklyPlan['must_do']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['must_do']) ? nl2br(htmlspecialchars($weeklyPlan['must_do'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">thumb_up</span> やったほうがいいこと</h3>
                <div class="view-content <?= empty($weeklyPlan['should_do']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['should_do']) ? nl2br(htmlspecialchars($weeklyPlan['should_do'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="plan-section">
                <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">lightbulb</span> やりたいこと</h3>
                <div class="view-content <?= empty($weeklyPlan['want_to_do']) ? 'empty' : '' ?>">
                    <?= !empty($weeklyPlan['want_to_do']) ? nl2br(htmlspecialchars($weeklyPlan['want_to_do'], ENT_QUOTES, 'UTF-8')) : '未記入' ?>
                </div>
            </div>

            <div class="daily-plans">
                <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 各曜日の計画・目標</h3>
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
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 提出物一覧</h3>
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
                                    <?= $sub['is_completed'] ? '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> ' : '' ?><?= htmlspecialchars($sub['submission_item'], ENT_QUOTES, 'UTF-8') ?>
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
            <h3 style="margin-bottom: var(--spacing-lg);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> コメント</h3>

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
