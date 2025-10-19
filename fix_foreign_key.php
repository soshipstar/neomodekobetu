<?php
/**
 * 外部キー制約を削除してからユニーク制約を削除
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>外部キー制約とユニーク制約の削除</h1>";

try {
    // 1. 外部キー制約を確認
    echo "<h2>Step 1: 外部キー制約の確認</h2>";
    $stmt = $pdo->query("
        SELECT
            CONSTRAINT_NAME,
            TABLE_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME = 'kakehashi_periods'
    ");
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($foreignKeys)) {
        echo "<p>kakehashi_periods を参照している外部キーはありません</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>制約名</th><th>テーブル</th><th>参照先テーブル</th><th>参照先カラム</th></tr>";
        foreach ($foreignKeys as $fk) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 2. 外部キー制約を削除
        echo "<h2>Step 2: 外部キー制約の削除</h2>";
        foreach ($foreignKeys as $fk) {
            $tableName = $fk['TABLE_NAME'];
            $constraintName = $fk['CONSTRAINT_NAME'];

            try {
                $sql = "ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`";
                $pdo->exec($sql);
                echo "<p style='color: green;'>✓ {$tableName}.{$constraintName} を削除しました</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ {$tableName}.{$constraintName} の削除に失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }

    // 3. ユニーク制約を削除
    echo "<h2>Step 3: ユニーク制約の削除</h2>";
    try {
        $pdo->exec("ALTER TABLE kakehashi_periods DROP INDEX unique_student_period_number");
        echo "<p style='color: green;'>✓ unique_student_period_number を削除しました</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ 削除に失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // 4. period_number カラムを削除（オプション）
    echo "<h2>Step 4: period_number カラムの削除</h2>";
    try {
        $pdo->exec("ALTER TABLE kakehashi_periods DROP COLUMN period_number");
        echo "<p style='color: green;'>✓ period_number カラムを削除しました</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>period_number カラムの削除をスキップ: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // 5. 外部キー制約を再作成（ユニーク制約なしで）
    echo "<h2>Step 5: 外部キー制約の再作成</h2>";
    foreach ($foreignKeys as $fk) {
        $tableName = $fk['TABLE_NAME'];
        $constraintName = $fk['CONSTRAINT_NAME'];

        try {
            // period_id のみの外部キーとして再作成
            $sql = "ALTER TABLE `{$tableName}`
                    ADD CONSTRAINT `{$constraintName}`
                    FOREIGN KEY (`period_id`)
                    REFERENCES `kakehashi_periods` (`id`)
                    ON DELETE CASCADE";
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ {$tableName}.{$constraintName} を再作成しました</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>外部キーの再作成をスキップ: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    // 6. 最終確認
    echo "<h2>Step 6: 最終確認</h2>";
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
        echo "<h2 style='color: green;'>✓ 成功！制約が削除されました</h2>";
        echo "<p><a href='debug_generate.php' style='font-size: 18px; padding: 15px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>かけはし生成テストへ →</a></p>";
    } else {
        echo "<h2 style='color: red;'>✗ 失敗：制約がまだ存在します</h2>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
