<?php
/**
 * マイグレーションv35 - 週間計画表の提出物管理テーブル作成
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    echo "マイグレーション v35 - 週間計画表の提出物管理テーブル作成\n";

    // テーブルが既に存在するか確認
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'weekly_plan_submissions'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo "✓ 既に存在します。\n";
        exit(0);
    }

    // マイグレーションを実行
    $sql = file_get_contents(__DIR__ . '/migration_v35_create_weekly_plan_submissions.sql');
    $pdo->exec($sql);

    echo "✓ weekly_plan_submissions テーブルを作成しました\n";
    echo "✓ マイグレーション完了\n";

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
