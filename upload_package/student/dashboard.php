<?php
/**
 * 生徒用ダッシュボード
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 提出物の未提出数を取得（保護者チャット経由）
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.is_completed = 0 AND sr.due_date >= CURDATE()
");
$stmt->execute([$studentId]);
$pendingSubmissions = $stmt->fetchColumn();

// すべての提出物を取得（統合）
$allSubmissions = [];

// 1. 週間計画表の提出物
$stmt = $pdo->prepare("
    SELECT
        wps.id,
        wps.submission_item as title,
        wps.due_date,
        wps.is_completed,
        DATEDIFF(wps.due_date, CURDATE()) as days_until_due
    FROM weekly_plan_submissions wps
    INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
    WHERE wp.student_id = ? AND wps.is_completed = 0
    ORDER BY wps.due_date ASC
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 2. 保護者チャット経由の提出物
$stmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.title,
        sr.due_date,
        sr.is_completed,
        DATEDIFF(sr.due_date, CURDATE()) as days_until_due
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.is_completed = 0
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 3. 生徒自身が登録した提出物
$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        due_date,
        is_completed,
        DATEDIFF(due_date, CURDATE()) as days_until_due
    FROM student_submissions
    WHERE student_id = ? AND is_completed = 0
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 提出物をカテゴリ分け（1週間以内のもののみ表示）
$overdueSubmissions = [];
$urgentSubmissions = [];
$normalSubmissions = [];

foreach ($allSubmissions as $sub) {
    if ($sub['days_until_due'] < 0) {
        $overdueSubmissions[] = $sub;
    } elseif ($sub['days_until_due'] <= 3) {
        $urgentSubmissions[] = $sub;
    } elseif ($sub['days_until_due'] <= 7) {
        $normalSubmissions[] = $sub;
    }
}

// 新着メッセージ数を取得（生徒用チャット）
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM student_chat_rooms scr
    LEFT JOIN student_chat_messages scm ON scr.id = scm.room_id
    WHERE scr.student_id = ? AND scm.sender_type = 'staff'
    AND (scm.is_read_by_student = 0 OR scm.is_read_by_student IS NULL)
");
$stmt->execute([$studentId]);
$newMessages = $stmt->fetchColumn();

// セッション設定
$_SESSION['user_type'] = 'student';
$_SESSION['full_name'] = $student['student_name'];

// ページ開始
$currentPage = 'dashboard';
renderPageStart('student', $currentPage, 'マイページ');
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">マイページ</h1>
        <p class="page-subtitle">ようこそ、<?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?>さん</p>
    </div>
</div>

<!-- メニューグリッド -->
<div class="menu-grid">
    <a href="schedule.php" class="menu-card">
        <div class="menu-card-icon">📅</div>
        <h3>スケジュール</h3>
        <p>出席日、イベント、休日を確認</p>
    </a>

    <a href="chat.php" class="menu-card" style="position: relative;">
        <?php if ($newMessages > 0): ?>
            <span class="notification-badge" style="position: absolute; top: 15px; right: 15px;"><?= $newMessages ?></span>
        <?php endif; ?>
        <div class="menu-card-icon">💬</div>
        <h3>チャット</h3>
        <p>スタッフとメッセージをやり取り</p>
    </a>

    <a href="weekly_plan.php" class="menu-card">
        <div class="menu-card-icon">📝</div>
        <h3>週間計画表</h3>
        <p>今週の計画を立てる・確認する</p>
    </a>

    <a href="submissions.php" class="menu-card" style="position: relative;">
        <?php if ($pendingSubmissions > 0): ?>
            <span class="notification-badge" style="position: absolute; top: 15px; right: 15px;"><?= $pendingSubmissions ?></span>
        <?php endif; ?>
        <div class="menu-card-icon">📤</div>
        <h3>提出物</h3>
        <p>提出物の確認と管理</p>
    </a>

    <a href="change_password.php" class="menu-card">
        <div class="menu-card-icon">🔐</div>
        <h3>パスワード変更</h3>
        <p>ログインパスワードを変更する</p>
    </a>
</div>

<!-- 提出物アラート -->
<?php if (!empty($overdueSubmissions) || !empty($urgentSubmissions) || !empty($normalSubmissions)): ?>
<div style="margin-top: var(--spacing-xl);">
    <!-- 期限超過 -->
    <?php foreach ($overdueSubmissions as $sub): ?>
        <div class="alert alert-danger" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
            <div>
                <strong>⚠️ 【期限超過】<?= htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 4px;">
                    期限: <?= date('Y年m月d日', strtotime($sub['due_date'])) ?>（<?= abs($sub['days_until_due']) ?>日超過）
                </div>
            </div>
            <a href="weekly_plan.php" class="btn btn-sm btn-danger">確認する</a>
        </div>
    <?php endforeach; ?>

    <!-- 緊急（3日以内） -->
    <?php foreach ($urgentSubmissions as $sub): ?>
        <div class="alert alert-warning" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
            <div>
                <strong>🔥 【緊急】<?= htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 4px;">
                    期限: <?= date('Y年m月d日', strtotime($sub['due_date'])) ?>
                    <?php if ($sub['days_until_due'] == 0): ?>
                        （今日が期限）
                    <?php else: ?>
                        （あと<?= $sub['days_until_due'] ?>日）
                    <?php endif; ?>
                </div>
            </div>
            <a href="weekly_plan.php" class="btn btn-sm btn-warning">確認する</a>
        </div>
    <?php endforeach; ?>

    <!-- 1週間以内 -->
    <?php foreach ($normalSubmissions as $sub): ?>
        <div class="alert alert-info" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
            <div>
                <strong>📋 <?= htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 4px;">
                    提出期限まであと<?= $sub['days_until_due'] ?>日です（<?= date('Y年m月d日', strtotime($sub['due_date'])) ?>）
                </div>
            </div>
            <a href="submissions.php" class="btn btn-sm btn-info">確認する</a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php renderPageEnd(); ?>
