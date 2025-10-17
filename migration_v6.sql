-- バージョン6へのマイグレーション
-- 生徒テーブルに生年月日フィールドを追加

ALTER TABLE students
ADD COLUMN birth_date DATE DEFAULT NULL COMMENT '生年月日'
AFTER student_name;
