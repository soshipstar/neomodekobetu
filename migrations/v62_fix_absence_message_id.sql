-- absence_notifications の message_id を NULL 許可に変更
-- v62_fix_absence_message_id.sql
--
-- 問題: message_id が NOT NULL + 外部キー制約があり、
--       スタッフによるキャンセル時に message_id なしで挿入できない

-- message_id を NULL 許可に変更
ALTER TABLE absence_notifications MODIFY COLUMN message_id INT NULL;
