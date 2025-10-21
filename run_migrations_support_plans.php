<?php
/**
 * 支援案機能のマイグレーション実行スクリプト
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    echo "データベース接続成功\n";

    // v26: support_plansテーブルの作成
    echo "\n=== Migration v26: support_plansテーブルの作成 ===\n";

    // テーブルが存在するか確認
    $stmt = $pdo->query("SHOW TABLES LIKE 'support_plans'");
    if ($stmt->rowCount() > 0) {
        echo "support_plansテーブルは既に存在します。スキップします。\n";
    } else {
        $sql = file_get_contents(__DIR__ . '/migration_v26_create_support_plans.sql');
        $pdo->exec($sql);
        echo "✓ support_plansテーブルを作成しました\n";
    }

    // v27: daily_recordsテーブルにsupport_plan_idカラムを追加
    echo "\n=== Migration v27: daily_recordsにsupport_plan_idカラムを追加 ===\n";

    // カラムが存在するか確認
    $stmt = $pdo->query("SHOW COLUMNS FROM daily_records LIKE 'support_plan_id'");
    if ($stmt->rowCount() > 0) {
        echo "support_plan_idカラムは既に存在します。スキップします。\n";
    } else {
        $sql = file_get_contents(__DIR__ . '/migration_v27_add_support_plan_to_daily_records.sql');
        $pdo->exec($sql);
        echo "✓ support_plan_idカラムを追加しました\n";
    }

    // v28: support_plansにactivity_dateカラムを追加
    echo "\n=== Migration v28: support_plansにactivity_dateカラムを追加 ===\n";

    // カラムが存在するか確認
    $stmt = $pdo->query("SHOW COLUMNS FROM support_plans LIKE 'activity_date'");
    if ($stmt->rowCount() > 0) {
        echo "activity_dateカラムは既に存在します。\n";

        // 既存データでNULLの場合は更新
        $stmt = $pdo->query("SELECT COUNT(*) FROM support_plans WHERE activity_date IS NULL");
        $nullCount = $stmt->fetchColumn();

        if ($nullCount > 0) {
            echo "{$nullCount}件のNULLデータを更新します...\n";
            $pdo->exec("UPDATE support_plans SET activity_date = DATE(created_at) WHERE activity_date IS NULL");
            echo "✓ 既存データを更新しました\n";
        } else {
            echo "すべてのデータにactivity_dateが設定されています。\n";
        }
    } else {
        $sql = file_get_contents(__DIR__ . '/migration_v28_add_activity_date_to_support_plans.sql');
        $pdo->exec($sql);
        echo "✓ activity_dateカラムを追加し、既存データを更新しました\n";
    }

    echo "\n=== すべてのマイグレーションが完了しました ===\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
