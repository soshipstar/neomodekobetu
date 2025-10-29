-- Migration v39: メッセージ削除機能の追加

-- chat_messagesテーブルに削除フラグを追加
ALTER TABLE chat_messages
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 COMMENT '削除フラグ（0:表示, 1:削除済み）';

-- student_chat_messagesテーブルに削除フラグを追加
ALTER TABLE student_chat_messages
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 COMMENT '削除フラグ（0:表示, 1:削除済み）';
