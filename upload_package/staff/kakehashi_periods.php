<?php
/**
 * かけはし期間管理ページ（スタッフ用）
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();

// スタッフの所属教室を取得
$staffClassroomId = $_SESSION['classroom_id'] ?? null;

// 教室に所属する生徒のみを取得（マスター管理者の場合は全教室）
if ($staffClassroomId) {
    $stmt = $pdo->prepare("
        SELECT id, student_name, grade_level
        FROM students
        WHERE is_active = 1 AND classroom_id = ?
        ORDER BY student_name
    ");
    $stmt->execute([$staffClassroomId]);
} else {
    $stmt = $pdo->query("
        SELECT id, student_name, grade_level
        FROM students
        WHERE is_active = 1
        ORDER BY student_name
    ");
}
$students = $stmt->fetchAll();

// 教室に所属する生徒の期間のみを取得（マスター管理者の場合は全教室）
if ($staffClassroomId) {
    $stmt = $pdo->prepare("
        SELECT kp.*, s.student_name, s.grade_level
        FROM kakehashi_periods kp
        INNER JOIN students s ON kp.student_id = s.id
        WHERE s.classroom_id = ?
        ORDER BY kp.start_date DESC, kp.created_at DESC
    ");
    $stmt->execute([$staffClassroomId]);
} else {
    $stmt = $pdo->query("
        SELECT kp.*, s.student_name, s.grade_level
        FROM kakehashi_periods kp
        INNER JOIN students s ON kp.student_id = s.id
        ORDER BY kp.start_date DESC, kp.created_at DESC
    ");
}
$periods = $stmt->fetchAll();

// 各期間の提出状況を取得
$periodStats = [];
foreach ($periods as $period) {
    // 保護者の提出状況
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted
        FROM kakehashi_guardian
        WHERE period_id = ?
    ");
    $stmt->execute([$period['id']]);
    $guardianStats = $stmt->fetch();

    // スタッフの提出状況
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted
        FROM kakehashi_staff
        WHERE period_id = ?
    ");
    $stmt->execute([$period['id']]);
    $staffStats = $stmt->fetch();

    $periodStats[$period['id']] = [
        'guardian' => $guardianStats,
        'staff' => $staffStats
    ];
}

// ページ開始
$currentPage = 'kakehashi_periods';
renderPageStart('staff', $currentPage, 'かけはし期間管理');
?>

<style>
.period-card {
    border: 2px solid var(--apple-gray-5);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
    transition: all var(--duration-normal) var(--ease-out);
    background: var(--apple-bg-primary);
}

.period-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.period-card.inactive {
    opacity: 0.6;
    background: var(--apple-gray-6);
}

.period-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.period-name {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.period-dates {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    margin-bottom: 5px;
}

.period-deadline {
    color: var(--apple-red);
    font-size: var(--text-subhead);
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-subhead);
    font-weight: 600;
}

.status-active { background: var(--apple-green); color: white; }
.status-inactive { background: var(--apple-gray); color: white; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--apple-gray-5);
}

.stat-box {
    background: var(--apple-gray-6);
    padding: 15px;
    border-radius: var(--radius-sm);
}

.stat-label {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.stat-value {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.info-box {
    background: rgba(0, 122, 255, 0.1);
    border-left: 4px solid var(--apple-blue);
    padding: 15px;
    margin-bottom: var(--spacing-lg);
    border-radius: var(--radius-sm);
}

.info-box h3 {
    color: var(--apple-blue);
    margin-bottom: var(--spacing-md);
    font-size: var(--text-callout);
}

.info-box p {
    color: var(--text-primary);
    line-height: 1.6;
}

.rules-box {
    background: rgba(52, 199, 89, 0.1);
    border-left: 4px solid var(--apple-green);
    padding: 15px;
    margin-bottom: var(--spacing-lg);
    border-radius: var(--radius-sm);
}

.rules-box h2 {
    color: var(--apple-green);
    margin-bottom: var(--spacing-md);
    font-size: var(--text-headline);
}

.rules-table {
    width: 100%;
    margin-top: 15px;
    border-collapse: collapse;
}

.rules-table th, .rules-table td {
    padding: 8px;
    text-align: left;
    border: 1px solid var(--apple-gray-5);
}

.rules-table th {
    background: rgba(52, 199, 89, 0.2);
}

.quick-links {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
}
.quick-link:hover { background: var(--apple-gray-5); }

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .action-buttons { flex-direction: column; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">かけはし期間管理</h1>
        <p class="page-subtitle">かけはし期間の状態を確認・管理します</p>
    </div>
</div>

<!-- クイックリンク -->
<div class="quick-links">
    <a href="kakehashi_staff.php" class="quick-link">✏️ かけはし入力</a>
    <a href="renrakucho_activities.php" class="quick-link">← 戻る</a>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- 仕組みの説明 -->
<div class="info-box">
    <h3>📋 かけはし期間の仕組み</h3>
    <p>
        • かけはし期間は生徒の支援開始日を基準に<strong>自動計算</strong>されます<br>
        • 対象期間: 6ヶ月間（支援開始日から順に設定）<br>
        • 提出期限: 初回は支援開始日の1日前、2回目以降は対象期間開始日の1ヶ月前<br>
        • 期間は提出期限の1ヶ月前になると自動的に生成されます<br>
        • <strong>※日付の変更はシステムの整合性を保つため手動ではできません</strong>
    </p>
</div>

<!-- 日付計算ルール表示 -->
<div class="rules-box">
    <h2>📐 日付計算ルール（自動適用）</h2>
    <table class="rules-table">
        <tr>
            <th>項目</th>
            <th>初回</th>
            <th>2回目以降</th>
        </tr>
        <tr>
            <td>対象期間開始日</td>
            <td>支援開始日</td>
            <td>前回終了日の翌日</td>
        </tr>
        <tr>
            <td>対象期間終了日</td>
            <td>開始日から6ヶ月後の前日</td>
            <td>開始日から6ヶ月後の前日</td>
        </tr>
        <tr>
            <td>提出期限</td>
            <td>支援開始日の1日前</td>
            <td>開始日の1ヶ月前</td>
        </tr>
    </table>
</div>

<!-- 期間一覧 -->
<h2 style="margin-bottom: var(--spacing-lg);">📊 登録済み期間一覧</h2>

<?php if (empty($periods)): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 60px 20px;">
            <p style="color: var(--text-secondary);">まだ期間が登録されていません</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($periods as $period): ?>
        <div class="period-card <?= $period['is_active'] ? '' : 'inactive' ?>">
            <div class="period-header">
                <div>
                    <div class="period-name">
                        👤 <?= htmlspecialchars($period['student_name']) ?> - <?= htmlspecialchars($period['period_name']) ?>
                        <span class="status-badge <?= $period['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $period['is_active'] ? '有効' : '無効' ?>
                        </span>
                    </div>
                    <div class="period-dates">
                        対象期間: <?= date('Y年m月d日', strtotime($period['start_date'])) ?> ～ <?= date('Y年m月d日', strtotime($period['end_date'])) ?>
                    </div>
                    <div class="period-deadline">
                        保護者提出期限: <?= date('Y年m月d日', strtotime($period['submission_deadline'])) ?>
                    </div>
                </div>
            </div>

            <!-- 提出状況 -->
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">スタッフ提出状況</div>
                    <div class="stat-value">
                        <?= ($periodStats[$period['id']]['staff']['submitted'] ?? 0) > 0 ? '✅ 提出済み' : '未提出' ?>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">保護者提出状況</div>
                    <div class="stat-value">
                        <?= ($periodStats[$period['id']]['guardian']['submitted'] ?? 0) > 0 ? '✅ 提出済み' : '未提出' ?>
                    </div>
                </div>
            </div>

            <!-- アクション -->
            <div class="action-buttons">
                <a href="pending_tasks.php?student_id=<?= $period['student_id'] ?>" class="btn btn-primary btn-sm">詳細確認</a>
                <form method="POST" action="kakehashi_periods_toggle.php" style="display: inline;">
                    <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <?= $period['is_active'] ? '無効にする' : '有効にする' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php renderPageEnd(); ?>
