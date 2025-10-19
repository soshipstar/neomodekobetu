<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーション v23 を実行中...\n\n";

    $sql = file_get_contents(__DIR__ . '/migration_v23_create_events.sql');

    $pdo->exec($sql);

    echo "✓ eventsテーブルを作成しました\n";
    echo "✓ event_registrationsテーブルを作成しました\n";
    echo "✓ chat_messagesのmessage_typeにevent_registrationを追加しました\n";
    echo "\nマイグレーション完了！\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>
