-- かけはし期間テーブルの更新
-- 個別生徒ごとにかけはし期間を設定できるように変更

ALTER TABLE kakehashi_periods
ADD COLUMN student_id INT NOT NULL COMMENT '対象生徒ID' AFTER id,
ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
DROP INDEX unique_period_student,
ADD UNIQUE KEY unique_student_period (student_id, start_date);

-- 既存のユニークキー制約を更新
ALTER TABLE kakehashi_guardian
DROP INDEX unique_period_student;

ALTER TABLE kakehashi_staff
DROP INDEX unique_period_student_staff;

-- 新しいユニークキー制約を追加（生徒と期間の組み合わせ）
ALTER TABLE kakehashi_guardian
ADD UNIQUE KEY unique_period_student (period_id, student_id);

ALTER TABLE kakehashi_staff
ADD UNIQUE KEY unique_period_student (period_id, student_id);
