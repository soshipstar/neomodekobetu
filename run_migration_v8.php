<?php
/**
 * マイグレーションv8実行スクリプト
 * ブラウザから実行してください: http://kobetu.narze.xyz/run_migration_v8.php
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>\n";
echo "<html lang='ja'>\n";
echo "<head><meta charset='UTF-8'><title>マイグレーション実行</title></head>\n";
echo "<body style='font-family: monospace; padding: 20px;'>\n";
echo "<h1>マイグレーションv8 実行</h1>\n";

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

    echo "<h2>ステップ1: 現在のテーブル構造確認</h2>\n";
    echo "<pre>\n";

    // kakehashi_periodsの現在の構造を確認
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_periods");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "kakehashi_periodsの既存カラム:\n";
    print_r($columns);
    echo "</pre>\n";

    echo "<h2>ステップ2: カラム追加</h2>\n";

    // student_idカラムを追加
    if (!in_array('student_id', $columns)) {
        echo "<p>student_idカラムを追加中...</p>\n";
        flush();
        $pdo->exec("ALTER TABLE kakehashi_periods ADD COLUMN student_id INT NOT NULL DEFAULT 0 COMMENT '対象生徒ID' AFTER id");
        echo "<p style='color: green;'>✓ student_idカラムを追加しました</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ student_idカラムは既に存在します</p>\n";
    }

    // 最新のカラムリストを再取得
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_periods");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // period_numberカラムを追加
    if (!in_array('period_number', $columns)) {
        echo "<p>period_numberカラムを追加中...</p>\n";
        flush();
        $pdo->exec("ALTER TABLE kakehashi_periods ADD COLUMN period_number INT NOT NULL DEFAULT 1 COMMENT '期数（1期、2期...）' AFTER student_id");
        echo "<p style='color: green;'>✓ period_numberカラムを追加しました</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ period_numberカラムは既に存在します</p>\n";
    }

    // 最新のカラムリストを再取得
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_periods");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // is_auto_generatedカラムを追加
    if (!in_array('is_auto_generated', $columns)) {
        echo "<p>is_auto_generatedカラムを追加中...</p>\n";
        flush();
        $pdo->exec("ALTER TABLE kakehashi_periods ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 0 COMMENT '自動生成フラグ' AFTER is_active");
        echo "<p style='color: green;'>✓ is_auto_generatedカラムを追加しました</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ is_auto_generatedカラムは既に存在します</p>\n";
    }

    echo "<h2>ステップ3: 完了後の構造確認</h2>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_periods");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Default']}\n";
    }
    echo "</pre>\n";

    echo "<h2 style='color: green;'>✓ マイグレーション完了</h2>\n";
    echo "<p><strong>注意:</strong> 既存のkakehashi_periodsデータがある場合、student_idが0になっています。</p>\n";
    echo "<p>phpMyAdminで手動で正しい生徒IDを設定してください。</p>\n";
    echo "<p><a href='staff/students.php'>生徒登録ページに戻る</a></p>\n";

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
