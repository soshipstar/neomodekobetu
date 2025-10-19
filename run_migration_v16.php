<?php
/**
 * マイグレーション v16 実行スクリプト
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>マイグレーション v16 実行</h1>";
echo "<p>kakehashi_staff の staff_id を NULL 許可に変更します...</p>";

try {
    // マイグレーションファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v16_allow_null_staff_id.sql');

    // セミコロンで分割して各ステートメントを実行
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
                echo "<p style='color: green;'>✓ 実行: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<pre>" . htmlspecialchars($statement) . "</pre>";
            }
        }
    }

    echo "<h2>確認</h2>";

    // カラム定義を確認
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_staff LIKE 'staff_id'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td style='background: " . ($column['Null'] == 'YES' ? '#d4edda' : '#f8d7da') . ";'>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
        echo "</table>";

        if ($column['Null'] == 'YES') {
            echo "<p style='color: green; font-weight: bold;'>✓ staff_id は NULL を許可するようになりました！</p>";
            echo "<p><a href='debug_kakehashi_periods.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>かけはし期間デバッグへ →</a></p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ staff_id はまだ NULL を許可していません</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
