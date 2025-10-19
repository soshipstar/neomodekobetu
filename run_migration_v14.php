<?php
/**
 * マイグレーション v14 実行スクリプト
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>マイグレーション v14 実行</h1>";
echo "<p>unique_student_period_number 制約を削除します...</p>";

try {
    // マイグレーションファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v14_remove_unique_constraint.sql');

    // セミコロンで分割して各ステートメントを実行
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // エラーを記録するが続行
                echo "<p style='color: orange;'>Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }

    echo "<p style='color: green; font-weight: bold;'>✓ マイグレーション完了</p>";

    // 確認
    echo "<h2>確認</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM kakehashi_periods WHERE Key_name = 'unique_student_period_number'");
    $result = $stmt->fetchAll();

    if (empty($result)) {
        echo "<p style='color: green;'>✓ unique_student_period_number 制約は削除されました</p>";
    } else {
        echo "<p style='color: red;'>✗ unique_student_period_number 制約がまだ存在します</p>";
    }

    // period_numberカラムの確認
    $stmt = $pdo->query("SHOW COLUMNS FROM kakehashi_periods LIKE 'period_number'");
    $result = $stmt->fetchAll();

    if (empty($result)) {
        echo "<p style='color: green;'>✓ period_number カラムは削除されました</p>";
    } else {
        echo "<p style='color: orange;'>period_number カラムがまだ存在します（問題ありません）</p>";
    }

    echo "<hr>";
    echo "<p><a href='debug_generate.php'>かけはし生成テストに戻る</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
