<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>kakehashi_periods テーブル構造</h1>";

// テーブル定義を表示
$stmt = $pdo->query("SHOW CREATE TABLE kakehashi_periods");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>CREATE TABLE 文</h2>";
echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";

// インデックス情報を表示
$stmt = $pdo->query("SHOW INDEX FROM kakehashi_periods");
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>インデックス一覧</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Key name</th><th>Column</th><th>Unique</th><th>Type</th></tr>";
foreach ($indexes as $index) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
    echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
    echo "<td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>";
    echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>";
    echo "</tr>";
}
echo "</table>";
