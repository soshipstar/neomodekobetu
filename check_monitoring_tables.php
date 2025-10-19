<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>モニタリング関連テーブル構造</h1>";

$tables = ['monitoring_records', 'monitoring_details', 'individual_support_plans', 'individual_support_plan_details'];

foreach ($tables as $table) {
    echo "<h2>{$table}</h2>";

    try {
        $stmt = $pdo->query("SHOW CREATE TABLE {$table}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre style='font-size: 11px; overflow-x: auto; background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($result['Create Table']) . "</pre>";

        // サンプルデータを表示
        $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 3");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($data)) {
            echo "<h3>サンプルデータ:</h3>";
            echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
            echo "<tr>";
            foreach (array_keys($data[0]) as $col) {
                echo "<th>" . htmlspecialchars($col) . "</th>";
            }
            echo "</tr>";
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($row as $val) {
                    echo "<td>" . htmlspecialchars(substr($val ?? '', 0, 50)) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<hr>";
}
