<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーション v24 を実行中...\n\n";

    $sql = file_get_contents(__DIR__ . '/migration_v24_create_submission_requests.sql');

    $pdo->exec($sql);

    echo "✓ submission_requestsテーブルを作成しました\n";
    echo "\nマイグレーション完了！\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>
