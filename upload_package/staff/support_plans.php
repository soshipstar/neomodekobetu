<?php
/**
 * 支援案一覧ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 検索パラメータ
$searchTag = $_GET['tag'] ?? '';
$searchDayOfWeek = $_GET['day_of_week'] ?? '';
$searchKeyword = $_GET['keyword'] ?? '';

// 支援案一覧を取得（検索条件付き）
$where = [];
$params = [];

if ($classroomId) {
    $where[] = "sp.classroom_id = ?";
    $params[] = $classroomId;
}

if (!empty($searchTag)) {
    $where[] = "FIND_IN_SET(?, sp.tags) > 0";
    $params[] = $searchTag;
}

if (!empty($searchDayOfWeek)) {
    $where[] = "FIND_IN_SET(?, sp.day_of_week) > 0";
    $params[] = $searchDayOfWeek;
}

if (!empty($searchKeyword)) {
    $where[] = "(sp.activity_name LIKE ? OR sp.activity_content LIKE ? OR sp.activity_purpose LIKE ?)";
    $keywordParam = "%{$searchKeyword}%";
    $params[] = $keywordParam;
    $params[] = $keywordParam;
    $params[] = $keywordParam;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT sp.*, u.full_name as staff_name,
           COUNT(DISTINCT dr.id) as usage_count
    FROM support_plans sp
    INNER JOIN users u ON sp.staff_id = u.id
    LEFT JOIN daily_records dr ON sp.id = dr.support_plan_id
    {$whereClause}
    GROUP BY sp.id
    ORDER BY sp.activity_date DESC, sp.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$supportPlans = $stmt->fetchAll();

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];

    try {
        // 使用中かチェック
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM daily_records WHERE support_plan_id = ?
        ");
        $stmt->execute([$deleteId]);
        $usageCount = $stmt->fetchColumn();

        if ($usageCount > 0) {
            $_SESSION['error'] = 'この支援案は既に活動で使用されているため削除できません';
        } else {
            $stmt = $pdo->prepare("DELETE FROM support_plans WHERE id = ?");
            $stmt->execute([$deleteId]);
            $_SESSION['success'] = '支援案を削除しました';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = '削除に失敗しました: ' . $e->getMessage();
    }

    header('Location: support_plans.php');
    exit;
}

// ページ開始
$currentPage = 'support_plans';
renderPageStart('staff', $currentPage, '支援案一覧');
?>

<style>
.plan-card {
    background: var(--apple-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: 15px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    transition: transform var(--duration-fast) var(--ease-out);
}

.plan-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.plan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.plan-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.plan-meta {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.usage-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--apple-purple);
    color: white;
    border-radius: var(--radius-lg);
    font-size: var(--text-caption-1);
    font-weight: 600;
}

.plan-section {
    margin-bottom: var(--spacing-md);
}

.plan-section-title {
    font-weight: 600;
    color: var(--apple-purple);
    font-size: var(--text-footnote);
    margin-bottom: 4px;
}

.plan-section-content {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    white-space: pre-wrap;
}

.plan-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--apple-gray-5);
}

.search-box {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.search-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
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
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--apple-gray-5); }

@media (max-width: 768px) {
    .search-grid { grid-template-columns: 1fr; }
    .plan-actions { flex-direction: column; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">支援案一覧</h1>
        <div style="display: flex; gap: 10px; margin-top: var(--spacing-md);">
            <a href="support_plan_form.php" class="btn btn-success">+ 新しい支援案を作成</a>
        </div>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理へ</a>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- 検索フォーム -->
<div class="search-box">
    <h3 style="margin-bottom: 15px; color: var(--text-primary);">🔍 支援案を検索</h3>
    <form method="GET">
        <div class="search-grid">
            <div class="form-group">
                <label class="form-label">タグ</label>
                <select name="tag" class="form-control">
                    <option value="">すべて</option>
                    <?php
                    $tags = ['プログラミング', 'テキスタイル', 'CAD', '動画', 'イラスト', '企業支援', '農業', '音楽', '食', '学習', '自分取扱説明書', '心理', '言語', '教育', 'イベント', 'その他'];
                    foreach ($tags as $tag):
                    ?>
                        <option value="<?= htmlspecialchars($tag) ?>" <?= $searchTag === $tag ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tag) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">曜日</label>
                <select name="day_of_week" class="form-control">
                    <option value="">すべて</option>
                    <?php
                    $days = [
                        'monday' => '月曜日',
                        'tuesday' => '火曜日',
                        'wednesday' => '水曜日',
                        'thursday' => '木曜日',
                        'friday' => '金曜日',
                        'saturday' => '土曜日',
                        'sunday' => '日曜日'
                    ];
                    foreach ($days as $value => $label):
                    ?>
                        <option value="<?= $value ?>" <?= $searchDayOfWeek === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">キーワード</label>
                <input type="text" name="keyword" value="<?= htmlspecialchars($searchKeyword) ?>"
                    placeholder="活動名、内容、目的で検索" class="form-control">
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="support_plans.php" class="btn btn-secondary">クリア</a>
        </div>
    </form>
</div>

<?php if (empty($supportPlans)): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 60px 20px;">
            <h2 style="color: var(--text-secondary);">支援案が登録されていません</h2>
            <p style="color: var(--text-secondary);">「新しい支援案を作成」ボタンから支援案を作成してください。</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($supportPlans as $plan): ?>
        <div class="plan-card">
            <div class="plan-header">
                <div style="flex: 1;">
                    <div class="plan-title">
                        <?= htmlspecialchars($plan['activity_name']) ?>
                        <span style="font-size: var(--text-callout); color: var(--apple-purple); font-weight: normal; margin-left: 10px;">
                            📅 <?= date('Y年n月j日', strtotime($plan['activity_date'])) ?>
                        </span>
                    </div>
                    <div class="plan-meta">
                        <span>作成者: <?= htmlspecialchars($plan['staff_name']) ?></span>
                        <span>作成日: <?= date('Y年n月j日', strtotime($plan['created_at'])) ?></span>
                        <?php if ($plan['usage_count'] > 0): ?>
                            <span class="usage-badge">使用回数: <?= $plan['usage_count'] ?>回</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="plan-content">
                <?php if (!empty($plan['activity_purpose'])): ?>
                    <div class="plan-section">
                        <div class="plan-section-title">活動の目的</div>
                        <div class="plan-section-content"><?= htmlspecialchars($plan['activity_purpose']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($plan['activity_content'])): ?>
                    <div class="plan-section">
                        <div class="plan-section-title">活動の内容</div>
                        <div class="plan-section-content"><?= htmlspecialchars($plan['activity_content']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($plan['five_domains_consideration'])): ?>
                    <div class="plan-section">
                        <div class="plan-section-title">五領域への配慮</div>
                        <div class="plan-section-content"><?= htmlspecialchars($plan['five_domains_consideration']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($plan['other_notes'])): ?>
                    <div class="plan-section">
                        <div class="plan-section-title">その他</div>
                        <div class="plan-section-content"><?= htmlspecialchars($plan['other_notes']) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="plan-actions">
                <a href="support_plan_form.php?id=<?= $plan['id'] ?>" class="btn btn-sm btn-primary">編集</a>
                <form method="POST" style="display: inline;" onsubmit="return confirm('この支援案を削除しますか？<?= $plan['usage_count'] > 0 ? '\n\n注意: この支援案は' . $plan['usage_count'] . '回使用されています。' : '' ?>');">
                    <input type="hidden" name="delete_id" value="<?= $plan['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">削除</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php renderPageEnd(); ?>
