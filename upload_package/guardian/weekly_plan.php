<?php
/**
 * 保護者用 - 週間計画表
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$currentUser['id']]);
$classroom = $stmt->fetch();

// 生徒一覧を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? ORDER BY student_name");
$stmt->execute([$currentUser['id']]);
$students = $stmt->fetchAll();

// デフォルトで最初の生徒を選択
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);
$targetDate = $_GET['date'] ?? date('Y-m-d');

if (!$selectedStudentId) {
    $student = null;
    $weeklyPlan = null;
    $planData = [];
    $comments = [];
    $weekStartDate = date('Y-m-d');
    $prevWeek = '';
    $nextWeek = '';
} else {
    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE id = ? AND guardian_id = ?");
    $stmt->execute([$selectedStudentId, $currentUser['id']]);
    $student = $stmt->fetch();

    if (!$student) {
        header('Location: dashboard.php');
        exit;
    }

    // 週の開始日を計算
    $timestamp = strtotime($targetDate);
    $dayOfWeek = date('w', $timestamp);
    $daysFromMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
    $weekStartDate = date('Y-m-d', strtotime("-$daysFromMonday days", $timestamp));

    // 週間計画を取得
    $stmt = $pdo->prepare("
        SELECT id, plan_data, created_at, updated_at
        FROM weekly_plans
        WHERE student_id = ? AND week_start_date = ?
    ");
    $stmt->execute([$selectedStudentId, $weekStartDate]);
    $weeklyPlan = $stmt->fetch();

    $planData = $weeklyPlan ? json_decode($weeklyPlan['plan_data'], true) : [];

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
}

// ページ開始
$currentPage = 'weekly_plan';
renderPageStart('guardian', $currentPage, '週間計画表', ['classroom' => $classroom]);
?>

<style>
.student-selector {
    background: var(--apple-bg-primary);
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
}

.student-selector select {
    width: 100%;
    padding: var(--spacing-md);
    border: 1px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    background: var(--apple-bg-primary);
}

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

.plan-table {
    width: 100%;
    border-collapse: collapse;
}

.plan-table th {
    background: var(--apple-gray-6);
    padding: var(--spacing-md);
    text-align: left;
    border: 1px solid var(--apple-gray-5);
    font-weight: 600;
    color: var(--text-primary);
}

.plan-table td {
    padding: var(--spacing-md);
    border: 1px solid var(--apple-gray-5);
    vertical-align: top;
}

.day-header {
    font-weight: 600;
    color: var(--apple-purple);
    margin-bottom: 5px;
}

.plan-content {
    color: var(--text-primary);
    line-height: 1.6;
    white-space: pre-wrap;
}

.empty-plan {
    color: var(--text-secondary);
    font-style: italic;
}

.comments-section {
    margin-top: var(--spacing-lg);
}

.comment {
    padding: var(--spacing-md);
    background: var(--apple-gray-6);
    border-left: 4px solid var(--apple-purple);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
}

.comment.staff { border-left-color: var(--apple-green); }
.comment.student { border-left-color: var(--apple-purple); }
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

@media (max-width: 768px) {
    .plan-table {
        font-size: var(--text-footnote);
        display: block;
        overflow-x: auto;
    }
    .plan-table th, .plan-table td {
        padding: var(--spacing-sm);
        min-width: 60px;
    }
    .week-nav {
        flex-direction: column;
        gap: var(--spacing-md);
        text-align: center;
    }
    .comment-form textarea {
        font-size: 16px;
    }
    .student-selector select {
        font-size: 16px;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">週間計画表</h1>
        <p class="page-subtitle">お子さまの週間計画を確認できます</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">コメントを投稿しました</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (count($students) > 1): ?>
    <div class="student-selector">
        <select onchange="location.href='?student_id=' + this.value">
            <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $selectedStudentId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['student_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>

<?php if ($student): ?>
    <div class="week-nav">
        <h2><?= date('Y年m月d日', strtotime($weekStartDate)) ?>の週</h2>
        <div class="week-nav-buttons">
            <a href="?student_id=<?= $selectedStudentId ?>&date=<?= $prevWeek ?>" class="btn btn-secondary btn-sm">← 前週</a>
            <a href="?student_id=<?= $selectedStudentId ?>&date=<?= date('Y-m-d') ?>" class="btn btn-primary btn-sm">今週</a>
            <a href="?student_id=<?= $selectedStudentId ?>&date=<?= $nextWeek ?>" class="btn btn-secondary btn-sm">次週 →</a>
        </div>
    </div>

    <?php if (!$weeklyPlan): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: var(--spacing-3xl);">
                <p style="color: var(--text-secondary);">この週の計画はまだ作成されていません</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="plan-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">曜日</th>
                            <th>計画・目標</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                        foreach ($days as $index => $day):
                            $dayKey = "day_$index";
                            $date = date('m/d', strtotime("+$index days", strtotime($weekStartDate)));
                            $content = $planData[$dayKey] ?? '';
                        ?>
                            <tr>
                                <td>
                                    <div class="day-header"><?= $day ?></div>
                                    <div style="font-size: var(--text-caption-1); color: var(--text-secondary);"><?= $date ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($content)): ?>
                                        <div class="plan-content"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        <div class="empty-plan">計画なし</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card comments-section">
            <div class="card-body">
                <h3 style="margin-bottom: var(--spacing-lg);">💬 コメント</h3>

                <?php if (empty($comments)): ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-lg);">まだコメントはありません</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment <?= $comment['commenter_type'] ?>">
                            <div class="comment-header">
                                <span class="comment-author">
                                    <?php
                                    $icon = $comment['commenter_type'] === 'staff' ? '👨‍🏫' :
                                            ($comment['commenter_type'] === 'guardian' ? '👪' : '🎓');
                                    echo $icon . ' ' . htmlspecialchars($comment['commenter_name'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                </span>
                                <span class="comment-date"><?= date('Y/m/d H:i', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <div class="comment-body">
                                <?= nl2br(htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="comment-form" style="margin-top: var(--spacing-lg);">
                    <form method="POST" action="add_guardian_plan_comment.php">
                        <input type="hidden" name="weekly_plan_id" value="<?= $weeklyPlan['id'] ?>">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <input type="hidden" name="week_start_date" value="<?= $weekStartDate ?>">
                        <textarea name="comment" placeholder="コメントを入力..." required></textarea>
                        <button type="submit" class="btn btn-primary" style="margin-top: var(--spacing-sm);">コメントを投稿</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: var(--spacing-3xl);">
            <p style="color: var(--text-secondary);">生徒が登録されていません</p>
        </div>
    </div>
<?php endif; ?>

<?php renderPageEnd(); ?>
