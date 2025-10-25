<?php
/**
 * マイグレーションv34 - weekly_plansテーブルの更新
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    echo "マイグレーション v34 - 週間計画表テーブルの更新\n";

    // weekly_goalカラムが既に存在するか確認
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'weekly_plans'
        AND COLUMN_NAME = 'weekly_goal'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo "✓ 既に適用済みです。\n";
        exit(0);
    }

    // マイグレーションを実行
    $sql = file_get_contents(__DIR__ . '/migration_v34_update_weekly_plans.sql');
    $pdo->exec($sql);

    echo "✓ カラムを追加しました\n";
    echo "  - weekly_goal (今週の目標)\n";
    echo "  - shared_goal (いっしょに決めた目標)\n";
    echo "  - must_do (やるべきこと)\n";
    echo "  - should_do (やったほうがいいこと)\n";
    echo "  - want_to_do (やりたいこと)\n";
    echo "✓ マイグレーション完了\n";

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
