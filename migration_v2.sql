-- バージョン2へのマイグレーション
-- 1. 生徒テーブルに学年区分を追加
-- 2. 1日複数活動対応のためのテーブル構造変更

-- 1. 生徒テーブルに学年区分カラムを追加
ALTER TABLE students
ADD COLUMN grade_level ENUM('elementary', 'junior_high', 'high_school') NOT NULL DEFAULT 'elementary'
COMMENT '小学生、中学生、高校生'
AFTER student_name;

-- 既存の生徒データに学年区分を設定（サンプルデータ）
UPDATE students SET grade_level = 'elementary' WHERE student_name = '山田太郎';
UPDATE students SET grade_level = 'junior_high' WHERE student_name = '佐藤花子';

-- 2. daily_recordsテーブルに活動名カラムを追加（複数活動対応）
ALTER TABLE daily_records
ADD COLUMN activity_name VARCHAR(255) NOT NULL DEFAULT '活動1' COMMENT '活動名'
AFTER record_date;

-- UNIQUE制約を削除して、同じ日に複数の活動を登録できるようにする
ALTER TABLE daily_records
DROP INDEX unique_date_staff;

-- 新しいUNIQUE制約を追加（日付 + スタッフ + 活動名）
ALTER TABLE daily_records
ADD UNIQUE KEY unique_date_staff_activity (record_date, staff_id, activity_name);

-- インデックスを追加（学年区分での検索用）
ALTER TABLE students
ADD INDEX idx_grade_level (grade_level);
