-- バージョン8へのマイグレーション
-- かけはしを自動生成システムに変更

-- studentsテーブルに初回かけはし作成日を追加
ALTER TABLE students
ADD COLUMN kakehashi_initial_date DATE DEFAULT NULL COMMENT '初回かけはし作成日（半年ごとに自動更新）'
AFTER birth_date;

-- kakehashi_periodsテーブルの構造を変更
-- 自動生成フラグと期数を追加
ALTER TABLE kakehashi_periods
ADD COLUMN period_number INT NOT NULL DEFAULT 1 COMMENT '期数（1期、2期...）' AFTER student_id,
ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 1 COMMENT '自動生成フラグ' AFTER is_active;

-- 既存のユニークキー制約を削除して新しいものを追加
ALTER TABLE kakehashi_periods
DROP INDEX unique_student_period,
ADD UNIQUE KEY unique_student_period_number (student_id, period_number);
