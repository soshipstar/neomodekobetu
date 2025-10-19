<?php
/**
 * 全ての外部キー制約を表示
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>全ての外部キー制約</h1>";

// kakehashi_periods に関連する全ての外部キー
echo "<h2>kakehashi_periods テーブルに関連する外部キー</h2>";

// 1. kakehashi_periods が参照している外部キー
echo "<h3>kakehashi_periods から出ている外部キー：</h3>";
$stmt = $pdo->query("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'kakehashi_periods'
    AND REFERENCED_TABLE_NAME IS NOT NULL
");
$outgoingFKs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($outgoingFKs)) {
    echo "<p style='color: green;'>なし</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>制約名</th><th>カラム</th><th>参照先テーブル</th><th>参照先カラム</th></tr>";
    foreach ($outgoingFKs as $fk) {
        echo "<tr style='background: #ffebee;'>";
        echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. kakehashi_periods を参照している外部キー
echo "<h3>kakehashi_periods を参照している外部キー：</h3>";
$stmt = $pdo->query("
    SELECT
        CONSTRAINT_NAME,
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME = 'kakehashi_periods'
");
$incomingFKs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($incomingFKs)) {
    echo "<p style='color: green;'>なし</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>制約名</th><th>テーブル</th><th>カラム</th><th>参照先カラム</th></tr>";
    foreach ($incomingFKs as $fk) {
        echo "<tr style='background: #fff3e0;'>";
        echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['TABLE_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 削除用のSQLを生成
echo "<h2>削除用SQL（コピーして使用）</h2>";

echo "<h3>1. 外部キーを削除するSQL：</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";

foreach ($outgoingFKs as $fk) {
    echo "ALTER TABLE kakehashi_periods DROP FOREIGN KEY " . htmlspecialchars($fk['CONSTRAINT_NAME']) . ";\n";
}

foreach ($incomingFKs as $fk) {
    echo "ALTER TABLE " . htmlspecialchars($fk['TABLE_NAME']) . " DROP FOREIGN KEY " . htmlspecialchars($fk['CONSTRAINT_NAME']) . ";\n";
}

echo "</pre>";

echo "<h3>2. ユニーク制約を削除するSQL：</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo "ALTER TABLE kakehashi_periods DROP INDEX unique_student_period_number;\n";
echo "</pre>";

// 自動実行ボタン
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    echo "<hr><h2>実行結果</h2>";

    try {
        // 全ての外部キーを削除
        foreach ($outgoingFKs as $fk) {
            try {
                $sql = "ALTER TABLE kakehashi_periods DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME'];
                $pdo->exec($sql);
                echo "<p style='color: green;'>✓ kakehashi_periods.{$fk['CONSTRAINT_NAME']} を削除</p>";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>kakehashi_periods.{$fk['CONSTRAINT_NAME']}: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }

        foreach ($incomingFKs as $fk) {
            try {
                $sql = "ALTER TABLE " . $fk['TABLE_NAME'] . " DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME'];
                $pdo->exec($sql);
                echo "<p style='color: green;'>✓ {$fk['TABLE_NAME']}.{$fk['CONSTRAINT_NAME']} を削除</p>";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>{$fk['TABLE_NAME']}.{$fk['CONSTRAINT_NAME']}: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }

        // ユニーク制約を削除
        try {
            $pdo->exec("ALTER TABLE kakehashi_periods DROP INDEX unique_student_period_number");
            echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✓ unique_student_period_number を削除しました！</p>";
            echo "<p><a href='debug_generate.php' style='padding: 15px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; font-size: 16px;'>かけはし生成テストへ →</a></p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ unique_student_period_number の削除に失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<form method='POST'>";
    echo "<p><button type='submit' name='execute' value='1' style='padding: 15px 30px; background: #f44336; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>全ての外部キーとユニーク制約を削除する</button></p>";
    echo "</form>";
}
