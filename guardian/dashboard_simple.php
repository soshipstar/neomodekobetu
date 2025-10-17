<?php
/**
 * 保護者用トップページ（シンプル版）
 * デバッグ用
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ファイル読み込み（出力前に実行）
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// 保護者チェック（直接チェック）
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
    exit;
}

$guardianId = $_SESSION['user_id'];
$guardianName = $_SESSION['full_name'];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保護者ページ（テスト版）</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .success {
            color: green;
        }
        .logout-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1 class="success">✓ ページは正常に動作しています</h1>
        <p><strong>保護者名:</strong> <?php echo htmlspecialchars($guardianName); ?></p>
        <p><strong>ユーザーID:</strong> <?php echo htmlspecialchars($guardianId); ?></p>
    </div>

    <div class="box">
        <h2>データベーステスト</h2>
        <?php
        try {
            $pdo = getDbConnection();
            echo "<p class='success'>✓ データベース接続成功</p>";

            // studentsテーブルの確認
            $stmt = $pdo->query("SHOW TABLES LIKE 'students'");
            if ($stmt->rowCount() > 0) {
                echo "<p class='success'>✓ studentsテーブル存在</p>";

                // grade_levelカラムの確認
                $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'grade_level'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'>✓ grade_levelカラム存在</p>";
                } else {
                    echo "<p style='color: orange;'>⚠ grade_levelカラムなし（migration_v2.sql未実行）</p>";
                }
            } else {
                echo "<p style='color: red;'>✗ studentsテーブルなし</p>";
            }

            // integrated_notesテーブルの確認
            $stmt = $pdo->query("SHOW TABLES LIKE 'integrated_notes'");
            if ($stmt->rowCount() > 0) {
                echo "<p class='success'>✓ integrated_notesテーブル存在</p>";
            } else {
                echo "<p style='color: orange;'>⚠ integrated_notesテーブルなし（migration_v3.sql未実行）</p>";
            }

            // 保護者に紐づく生徒を取得
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE guardian_id = ?");
            $stmt->execute([$guardianId]);
            $studentCount = $stmt->fetchColumn();
            echo "<p><strong>紐づいている生徒数:</strong> {$studentCount}名</p>";

        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="box">
        <a href="/logout.php" class="logout-btn">ログアウト</a>
        <a href="/emergency_logout.php" class="logout-btn" style="background: #ff9800; margin-left: 10px;">緊急ログアウト</a>
    </div>
</body>
</html>
