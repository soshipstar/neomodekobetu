<?php
/**
 * Migration v30: 生徒用ログイン情報を追加
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$output = [];

try {
    $pdo = getDbConnection();

    $output[] = "=== Migration v30: 生徒用ログイン情報の追加 ===";

    // 追加するカラムの定義
    $columns = [
        'username' => "ADD COLUMN username VARCHAR(50) NULL UNIQUE COMMENT '生徒用ログインID'",
        'password_hash' => "ADD COLUMN password_hash VARCHAR(255) NULL COMMENT '生徒用パスワード（ハッシュ化）'",
        'password_plain' => "ADD COLUMN password_plain VARCHAR(255) NULL COMMENT '生徒用パスワード（平文・表示用）'",
        'last_login' => "ADD COLUMN last_login DATETIME NULL COMMENT '最終ログイン日時'"
    ];

    $addedCount = 0;
    $skippedCount = 0;

    // 各カラムを個別にチェックして追加
    foreach ($columns as $columnName => $alterSql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE '{$columnName}'");
        if ($stmt->rowCount() > 0) {
            $output[] = "✓ {$columnName} カラムは既に存在します。";
            $skippedCount++;
        } else {
            try {
                $pdo->exec("ALTER TABLE students {$alterSql}");
                $output[] = "✓ {$columnName} カラムを追加しました。";
                $addedCount++;
            } catch (PDOException $e) {
                $output[] = "✗ {$columnName} カラムの追加に失敗: " . $e->getMessage();
            }
        }
    }

    $output[] = "";
    $output[] = "=== Migration v30 完了 ===";
    $output[] = "追加: {$addedCount} カラム / スキップ: {$skippedCount} カラム";

} catch (PDOException $e) {
    $output[] = "エラー: " . $e->getMessage();
}

// コマンドラインからの実行の場合
if (php_sapi_name() === 'cli') {
    foreach ($output as $line) {
        echo $line . "\n";
    }
} else {
    // ブラウザからの実行の場合
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migration v30</title></head><body>";
    echo "<pre>";
    echo implode("\n", $output);
    echo "</pre>";
    echo "</body></html>";
}
