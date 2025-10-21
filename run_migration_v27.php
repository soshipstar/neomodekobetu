<?php
/**
 * マイグレーション v27 実行スクリプト
 * 生徒の退所日カラムを追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "=== マイグレーション v27: 退所日カラム追加 ===\n\n";

    // withdrawal_dateカラムが既に存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'withdrawal_date'");
    if ($stmt->rowCount() > 0) {
        echo "withdrawal_dateカラムは既に存在します。\n";
        exit(0);
    }

    // マイグレーションファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v27_add_withdrawal_date.sql');

    // コメント行を除去
    $sql = preg_replace('/--.*$/m', '', $sql);

    // 実行
    echo "withdrawal_dateカラムを追加中...\n";
    $pdo->exec($sql);

    echo "✓ マイグレーション完了\n\n";

    // 確認
    echo "退所済み生徒の件数:\n";
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as count,
            SUM(CASE WHEN withdrawal_date IS NOT NULL THEN 1 ELSE 0 END) as with_date
        FROM students
        WHERE status = 'withdrawn'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  退所済み: {$row['count']}名\n";
    echo "  退所日設定済み: {$row['with_date']}名\n";

    echo "\n処理が正常に完了しました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
