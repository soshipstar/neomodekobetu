-- バージョン2.1へのマイグレーション
-- 生徒記録に「本日の様子」カラムを追加

ALTER TABLE student_records
ADD COLUMN daily_note TEXT COMMENT '本日の様子'
AFTER daily_record_id;
