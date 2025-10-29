-- Migration v40: 生徒チャットに既読機能を追加

-- student_chat_messagesテーブルにis_readカラムを追加
ALTER TABLE student_chat_messages
ADD COLUMN is_read TINYINT(1) DEFAULT 0 COMMENT '既読フラグ（0:未読, 1:既読）',
ADD INDEX idx_unread (room_id, is_read);
