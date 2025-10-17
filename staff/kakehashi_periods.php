<?php
/**
 * かけはし期間管理ページ（スタッフ用）
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();

// 全ての生徒を取得
$stmt = $pdo->query("
    SELECT id, student_name, grade_level
    FROM students
    WHERE is_active = 1
    ORDER BY student_name
");
$students = $stmt->fetchAll();

// 全ての期間を取得（生徒情報も含む）
$stmt = $pdo->query("
    SELECT kp.*, s.student_name, s.grade_level
    FROM kakehashi_periods kp
    INNER JOIN students s ON kp.student_id = s.id
    ORDER BY kp.start_date DESC, kp.created_at DESC
");
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
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>かけはし期間管理 - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 30px;
        }

        .add-period-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .add-period-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group input {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-toggle {
            background: #ffc107;
            color: #856404;
        }

        .btn-toggle:hover {
            background: #e0a800;
        }

        .periods-list h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .period-card {
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .period-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .period-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
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
            color: #333;
            margin-bottom: 8px;
        }

        .period-dates {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .period-deadline {
            color: #dc3545;
            font-size: 14px;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-inactive {
            background: #6c757d;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e1e8ed;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-box p {
            color: #0d47a1;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗓️ かけはし期間管理</h1>
            <div class="nav-links">
                <a href="kakehashi_staff.php">✏️ かけはし入力</a>
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-warning">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- 仕組みの説明 -->
            <div class="info-box">
                <h3>📋 かけはし期間の仕組み</h3>
                <p>
                    • 生徒ごとに個別にかけはし期間を設定します<br>
                    • スタッフは設定した期間内にスタッフ用かけはしを入力できます<br>
                    • 保護者は、期間の開始日から1か月以内に保護者用かけはしを提出する必要があります<br>
                    • 期間を「無効」にすると、新規入力ができなくなります（既存データは保持されます）
                </p>
            </div>

            <!-- 新規期間追加フォーム -->
            <div class="add-period-section">
                <h2>➕ 新しい期間を作成</h2>
                <form method="POST" action="kakehashi_periods_save.php">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>対象生徒 *</label>
                            <select name="student_id" required>
                                <option value="">生徒を選択してください</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['id'] ?>">
                                        <?= htmlspecialchars($student['student_name']) ?>
                                        (<?= $student['grade_level'] === 'elementary' ? '小学部' : ($student['grade_level'] === 'junior_high' ? '中学部' : '高等部') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>期間名 *</label>
                            <input type="text" name="period_name" placeholder="例：2025年度前期" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>対象期間開始日 *</label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>対象期間終了日 *</label>
                            <input type="date" name="end_date" required>
                        </div>
                    </div>
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                        ※ 保護者の提出期限は、開始日から自動的に1か月後に設定されます
                    </p>
                    <button type="submit" class="btn btn-primary">📅 期間を作成</button>
                </form>
            </div>

            <!-- 期間一覧 -->
            <div class="periods-list">
                <h2>📊 登録済み期間一覧</h2>

                <?php if (empty($periods)): ?>
                    <div class="empty-state">
                        <p>まだ期間が登録されていません</p>
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
                                <form method="POST" action="kakehashi_periods_toggle.php" style="display: inline;">
                                    <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                    <button type="submit" class="btn btn-toggle">
                                        <?= $period['is_active'] ? '無効にする' : '有効にする' ?>
                                    </button>
                                </form>
                                <form method="POST" action="kakehashi_periods_delete.php" style="display: inline;" onsubmit="return confirm('この期間を削除しますか？\n※関連するかけはしデータも全て削除されます');">
                                    <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                    <button type="submit" class="btn btn-danger">削除</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
