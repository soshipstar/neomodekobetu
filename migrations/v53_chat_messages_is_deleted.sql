-- chat_messages テーブルに is_deleted カラムを追加
-- メッセージの論理削除用

ALTER TABLE `chat_messages`
ADD COLUMN IF NOT EXISTS `is_deleted` TINYINT(1) DEFAULT 0 AFTER `is_read`;

-- インデックス追加（削除されていないメッセージの取得を高速化）
ALTER TABLE `chat_messages`
ADD INDEX IF NOT EXISTS `idx_is_deleted` (`is_deleted`);
