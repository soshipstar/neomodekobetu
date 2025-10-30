<?php
/**
 * 完了した提出物データを1か月後に削除するスクリプト
 *
 * cron設定例（毎日午前2時に実行）:
 * 0 2 * * * cd /path/to/kobetu && php cleanup_old_submissions.php
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    // 完了から半年以上経過した提出物を取得（添付ファイル削除のため）
    $stmt = $pdo->prepare("
        SELECT id, attachment_path
        FROM submission_requests
        WHERE is_completed = 1
            AND completed_at IS NOT NULL
            AND completed_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $stmt->execute();
    $oldSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;
    $deletedFiles = 0;

    // 添付ファイルを削除
    foreach ($oldSubmissions as $submission) {
        if ($submission['attachment_path']) {
            $filePath = __DIR__ . '/' . $submission['attachment_path'];
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedFiles++;
                    echo "削除: {$submission['attachment_path']}\n";
                } else {
                    echo "警告: ファイル削除失敗: {$submission['attachment_path']}\n";
                }
            }
        }
    }

    // データベースから削除
    $stmt = $pdo->prepare("
        DELETE FROM submission_requests
        WHERE is_completed = 1
            AND completed_at IS NOT NULL
            AND completed_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();

    echo "完了: {$deletedCount}件の提出物データを削除しました。\n";
    echo "完了: {$deletedFiles}個の添付ファイルを削除しました。\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
