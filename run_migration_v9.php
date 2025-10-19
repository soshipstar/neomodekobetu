<?php
/**
 * マイグレーションv9実行スクリプト
 * 本人の願いフィールドを追加
 * ブラウザから実行してください: http://kobetu.narze.xyz/run_migration_v9.php
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>\n";
echo "<html lang='ja'>\n";
echo "<head><meta charset='UTF-8'><title>マイグレーション実行</title></head>\n";
echo "<body style='font-family: monospace; padding: 20px;'>\n";
echo "<h1>マイグレーションv9 実行 - 本人の願いフィールド追加</h1>\n";

try {
    // database.phpを読み込み
    $dbPath = __DIR__ . '/config/database.php';
    echo "<p>database.phpのパス: " . htmlspecialchars($dbPath) . "</p>\n";

    if (!file_exists($dbPath)) {
        throw new Exception("database.phpが見つかりません: $dbPath");
    }

    require_once $dbPath;

    if (!function_exists('getDbConnection')) {
        throw new Exception("getDbConnection関数が見つかりません");
    }

    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>ステップ1: kakehashi_guardianテーブルの構造確認</h2>\n";
    echo "<pre>\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_guardian");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "kakehashi_guardianの既存カラム:\n";
    print_r($columns);
    echo "</pre>\n";

    echo "<h2>ステップ2: kakehashi_guardianにstudent_wishカラムを追加</h2>\n";

    if (!in_array('student_wish', $columns)) {
        echo "<p>student_wishカラムを追加中...</p>\n";
        flush();
        $pdo->exec("ALTER TABLE kakehashi_guardian ADD COLUMN student_wish TEXT DEFAULT NULL COMMENT '本人の願い' AFTER period_id");
        echo "<p style='color: green;'>✓ student_wishカラムを追加しました</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ student_wishカラムは既に存在します</p>\n";
    }

    echo "<h2>ステップ3: kakehashi_staffテーブルの構造確認</h2>\n";
    echo "<pre>\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_staff");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "kakehashi_staffの既存カラム:\n";
    print_r($columns);
    echo "</pre>\n";

    echo "<h2>ステップ4: kakehashi_staffにstudent_wishカラムを追加</h2>\n";

    if (!in_array('student_wish', $columns)) {
        echo "<p>student_wishカラムを追加中...</p>\n";
        flush();
        $pdo->exec("ALTER TABLE kakehashi_staff ADD COLUMN student_wish TEXT DEFAULT NULL COMMENT '本人の願い' AFTER period_id");
        echo "<p style='color: green;'>✓ student_wishカラムを追加しました</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ student_wishカラムは既に存在します</p>\n";
    }

    echo "<h2>ステップ5: 完了後の構造確認</h2>\n";

    echo "<h3>kakehashi_guardian</h3>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_guardian");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Default']}\n";
    }
    echo "</pre>\n";

    echo "<h3>kakehashi_staff</h3>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_staff");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Default']}\n";
    }
    echo "</pre>\n";

    echo "<h2 style='color: green;'>✓ マイグレーション完了</h2>\n";
    echo "<p>「本人の願い」フィールドが追加されました。</p>\n";
    echo "<p><a href='guardian/kakehashi.php'>保護者かけはしページ</a></p>\n";
    echo "<p><a href='staff/kakehashi_staff.php'>スタッフかけはしページ</a></p>\n";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>データベースエラーが発生しました</h2>\n";
    echo "<pre style='color: red; background: #fff0f0; padding: 10px;'>\n";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>\n";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>エラーが発生しました</h2>\n";
    echo "<pre style='color: red; background: #fff0f0; padding: 10px;'>\n";
    echo htmlspecialchars($e->getMessage());
    echo "\n\nStack trace:\n";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>\n";
}

echo "</body>\n";
echo "</html>\n";
