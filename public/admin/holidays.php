<?php
/**
 * 管理者用 - 休日管理ページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['admin', 'staff']);

$pdo = getDbConnection();

// 検索パラメータを取得
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';

// 休日一覧を取得（検索機能付き）
$sql = "
    SELECT
        h.id,
        h.holiday_date,
        h.holiday_name,
        h.holiday_type,
        h.created_at,
        u.full_name as created_by_name
    FROM holidays h
    LEFT JOIN users u ON h.created_by = u.id
    WHERE 1=1
";

$params = [];

if (!empty($searchKeyword)) {
    $sql .= " AND h.holiday_name LIKE ?";
    $params[] = '%' . $searchKeyword . '%';
}

if (!empty($searchStartDate)) {
    $sql .= " AND h.holiday_date >= ?";
    $params[] = $searchStartDate;
}

if (!empty($searchEndDate)) {
    $sql .= " AND h.holiday_date <= ?";
    $params[] = $searchEndDate;
}

$sql .= " ORDER BY h.holiday_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$holidays = $stmt->fetchAll();

// ページ開始
$currentPage = 'holidays';
renderPageStart('admin', $currentPage, '休日管理');
?>

<style>
.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-md);
    font-size: var(--text-caption-1);
    font-weight: bold;
}
.type-regular {
    background: rgba(0, 122, 255, 0.15);
    color: var(--apple-blue);
}
.type-special {
    background: rgba(255, 149, 0, 0.15);
    color: var(--apple-orange);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">休日管理</h1>
        <p class="page-subtitle">休日・祝日の登録と管理</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        switch ($_GET['success']) {
            case 'created':
                if (isset($_GET['count'])) {
                    echo '定期休日として' . (int)$_GET['count'] . '件の休日を登録しました。';
                } else {
                    echo '休日を登録しました。';
                }
                break;
            case 'deleted':
                echo '休日を削除しました。';
                break;
            default:
                echo '処理が完了しました。';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        エラー: <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<!-- 新規登録フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">新規休日登録</h2>
        <form action="holidays_save.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">日付 *</label>
                    <input type="date" name="holiday_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">休日タイプ *</label>
                    <select name="holiday_type" class="form-control" required>
                        <option value="regular">定期休日（毎週の休み）</option>
                        <option value="special">特別休日（イベント・祝日など）</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">休日名 *</label>
                <input type="text" name="holiday_name" class="form-control" required placeholder="例: 夏季休業、年末年始、祝日名など">
                <small style="color: var(--text-secondary);">カレンダーに表示される名前です</small>
            </div>
            <div style="text-align: right;">
                <button type="submit" class="btn btn-success">登録する</button>
            </div>
        </form>
    </div>
</div>

<!-- 検索フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">休日検索</h2>
        <form method="GET" action="holidays.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">期間（開始日）</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($searchStartDate) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">期間（終了日）</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($searchEndDate) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">キーワード</label>
                <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="休日名で検索">
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="holidays.php" class="btn btn-secondary">クリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>
</div>

<!-- 休日一覧 -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">登録済み休日一覧</h2>

        <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate)): ?>
            <div class="alert alert-info">
                <strong>検索結果:</strong> <?= count($holidays) ?>件の休日が見つかりました
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>休日名</th>
                    <th>タイプ</th>
                    <th>登録者</th>
                    <th>登録日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($holidays)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate)): ?>
                                検索条件に一致する休日が見つかりませんでした
                            <?php else: ?>
                                登録されている休日がありません
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($holidays as $holiday): ?>
                        <tr>
                            <td><?= date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($holiday['holiday_date']))] . '）', strtotime($holiday['holiday_date'])) ?></td>
                            <td><?= htmlspecialchars($holiday['holiday_name']) ?></td>
                            <td>
                                <span class="type-badge <?= $holiday['holiday_type'] === 'regular' ? 'type-regular' : 'type-special' ?>">
                                    <?= $holiday['holiday_type'] === 'regular' ? '定期休日' : '特別休日' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($holiday['created_by_name'] ?? '-') ?></td>
                            <td><?= date('Y/m/d', strtotime($holiday['created_at'])) ?></td>
                            <td>
                                <form method="POST" action="holidays_save.php" style="display: inline;" onsubmit="return confirm('この休日を削除しますか？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="holiday_id" value="<?= $holiday['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPageEnd(); ?>
