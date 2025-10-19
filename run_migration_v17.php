<?php
/**
 * マイグレーション実行スクリプト v17
 * かけはしテーブルに非表示フラグを追加
 */

// ブラウザ表示用のヘッダー設定
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイグレーション v17</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .success {
            color: #4ec9b0;
        }
        .error {
            color: #f48771;
        }
        .info {
            color: #4fc1ff;
        }
        .statement {
            background: #2d2d30;
            padding: 10px;
            margin: 10px 0;
            border-left: 3px solid #007acc;
            overflow-x: auto;
        }
        h1 {
            color: #4ec9b0;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "<h1>=== Migration v17: かけはし非表示フラグ追加 ===</h1>";

    // カラムが既に存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_guardian LIKE 'is_hidden'");
    $guardianExists = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_staff LIKE 'is_hidden'");
    $staffExists = $stmt->rowCount() > 0;

    if ($guardianExists && $staffExists) {
        echo "<p class='info'>✓ is_hiddenカラムは既に存在します。マイグレーションは不要です。</p>";
        echo "<p><a href='staff/renrakucho_activities.php' style='color: #4fc1ff;'>← スタッフページに戻る</a></p>";
    } else {
        // SQLファイルを読み込む
        $sql = file_get_contents(__DIR__ . '/migration_v17_add_hidden_flag.sql');

        // セミコロンで分割して実行
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }

            // コメント行を除外
            $lines = explode("\n", $statement);
            $cleanStatement = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '--') !== 0) {
                    $cleanStatement .= $line . "\n";
                }
            }

            if (empty(trim($cleanStatement))) {
                continue;
            }

            echo "<div class='statement'>";
            echo "<p class='info'>実行中:</p>";
            echo "<pre>" . htmlspecialchars($cleanStatement) . "</pre>";

            try {
                $pdo->exec($cleanStatement);
                echo "<p class='success'>✓ 完了</p>";
            } catch (Exception $e) {
                // カラムが既に存在する場合のエラーは無視
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    echo "<p class='info'>✓ カラムは既に存在します（スキップ）</p>";
                } else {
                    throw $e;
                }
            }

            echo "</div>";
        }

        echo "<h2 class='success'>=== マイグレーション完了 ===</h2>";
        echo "<p><a href='staff/renrakucho_activities.php' style='color: #4fc1ff;'>← スタッフページに戻る</a></p>";
    }

} catch (Exception $e) {
    echo "<h2 class='error'>エラーが発生しました</h2>";
    echo "<div class='statement'>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
</body>
</html>
