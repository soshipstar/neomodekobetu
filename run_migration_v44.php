<?php
/**
 * マイグレーション v44 実行スクリプト
 * absence_notificationsテーブルに振替依頼関連カラムを追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーション v44 を開始します...\n";

    // SQLファイルを読み込んで実行
    $sql = file_get_contents(__DIR__ . '/migration_v44_add_makeup_request.sql');

    // セミコロンで分割して各クエリを実行
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $query) {
        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }

        echo "実行中: " . substr($query, 0, 50) . "...\n";
        $pdo->exec($query);
    }

    echo "マイグレーション v44 が正常に完了しました。\n";

} catch (PDOException $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
