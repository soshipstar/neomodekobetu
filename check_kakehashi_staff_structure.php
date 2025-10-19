<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>kakehashi_staff テーブル構造</h1>";

$stmt = $pdo->query("SHOW CREATE TABLE kakehashi_staff");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre style='font-size: 11px; overflow-x: auto; background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($result['Create Table']) . "</pre>";

// サンプルデータ
echo "<h2>サンプルデータ</h2>";
$stmt = $pdo->query("SELECT * FROM kakehashi_staff LIMIT 5");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) {
    echo "<p>データなし</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr>";
    foreach (array_keys($data[0]) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
