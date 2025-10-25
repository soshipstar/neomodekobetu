<?php
/**
 * マイグレーションv37 - 生徒の学年調整カラム追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    echo "マイグレーション v37 - 生徒の学年調整カラム追加\n";

    // grade_adjustmentカラムが既に存在するか確認
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'students'
        AND COLUMN_NAME = 'grade_adjustment'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo "✓ 既に適用済みです。\n";
        exit(0);
    }

    // マイグレーションを実行
    $sql = file_get_contents(__DIR__ . '/migration_v37_add_grade_adjustment.sql');
    $pdo->exec($sql);

    echo "✓ grade_adjustmentカラムを追加しました\n";
    echo "  学年調整: -2, -1, 0, +1, +2 が設定可能\n";
    echo "✓ マイグレーション完了\n";

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
