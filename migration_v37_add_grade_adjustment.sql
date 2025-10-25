-- 生徒テーブルに学年調整カラムを追加

ALTER TABLE students
ADD COLUMN grade_adjustment INT DEFAULT 0 COMMENT '学年調整 (-2, -1, 0, 1, 2)' AFTER grade_level;
