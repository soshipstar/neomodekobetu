<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<h1>eventsテーブルの構造確認</h1>";

try {
    $stmt = $pdo->query("DESCRIBE events");
    $columns = $stmt->fetchAll();

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>カラム名</th><th>型</th><th>NULL許可</th><th>キー</th><th>デフォルト</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
?>
