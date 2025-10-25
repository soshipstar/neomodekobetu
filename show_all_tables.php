<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>テーブル一覧</title>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#667eea;color:white;}</style></head><body>";
echo "<h1>データベース内のすべてのテーブル</h1>";

// 現在のデータベース名を取得
$stmt = $pdo->query("SELECT DATABASE()");
$dbName = $stmt->fetchColumn();
echo "<p>データベース: <strong>$dbName</strong></p>";

// すべてのテーブルを取得
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<p>テーブル数: <strong>" . count($tables) . "</strong></p>";

echo "<table>";
echo "<tr><th>No.</th><th>テーブル名</th><th>レコード数</th></tr>";

$i = 1;
foreach ($tables as $table) {
    // レコード数を取得
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
    } catch (Exception $e) {
        $count = 'エラー';
    }

    echo "<tr>";
    echo "<td>$i</td>";
    echo "<td>$table</td>";
    echo "<td>$count</td>";
    echo "</tr>";
    $i++;
}

echo "</table>";

// student関連のテーブルをハイライト
echo "<h2>生徒システム関連のテーブル確認</h2>";
$studentTables = ['student_chat_rooms', 'student_chat_messages', 'weekly_plans', 'weekly_plan_comments'];
echo "<ul>";
foreach ($studentTables as $table) {
    $exists = in_array($table, $tables);
    if ($exists) {
        echo "<li style='color:green;'>✓ $table が存在します</li>";
    } else {
        echo "<li style='color:red;'>✗ $table が存在しません</li>";
    }
}
echo "</ul>";

echo "</body></html>";
