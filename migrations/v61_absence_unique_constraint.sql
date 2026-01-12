-- absence_notifications テーブルに一意制約を追加
-- v61_absence_unique_constraint.sql
--
-- 問題: ON DUPLICATE KEY UPDATE が正しく動作しないため、
--       (student_id, absence_date) に一意制約を追加

-- 既存の重複データを削除（最新のものを残す）
DELETE a1 FROM absence_notifications a1
INNER JOIN absence_notifications a2
WHERE a1.student_id = a2.student_id
  AND a1.absence_date = a2.absence_date
  AND a1.id < a2.id;

-- 一意制約を追加
ALTER TABLE absence_notifications
ADD UNIQUE KEY unique_student_absence_date (student_id, absence_date);
