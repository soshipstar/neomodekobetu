<?php
/**
 * マイグレーションv39実行スクリプト
 * メッセージ削除機能の追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーションv39を開始します...\n";

    // SQLファイルを読み込んで実行
    $sql = file_get_contents(__DIR__ . '/migration_v39_add_message_deleted.sql');

    // コメント行を除去
    $sql = preg_replace('/^--.*$/m', '', $sql);

    // 空行を除去
    $sql = preg_replace('/^\s*$/m', '', $sql);

    // ステートメントごとに分割して実行
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "実行中: " . substr($statement, 0, 100) . "...\n";
            $pdo->exec($statement);
        }
    }

    echo "マイグレーションv39が完了しました。\n";

} catch (PDOException $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
