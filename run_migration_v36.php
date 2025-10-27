<?php
/**
 * マイグレーションv36 - 週間計画表の達成度評価カラム追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    echo "マイグレーション v36 - 週間計画表の達成度評価カラム追加\n";

    // weekly_goal_achievementカラムが既に存在するか確認
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'weekly_plans'
        AND COLUMN_NAME = 'weekly_goal_achievement'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo "✓ 既に適用済みです。\n";
        exit(0);
    }

    // マイグレーションを実行
    $sql = file_get_contents(__DIR__ . '/migration_v36_add_weekly_plan_achievements.sql');
    $pdo->exec($sql);

    echo "✓ 達成度評価カラムを追加しました\n";
    echo "  - 各目標項目の達成度カラム (weekly_goal_achievement, shared_goal_achievement, must_do_achievement, should_do_achievement, want_to_do_achievement)\n";
    echo "  - 各目標項目のコメントカラム\n";
    echo "  - 各曜日の達成度データ (daily_achievement)\n";
    echo "  - 総合コメント (overall_comment)\n";
    echo "  - 評価情報 (evaluated_at, evaluated_by_type, evaluated_by_id)\n";
    echo "✓ マイグレーション完了\n";

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
