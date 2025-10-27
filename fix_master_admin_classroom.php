<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マスター管理者修正</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .result {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="result">
        <h1>🔧 マスター管理者修正スクリプト</h1>
        <?php
        require_once __DIR__ . '/config/database.php';

        $pdo = getDbConnection();

        try {
            // マスター管理者のclassroom_idをNULLに更新
            $stmt = $pdo->prepare("
                UPDATE users
                SET classroom_id = NULL
                WHERE is_master = 1 AND user_type = 'admin'
            ");
            $stmt->execute();

            $affectedRows = $stmt->rowCount();

            echo '<p class="success">✅ マスター管理者のclassroom_idを修正しました。</p>';
            echo '<p>更新されたレコード数: <strong>' . $affectedRows . '</strong></p>';
            echo '<hr>';

            // 確認
            $stmt = $pdo->prepare("
                SELECT id, username, full_name, classroom_id, is_master
                FROM users
                WHERE is_master = 1 AND user_type = 'admin'
            ");
            $stmt->execute();
            $masters = $stmt->fetchAll();

            echo '<h2>現在のマスター管理者:</h2>';
            foreach ($masters as $master) {
                echo '<div class="info">';
                echo '<strong>ID:</strong> ' . $master['id'] . '<br>';
                echo '<strong>ユーザー名:</strong> ' . htmlspecialchars($master['username']) . '<br>';
                echo '<strong>氏名:</strong> ' . htmlspecialchars($master['full_name']) . '<br>';
                echo '<strong>classroom_id:</strong> ';
                if ($master['classroom_id'] === null) {
                    echo '<span class="success">NULL (正常)</span>';
                } else {
                    echo '<span class="error">' . $master['classroom_id'] . ' (要確認)</span>';
                }
                echo '</div>';
            }

            echo '<p style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">';
            echo '⚠️ <strong>重要:</strong> 一度ログアウトして再ログインしてください。そうすることでセッションが更新されます。';
            echo '</p>';

        } catch (PDOException $e) {
            echo '<p class="error">❌ エラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
        <a href="login.php" class="btn">ログインページへ</a>
    </div>
</body>
</html>
