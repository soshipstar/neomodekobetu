<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーション v22 を実行中...\n\n";

    $sql = file_get_contents(__DIR__ . '/migration_v22_create_absence_notifications.sql');

    $pdo->exec($sql);

    echo "✓ 欠席連絡テーブルを作成しました\n";
    echo "✓ chat_messagesテーブルにmessage_typeカラムを追加しました\n";
    echo "\nマイグレーション完了！\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>
