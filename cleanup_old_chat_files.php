<?php
/**
 * 古いチャット添付ファイルのクリーンアップスクリプト
 * 1ヶ月以上前のファイルを削除
 *
 * cronで定期実行することを推奨
 * 例: 0 3 * * * php /path/to/cleanup_old_chat_files.php
 */

require_once __DIR__ . '/config/database.php';

echo "=== チャット添付ファイルクリーンアップ開始 ===\n";
echo "実行日時: " . date('Y-m-d H:i:s') . "\n\n";

$pdo = getDbConnection();
$uploadDir = __DIR__ . '/uploads/chat_attachments/';

// 1ヶ月以上前のメッセージを取得
$oneMonthAgo = date('Y-m-d H:i:s', strtotime('-1 month'));

try {
    $stmt = $pdo->prepare("
        SELECT id, attachment_path, attachment_original_name, created_at
        FROM chat_messages
        WHERE attachment_path IS NOT NULL
        AND created_at < ?
    ");
    $stmt->execute([$oneMonthAgo]);
    $oldMessages = $stmt->fetchAll();

    $deletedFiles = 0;
    $deletedRecords = 0;
    $errors = 0;

    foreach ($oldMessages as $message) {
        $filePath = __DIR__ . '/' . $message['attachment_path'];

        // ファイルを削除
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                echo "✓ ファイル削除: {$message['attachment_original_name']} (ID: {$message['id']})\n";
                $deletedFiles++;
            } else {
                echo "✗ ファイル削除失敗: {$message['attachment_original_name']} (ID: {$message['id']})\n";
                $errors++;
            }
        }

        // データベースから添付ファイル情報を削除
        $updateStmt = $pdo->prepare("
            UPDATE chat_messages
            SET attachment_path = NULL,
                attachment_original_name = NULL,
                attachment_size = NULL,
                attachment_type = NULL
            WHERE id = ?
        ");
        if ($updateStmt->execute([$message['id']])) {
            $deletedRecords++;
        } else {
            $errors++;
        }
    }

    echo "\n=== クリーンアップ完了 ===\n";
    echo "削除したファイル数: {$deletedFiles}\n";
    echo "更新したレコード数: {$deletedRecords}\n";
    echo "エラー数: {$errors}\n";
    echo "処理日時: " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>
