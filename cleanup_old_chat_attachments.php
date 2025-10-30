<?php
/**
 * チャット添付ファイルを3か月後に削除するスクリプト
 *
 * cron設定例（毎日午前3時に実行）:
 * 0 3 * * * cd /path/to/kobetu && php cleanup_old_chat_attachments.php
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    // 3か月以上経過した保護者チャットの添付ファイルを取得
    $stmt = $pdo->prepare("
        SELECT id, attachment_path
        FROM chat_messages
        WHERE attachment_path IS NOT NULL
            AND attachment_path != ''
            AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ");
    $stmt->execute();
    $oldGuardianAttachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3か月以上経過した生徒チャットの添付ファイルを取得
    $stmt = $pdo->prepare("
        SELECT id, attachment_path
        FROM student_chat_messages
        WHERE attachment_path IS NOT NULL
            AND attachment_path != ''
            AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ");
    $stmt->execute();
    $oldStudentAttachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedFiles = 0;
    $failedFiles = 0;

    // 保護者チャットの添付ファイルを削除
    foreach ($oldGuardianAttachments as $attachment) {
        $filePath = __DIR__ . '/' . $attachment['attachment_path'];
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $deletedFiles++;
                echo "削除: {$attachment['attachment_path']}\n";
            } else {
                $failedFiles++;
                echo "警告: ファイル削除失敗: {$attachment['attachment_path']}\n";
            }
        }
    }

    // 生徒チャットの添付ファイルを削除
    foreach ($oldStudentAttachments as $attachment) {
        $filePath = __DIR__ . '/' . $attachment['attachment_path'];
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $deletedFiles++;
                echo "削除: {$attachment['attachment_path']}\n";
            } else {
                $failedFiles++;
                echo "警告: ファイル削除失敗: {$attachment['attachment_path']}\n";
            }
        }
    }

    // データベースの添付ファイル情報をクリア（メッセージ自体は残す）
    $stmt = $pdo->prepare("
        UPDATE chat_messages
        SET attachment_path = NULL,
            attachment_original_name = NULL,
            attachment_size = NULL,
            attachment_type = NULL
        WHERE attachment_path IS NOT NULL
            AND attachment_path != ''
            AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ");
    $stmt->execute();
    $clearedGuardianRecords = $stmt->rowCount();

    $stmt = $pdo->prepare("
        UPDATE student_chat_messages
        SET attachment_path = NULL,
            attachment_original_name = NULL,
            attachment_size = NULL,
            attachment_type = NULL
        WHERE attachment_path IS NOT NULL
            AND attachment_path != ''
            AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ");
    $stmt->execute();
    $clearedStudentRecords = $stmt->rowCount();

    echo "\n完了: {$deletedFiles}個の添付ファイルを削除しました。\n";
    if ($failedFiles > 0) {
        echo "警告: {$failedFiles}個のファイル削除に失敗しました。\n";
    }
    echo "完了: {$clearedGuardianRecords}件の保護者チャットメッセージから添付ファイル情報をクリアしました。\n";
    echo "完了: {$clearedStudentRecords}件の生徒チャットメッセージから添付ファイル情報をクリアしました。\n";

} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
