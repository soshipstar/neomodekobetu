<?php
/**
 * マイグレーション v29: 施設通信テーブルの作成
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーション v29 を実行します...\n";

    // SQLファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v29_create_newsletters.sql');

    // 実行
    $pdo->exec($sql);

    echo "✓ newsletters テーブルを作成しました\n";
    echo "マイグレーション v29 が正常に完了しました！\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
