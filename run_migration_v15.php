<?php
/**
 * マイグレーション v15 実行スクリプト
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>マイグレーション v15 実行</h1>";
echo "<p>モニタリング表に目標評価カラムを追加します...</p>";

try {
    // マイグレーションファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v15_add_goal_evaluation.sql');

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

    $stmt = $pdo->query("SHOW COLUMNS FROM monitoring_records LIKE 'long_term_goal_evaluation'");
    $result = $stmt->fetchAll();

    if (!empty($result)) {
        echo "<p style='color: green;'>✓ long_term_goal_evaluation カラムが追加されました</p>";
    } else {
        echo "<p style='color: red;'>✗ long_term_goal_evaluation カラムがありません</p>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM monitoring_records LIKE 'short_term_goal_evaluation'");
    $result = $stmt->fetchAll();

    if (!empty($result)) {
        echo "<p style='color: green;'>✓ short_term_goal_evaluation カラムが追加されました</p>";
    } else {
        echo "<p style='color: red;'>✗ short_term_goal_evaluation カラムがありません</p>";
    }

    echo "<hr>";
    echo "<p><a href='test_monitoring_creation.php'>モニタリング作成テストに戻る</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
