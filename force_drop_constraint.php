<?php
/**
 * ユニーク制約を強制削除
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>ユニーク制約の強制削除</h1>";

try {
    // 現在の制約を確認
    echo "<h2>削除前の状態</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM kakehashi_periods");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($indexes as $index) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
        echo "<td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 制約を削除
    echo "<h2>制約削除実行</h2>";

    try {
        $pdo->exec("ALTER TABLE kakehashi_periods DROP INDEX unique_student_period_number");
        echo "<p style='color: green;'>✓ unique_student_period_number を削除しました</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // 削除後の状態を確認
    echo "<h2>削除後の状態</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM kakehashi_periods");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasConstraint = false;
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'unique_student_period_number') {
            $hasConstraint = true;
        }
        echo "<tr>";
        echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
        echo "<td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (!$hasConstraint) {
        echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✓ 制約の削除に成功しました！</p>";
        echo "<p><a href='debug_generate.php' style='font-size: 16px; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>かけはし生成テストへ</a></p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ 制約がまだ存在します</p>";

        // テーブルのCREATE文を表示
        echo "<h2>テーブル定義</h2>";
        $stmt = $pdo->query("SHOW CREATE TABLE kakehashi_periods");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
