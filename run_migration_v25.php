<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーション v25 を実行中...\n\n";

    $sql = file_get_contents(__DIR__ . '/migration_v25_add_submission_attachment.sql');

    $pdo->exec($sql);

    echo "✓ submission_requestsテーブルに添付ファイル関連カラムを追加しました\n";
    echo "\nマイグレーション完了！\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>
