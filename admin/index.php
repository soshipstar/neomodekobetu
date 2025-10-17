<?php
/**
 * 管理者用トップページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();
checkUserType('admin');

$pdo = getDbConnection();

// 統計情報を取得
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'active_students' => 0,
    'total_records' => 0,
];

// ユーザー数
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$stats['total_users'] = $stmt->fetchColumn();

// 生徒数
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$stats['total_students'] = $stmt->fetchColumn();

// 有効な生徒数
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1");
$stats['active_students'] = $stmt->fetchColumn();

// 記録数
$stmt = $pdo->query("SELECT COUNT(*) FROM daily_records");
$stats['total_records'] = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ページ</title>
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
            max-width: 1200px;
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
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: normal;
        }
        .stat-card .number {
            color: #667eea;
            font-size: 36px;
            font-weight: bold;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .menu-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .menu-card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .menu-card h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #667eea;
        }
        .menu-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>⚙️ 管理者ページ</h1>
            </div>
            <div style="display: flex; align-items: center;">
                <span class="user-info">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>さん（管理者）
                </span>
                <a href="../logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>

        <!-- 統計情報 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>登録ユーザー数</h3>
                <div class="number"><?php echo $stats['total_users']; ?></div>
            </div>
            <div class="stat-card">
                <h3>登録生徒数</h3>
                <div class="number"><?php echo $stats['total_students']; ?></div>
            </div>
            <div class="stat-card">
                <h3>有効な生徒数</h3>
                <div class="number"><?php echo $stats['active_students']; ?></div>
            </div>
            <div class="stat-card">
                <h3>総記録数</h3>
                <div class="number"><?php echo $stats['total_records']; ?></div>
            </div>
        </div>

        <!-- メニュー -->
        <div class="menu-grid">
            <a href="students.php" class="menu-card">
                <div class="menu-card-icon">👥</div>
                <h3>生徒管理</h3>
                <p>生徒の登録・編集・削除を行います。学年や保護者の紐付け設定も可能です。</p>
            </a>

            <a href="guardians.php" class="menu-card">
                <div class="menu-card-icon">👤</div>
                <h3>保護者管理</h3>
                <p>保護者アカウントの登録・編集を行います。生徒との紐付け管理も可能です。</p>
            </a>

            <a href="users.php" class="menu-card">
                <div class="menu-card-icon">⚙️</div>
                <h3>スタッフ管理</h3>
                <p>職員アカウントを管理します。（準備中）</p>
            </a>

            <a href="reports.php" class="menu-card">
                <div class="menu-card-icon">📊</div>
                <h3>レポート</h3>
                <p>活動記録の統計やレポートを確認します。（準備中）</p>
            </a>

            <a href="settings.php" class="menu-card">
                <div class="menu-card-icon">⚙️</div>
                <h3>システム設定</h3>
                <p>システム全体の設定を管理します。（準備中）</p>
            </a>
        </div>
    </div>
</body>
</html>
