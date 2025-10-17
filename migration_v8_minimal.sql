-- バージョン8へのマイグレーション（最小版）
-- kakehashi_periodsテーブルに必要なカラムのみ追加

-- student_idカラムが存在するかチェックして追加
-- 既に存在する場合はエラーになるので、その場合は次に進む

-- kakehashi_periodsテーブルに必要なカラムを追加
ALTER TABLE kakehashi_periods
ADD COLUMN student_id INT NOT NULL COMMENT '対象生徒ID' AFTER id;

ALTER TABLE kakehashi_periods
ADD COLUMN period_number INT NOT NULL DEFAULT 1 COMMENT '期数（1期、2期...）' AFTER student_id;

ALTER TABLE kakehashi_periods
ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 1 COMMENT '自動生成フラグ' AFTER is_active;

-- 外部キー制約を追加
ALTER TABLE kakehashi_periods
ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE;

-- ユニークキー制約を追加（生徒と期数の組み合わせ）
ALTER TABLE kakehashi_periods
ADD UNIQUE KEY unique_student_period_number (student_id, period_number);
