<?php
/**
 * スタッフ用 - 休日管理ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>休日管理 - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        table tr:hover {
            background: #f8f9fa;
        }
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .type-regular {
            background: #e3f2fd;
            color: #1565c0;
        }
        .type-special {
            background: #fff3e0;
            color: #e65100;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗓️ 休日管理</h1>
            <div class="header-actions">
                <span class="user-info"><?php echo htmlspecialchars($_SESSION['full_name']); ?>（<?php echo $_SESSION['user_type'] === 'admin' ? '管理者' : 'スタッフ'; ?>）</span>
                <a href="renrakucho_activities.php" class="btn btn-secondary btn-sm">活動管理に戻る</a>
                <a href="/logout.php" class="btn btn-danger btn-sm">ログアウト</a>
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
            <div class="alert alert-error">
                エラー: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- 新規登録フォーム -->
        <div class="content-box">
            <h2 class="section-title">新規休日登録</h2>
            <form action="holidays_save.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>日付 *</label>
                        <input type="date" name="holiday_date" required>
                    </div>
                    <div class="form-group">
                        <label>休日タイプ *</label>
                        <select name="holiday_type" required>
                            <option value="regular">定期休日（毎週の休み）</option>
                            <option value="special">特別休日（イベント・祝日など）</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>休日名 *</label>
                    <input type="text" name="holiday_name" required placeholder="例: 夏季休業、年末年始、祝日名など">
                    <div class="help-text">カレンダーに表示される名前です</div>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-success">登録する</button>
                </div>
            </form>
        </div>

        <!-- 検索フォーム -->
        <div class="content-box">
            <h2 class="section-title">🔍 休日検索</h2>
            <form method="GET" action="holidays.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>期間（開始日）</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($searchStartDate); ?>">
                    </div>
                    <div class="form-group">
                        <label>期間（終了日）</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($searchEndDate); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>キーワード</label>
                    <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="休日名で検索">
                </div>
                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="holidays.php" class="btn btn-secondary">クリア</a>
                    <button type="submit" class="btn btn-primary">検索</button>
                </div>
            </form>
        </div>

        <!-- 休日一覧 -->
        <div class="content-box">
            <h2 class="section-title">登録済み休日一覧</h2>
            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate)): ?>
                <div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3; color: #1976D2;">
                    <strong>検索結果:</strong> <?php echo count($holidays); ?>件の休日が見つかりました
                </div>
            <?php endif; ?>
            <table>
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
                            <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
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
                                <td><?php echo date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($holiday['holiday_date']))] . '）', strtotime($holiday['holiday_date'])); ?></td>
                                <td><?php echo htmlspecialchars($holiday['holiday_name']); ?></td>
                                <td>
                                    <span class="type-badge <?php echo $holiday['holiday_type'] === 'regular' ? 'type-regular' : 'type-special'; ?>">
                                        <?php echo $holiday['holiday_type'] === 'regular' ? '定期休日' : '特別休日'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($holiday['created_by_name']); ?></td>
                                <td><?php echo date('Y/m/d', strtotime($holiday['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="holidays_save.php" style="display: inline;" onsubmit="return confirm('この休日を削除しますか？');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="holiday_id" value="<?php echo $holiday['id']; ?>">
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
</body>
</html>
