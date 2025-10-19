<?php
/**
 * staff_id を NULL 許可に強制変更
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>staff_id NULL 許可への強制変更</h1>";

try {
    // Step 1: 既存データを確認
    echo "<h2>Step 1: 既存データ確認</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(staff_id) as non_null FROM kakehashi_staff");
    $counts = $stmt->fetch();

    echo "<p>総レコード数: {$counts['total']}</p>";
    echo "<p>staff_id が NULL でないレコード: {$counts['non_null']}</p>";

    // Step 2: 外部キー制約を確認
    echo "<h2>Step 2: 外部キー制約確認</h2>";
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'kakehashi_staff'
        AND COLUMN_NAME = 'staff_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($constraints as $constraint) {
        echo "<p>外部キー制約: {$constraint['CONSTRAINT_NAME']}</p>";
    }

    // Step 3: 外部キー制約を削除
    echo "<h2>Step 3: 外部キー制約削除</h2>";

    foreach ($constraints as $constraint) {
        try {
            $sql = "ALTER TABLE kakehashi_staff DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}";
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ {$constraint['CONSTRAINT_NAME']} を削除</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ {$constraint['CONSTRAINT_NAME']} の削除に失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // Step 4: カラムを NULL 許可に変更
    echo "<h2>Step 4: カラムを NULL 許可に変更</h2>";

    try {
        $pdo->exec("ALTER TABLE kakehashi_staff MODIFY COLUMN staff_id int DEFAULT NULL COMMENT 'スタッフID（NULL=未割当）'");
        echo "<p style='color: green;'>✓ staff_id を NULL 許可に変更しました</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ 変更に失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Step 5: 外部キー制約を再作成
    echo "<h2>Step 5: 外部キー制約を再作成</h2>";

    try {
        $pdo->exec("
            ALTER TABLE kakehashi_staff
            ADD CONSTRAINT kakehashi_staff_ibfk_3
            FOREIGN KEY (staff_id)
            REFERENCES users (id)
            ON DELETE SET NULL
        ");
        echo "<p style='color: green;'>✓ 外部キー制約を再作成しました（ON DELETE SET NULL）</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>外部キー制約の再作成: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Step 6: 確認
    echo "<h2>Step 6: 最終確認</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_staff LIKE 'staff_id'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    echo "<tr>";
    echo "<td>{$column['Field']}</td>";
    echo "<td>{$column['Type']}</td>";
    echo "<td style='background: " . ($column['Null'] == 'YES' ? '#d4edda' : '#f8d7da') . "; font-weight: bold;'>{$column['Null']}</td>";
    echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
    echo "</table>";

    if ($column['Null'] == 'YES') {
        echo "<h2 style='color: green;'>✓ 成功！</h2>";
        echo "<p>staff_id は NULL を許可するようになりました。</p>";
        echo "<p><a href='debug_kakehashi_periods.php' style='padding: 15px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; font-size: 16px;'>かけはし期間デバッグへ →</a></p>";
    } else {
        echo "<h2 style='color: red;'>✗ 失敗</h2>";
        echo "<p>staff_id はまだ NULL を許可していません。</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
