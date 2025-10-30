<?php
/**
 * 古いデータを一括削除するマスタークリーンアップスクリプト
 *
 * このスクリプトは以下を実行します:
 * 1. 完了から半年（6か月）以上経過した提出物データを削除
 * 2. 3か月以上経過したチャット添付ファイルを削除
 *
 * cron設定例（毎日午前2時に実行）:
 * 0 2 * * * cd /path/to/kobetu && php cleanup_all.php
 */

echo "=== データクリーンアップ開始 ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// 1. 提出物データのクリーンアップ
echo "【1】提出物データのクリーンアップ\n";
echo "--------------------------------------\n";
include __DIR__ . '/cleanup_old_submissions.php';
echo "\n";

// 2. チャット添付ファイルのクリーンアップ
echo "【2】チャット添付ファイルのクリーンアップ\n";
echo "--------------------------------------\n";
include __DIR__ . '/cleanup_old_chat_attachments.php';
echo "\n";

echo "=== データクリーンアップ完了 ===\n";
echo date('Y-m-d H:i:s') . "\n";
