<?php
/**
 * スタッフ用 - 休日管理ページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

$pdo = getDbConnection();

// 教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 検索パラメータを取得
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';

// 休日一覧を取得（検索機能付き、教室でフィルタリング）
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
    WHERE h.classroom_id = ?
";

$params = [$classroomId];

// キーワード検索
if (!empty($searchKeyword)) {
    $sql .= " AND h.holiday_name LIKE ?";
    $params[] = '%' . $searchKeyword . '%';
}

// 期間検索（開始日）
if (!empty($searchStartDate)) {
    $sql .= " AND h.holiday_date >= ?";
    $params[] = $searchStartDate;
}

// 期間検索（終了日）
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
renderPageStart('staff', $currentPage, '休日管理');
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
    color: var(--md-blue);
}
.type-special {
    background: rgba(255, 149, 0, 0.15);
    color: var(--md-orange);
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
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--md-blue);">新規休日登録</h2>

        <!-- タブ切り替え -->
        <div class="holiday-tabs" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <button type="button" class="tab-btn active" data-tab="regular" style="flex: 1; padding: 12px; border: 2px solid var(--md-blue); background: var(--md-blue); color: white; border-radius: 8px; cursor: pointer; font-weight: 600;">
                定期休日（毎週の休み）
            </button>
            <button type="button" class="tab-btn" data-tab="special" style="flex: 1; padding: 12px; border: 2px solid var(--cds-border-subtle-00); background: white; color: var(--cds-text-primary); border-radius: 0; cursor: pointer; font-weight: 600;">
                特別休日（祝日・イベント等）
            </button>
        </div>

        <!-- 定期休日フォーム -->
        <div id="regularForm" class="holiday-form">
            <form action="holidays_save.php" method="POST">
                <input type="hidden" name="action" value="create_regular">

                <div class="form-group">
                    <label class="form-label">休日名 *</label>
                    <input type="text" name="holiday_name" class="form-control" required placeholder="例: 定休日、日曜休み">
                    <small style="color: var(--text-secondary);">カレンダーに表示される名前です</small>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">休業日の曜日 *</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                        <?php
                        $dayNames = ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'];
                        $dayColors = ['var(--cds-support-error)', 'var(--cds-text-primary)', 'var(--cds-text-primary)', 'var(--cds-text-primary)', 'var(--cds-text-primary)', 'var(--cds-text-primary)', 'var(--cds-blue-60)'];
                        foreach ($dayNames as $i => $dayName):
                        ?>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; border: 2px solid var(--cds-border-subtle-00); border-radius: 0; cursor: pointer; transition: all 0.2s;">
                            <input type="checkbox" name="days_of_week[]" value="<?= $i ?>" style="width: 18px; height: 18px; cursor: pointer;">
                            <span style="color: <?= $dayColors[$i] ?>; font-weight: 500;"><?= $dayName ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: var(--text-secondary); display: block; margin-top: 8px;">選択した曜日が年度末（3月末）まで登録されます</small>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">登録開始日</label>
                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    <small style="color: var(--text-secondary);">この日以降の該当曜日が登録されます（デフォルト: 今日）</small>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">定期休日を登録</button>
                </div>
            </form>
        </div>

        <!-- 特別休日フォーム -->
        <div id="specialForm" class="holiday-form" style="display: none;">
            <form action="holidays_save.php" method="POST">
                <input type="hidden" name="action" value="create_special">

                <div class="form-group">
                    <label class="form-label">日付 *</label>
                    <input type="date" name="holiday_date" class="form-control" required>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">休日名 *</label>
                    <input type="text" name="holiday_name" class="form-control" required placeholder="例: 祝日、夏季休業、年末年始">
                    <small style="color: var(--text-secondary);">カレンダーに表示される名前です</small>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">特別休日を登録</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// タブ切り替え
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // タブボタンのスタイル切り替え
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.style.background = 'white';
            b.style.color = 'var(--cds-text-primary)';
            b.style.borderColor = 'var(--cds-border-subtle-00)';
            b.classList.remove('active');
        });
        btn.style.background = 'var(--md-blue)';
        btn.style.color = 'white';
        btn.style.borderColor = 'var(--md-blue)';
        btn.classList.add('active');

        // フォーム切り替え
        const tab = btn.dataset.tab;
        document.querySelectorAll('.holiday-form').forEach(form => {
            form.style.display = 'none';
        });
        document.getElementById(tab + 'Form').style.display = 'block';
    });
});

// チェックボックスの選択時にスタイル変更
document.querySelectorAll('input[name="days_of_week[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const label = this.closest('label');
        if (this.checked) {
            label.style.background = 'rgba(31, 98, 254, 0.15)';
            label.style.borderColor = 'var(--md-blue)';
        } else {
            label.style.background = 'white';
            label.style.borderColor = 'var(--cds-border-subtle-00)';
        }
    });
});
</script>

<!-- 検索フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--md-blue);">休日検索</h2>
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
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--md-blue);">登録済み休日一覧</h2>

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
