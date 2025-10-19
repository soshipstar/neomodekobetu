<?php
/**
 * ステップバイステップで制約を削除
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>制約削除（ステップバイステップ）</h1>";

$step = $_GET['step'] ?? 1;

try {
    if ($step == 1) {
        echo "<h2>Step 1: 現在の状態確認</h2>";

        // 外部キー確認
        $stmt = $pdo->query("
            SELECT
                CONSTRAINT_NAME,
                TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME = 'kakehashi_periods'
        ");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>外部キー制約:</h3>";
        if (empty($foreignKeys)) {
            echo "<p style='color: green;'>外部キーなし</p>";
        } else {
            echo "<ul>";
            foreach ($foreignKeys as $fk) {
                echo "<li>{$fk['TABLE_NAME']}.{$fk['CONSTRAINT_NAME']}</li>";
            }
            echo "</ul>";
        }

        // インデックス確認
        $stmt = $pdo->query("SHOW INDEX FROM kakehashi_periods WHERE Key_name = 'unique_student_period_number'");
        $indexes = $stmt->fetchAll();

        echo "<h3>unique_student_period_number 制約:</h3>";
        if (empty($indexes)) {
            echo "<p style='color: green;'>制約なし</p>";
        } else {
            echo "<p style='color: red;'>制約あり</p>";
        }

        echo "<p><a href='?step=2' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Step 2へ進む</a></p>";

    } elseif ($step == 2) {
        echo "<h2>Step 2: 外部キー制約を削除</h2>";

        $pdo->exec("ALTER TABLE kakehashi_guardian DROP FOREIGN KEY kakehashi_guardian_ibfk_1");
        echo "<p style='color: green;'>✓ kakehashi_guardian の外部キーを削除</p>";

        $pdo->exec("ALTER TABLE kakehashi_staff DROP FOREIGN KEY kakehashi_staff_ibfk_1");
        echo "<p style='color: green;'>✓ kakehashi_staff の外部キーを削除</p>";

        echo "<p><a href='?step=3' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Step 3へ進む</a></p>";

    } elseif ($step == 3) {
        echo "<h2>Step 3: ユニーク制約を削除</h2>";

        try {
            $pdo->exec("ALTER TABLE kakehashi_periods DROP INDEX unique_student_period_number");
            echo "<p style='color: green;'>✓ unique_student_period_number を削除しました</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ 削除失敗: " . htmlspecialchars($e->getMessage()) . "</p>";

            // テーブル定義を表示
            echo "<h3>テーブル定義:</h3>";
            $stmt = $pdo->query("SHOW CREATE TABLE kakehashi_periods");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre style='font-size: 12px; overflow-x: auto;'>" . htmlspecialchars($result['Create Table']) . "</pre>";
        }

        // 確認
        $stmt = $pdo->query("SHOW INDEX FROM kakehashi_periods WHERE Key_name = 'unique_student_period_number'");
        $indexes = $stmt->fetchAll();

        if (empty($indexes)) {
            echo "<p style='color: green; font-weight: bold;'>✓ 制約が削除されました！</p>";
            echo "<p><a href='?step=4' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Step 4へ進む（外部キー再作成）</a></p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ 制約がまだ存在します</p>";
            echo "<p><a href='?step=1' style='padding: 10px 20px; background: #FF9800; color: white; text-decoration: none; border-radius: 5px;'>Step 1に戻る</a></p>";
        }

    } elseif ($step == 4) {
        echo "<h2>Step 4: 外部キー制約を再作成</h2>";

        try {
            $pdo->exec("
                ALTER TABLE kakehashi_guardian
                ADD CONSTRAINT kakehashi_guardian_ibfk_1
                FOREIGN KEY (period_id)
                REFERENCES kakehashi_periods (id)
                ON DELETE CASCADE
            ");
            echo "<p style='color: green;'>✓ kakehashi_guardian の外部キーを再作成</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>kakehashi_guardian: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        try {
            $pdo->exec("
                ALTER TABLE kakehashi_staff
                ADD CONSTRAINT kakehashi_staff_ibfk_1
                FOREIGN KEY (period_id)
                REFERENCES kakehashi_periods (id)
                ON DELETE CASCADE
            ");
            echo "<p style='color: green;'>✓ kakehashi_staff の外部キーを再作成</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>kakehashi_staff: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        echo "<h2 style='color: green;'>完了！</h2>";
        echo "<p><a href='debug_generate.php' style='font-size: 18px; padding: 15px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>かけはし生成テストへ →</a></p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
