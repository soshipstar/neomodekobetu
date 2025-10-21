<?php
/**
 * マイグレーション v26 実行スクリプト
 * 生徒ステータスに短期利用を追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "=== マイグレーション v26: 生徒ステータス更新 ===\n\n";

    // マイグレーションファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v26_update_student_status.sql');

    // コメント行を除去
    $sql = preg_replace('/--.*$/m', '', $sql);

    // 実行
    echo "生徒ステータスカラムを更新中...\n";
    $pdo->exec($sql);

    echo "✓ マイグレーション完了\n\n";

    // 確認
    echo "現在の生徒ステータス別件数:\n";
    $stmt = $pdo->query("
        SELECT
            status,
            COUNT(*) as count,
            CASE status
                WHEN 'trial' THEN '体験'
                WHEN 'active' THEN '在籍'
                WHEN 'short_term' THEN '短期利用'
                WHEN 'withdrawn' THEN '退所'
                ELSE status
            END as status_name
        FROM students
        GROUP BY status
        ORDER BY
            FIELD(status, 'active', 'trial', 'short_term', 'withdrawn')
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['status_name']}: {$row['count']}名\n";
    }

    echo "\n処理が正常に完了しました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
