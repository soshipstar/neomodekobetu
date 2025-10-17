-- バージョン8へのマイグレーション（修正版）
-- かけはしを自動生成システムに変更

-- studentsテーブルに初回かけはし作成日を追加
ALTER TABLE students
ADD COLUMN kakehashi_initial_date DATE DEFAULT NULL COMMENT '初回かけはし作成日（半年ごとに自動更新）'
AFTER birth_date;

-- kakehashi_periodsテーブルに必要なカラムを追加
ALTER TABLE kakehashi_periods
ADD COLUMN student_id INT NOT NULL COMMENT '対象生徒ID' AFTER id,
ADD COLUMN period_number INT NOT NULL DEFAULT 1 COMMENT '期数（1期、2期...）' AFTER student_id,
ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 1 COMMENT '自動生成フラグ' AFTER is_active;

-- 外部キー制約を追加
ALTER TABLE kakehashi_periods
ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE;

-- 既存のユニークキー制約を更新（生徒と期数の組み合わせ）
ALTER TABLE kakehashi_periods
ADD UNIQUE KEY unique_student_period_number (student_id, period_number);
