<?php
/**
 * 管理者用 - イベント管理ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['admin', 'staff']);

$pdo = getDbConnection();

// 検索パラメータを取得
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';
$searchTargetAudience = $_GET['target_audience'] ?? '';

// イベント一覧を取得（検索機能付き）
$sql = "
    SELECT
        e.id,
        e.event_date,
        e.event_name,
        e.event_description,
        e.event_color,
        e.target_audience,
        e.created_at,
        u.full_name as created_by_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE 1=1
";

$params = [];

if (!empty($searchKeyword)) {
    $sql .= " AND (e.event_name LIKE ? OR e.event_description LIKE ?)";
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
}

if (!empty($searchStartDate)) {
    $sql .= " AND e.event_date >= ?";
    $params[] = $searchStartDate;
}

if (!empty($searchEndDate)) {
    $sql .= " AND e.event_date <= ?";
    $params[] = $searchEndDate;
}

if (!empty($searchTargetAudience)) {
    $sql .= " AND e.target_audience = ?";
    $params[] = $searchTargetAudience;
}

$sql .= " ORDER BY e.event_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$targetAudienceLabels = [
    'all' => '全体',
    'elementary' => '小学生',
    'junior_high_school' => '中高生',
    'guardian' => '保護者',
    'other' => 'その他'
];

// ページ開始
$currentPage = 'events';
renderPageStart('admin', $currentPage, 'イベント管理');
?>

<style>
.event-color-badge {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    vertical-align: middle;
}
.color-preview {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.color-option {
    width: 30px;
    height: 30px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}
.color-option:hover {
    border-color: var(--text-primary);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">イベント管理</h1>
        <p class="page-subtitle">施設イベントの登録と管理</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        switch ($_GET['success']) {
            case 'created': echo 'イベントを登録しました。'; break;
            case 'updated': echo 'イベントを更新しました。'; break;
            case 'deleted': echo 'イベントを削除しました。'; break;
            default: echo '処理が完了しました。';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">エラー: <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<!-- 新規登録フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">新規イベント登録</h2>
        <form action="events_save.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">日付 *</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">イベント名 *</label>
                    <input type="text" name="event_name" class="form-control" required placeholder="例: 運動会、遠足、文化祭など">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">説明</label>
                <textarea name="event_description" class="form-control" placeholder="イベントの詳細説明（任意）"></textarea>
                <small style="color: var(--text-secondary);">イベントの詳細を記入してください（省略可）</small>
            </div>
            <div class="form-group">
                <label class="form-label">対象者 *</label>
                <select name="target_audience" class="form-control" required>
                    <option value="all">全体</option>
                    <option value="elementary">小学生</option>
                    <option value="junior_high_school">中高生向け</option>
                    <option value="guardian">保護者</option>
                    <option value="other">その他</option>
                </select>
                <small style="color: var(--text-secondary);">このイベントの対象者を選択してください</small>
            </div>
            <div class="form-group">
                <label class="form-label">カレンダー表示色</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="color" name="event_color" id="event_color" value="#28a745" style="width: 60px; height: 40px; border: none; cursor: pointer;">
                    <span style="color: var(--text-secondary);">選択した色でカレンダーに表示されます</span>
                </div>
                <div class="color-preview">
                    <div class="color-option" style="background: var(--apple-green);" onclick="document.getElementById('event_color').value='#28a745'"></div>
                    <div class="color-option" style="background: var(--apple-blue);" onclick="document.getElementById('event_color').value='#007bff'"></div>
                    <div class="color-option" style="background: var(--apple-orange);" onclick="document.getElementById('event_color').value='#ffc107'"></div>
                    <div class="color-option" style="background: var(--apple-red);" onclick="document.getElementById('event_color').value='#dc3545'"></div>
                    <div class="color-option" style="background: #17a2b8;" onclick="document.getElementById('event_color').value='#17a2b8'"></div>
                    <div class="color-option" style="background: #6f42c1;" onclick="document.getElementById('event_color').value='#6f42c1'"></div>
                </div>
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
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">イベント検索</h2>
        <form method="GET" action="events.php">
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
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">対象者</label>
                    <select name="target_audience" class="form-control">
                        <option value="">すべて</option>
                        <?php foreach ($targetAudienceLabels as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $searchTargetAudience === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">キーワード</label>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="イベント名または説明で検索">
                </div>
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="events.php" class="btn btn-secondary">クリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>
</div>

<!-- イベント一覧 -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">登録済みイベント一覧</h2>

        <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchTargetAudience)): ?>
            <div class="alert alert-info">
                <strong>検索結果:</strong> <?= count($events) ?>件のイベントが見つかりました
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>イベント名</th>
                    <th>対象者</th>
                    <th>説明</th>
                    <th>色</th>
                    <th>登録者</th>
                    <th>登録日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchTargetAudience)): ?>
                                検索条件に一致するイベントが見つかりませんでした
                            <?php else: ?>
                                登録されているイベントがありません
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($event['event_date']))] . '）', strtotime($event['event_date'])) ?></td>
                            <td><?= htmlspecialchars($event['event_name']) ?></td>
                            <td>
                                <span class="badge badge-info"><?= $targetAudienceLabels[$event['target_audience']] ?? '全体' ?></span>
                            </td>
                            <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($event['event_description'] ?: '---') ?>
                            </td>
                            <td>
                                <span class="event-color-badge" style="background: <?= htmlspecialchars($event['event_color']) ?>;"></span>
                            </td>
                            <td><?= htmlspecialchars($event['created_by_name'] ?? '-') ?></td>
                            <td><?= date('Y/m/d', strtotime($event['created_at'])) ?></td>
                            <td>
                                <form method="POST" action="events_save.php" style="display: inline;" onsubmit="return confirm('このイベントを削除しますか？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
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
