-- バージョン8へのマイグレーション（安全版）
-- 既存データがある場合も対応

-- studentsテーブルにkakehashi_initial_dateカラムを追加（既に存在する場合はスキップ）
-- ALTER TABLE students
-- ADD COLUMN kakehashi_initial_date DATE DEFAULT NULL COMMENT '初回かけはし作成日（半年ごとに自動更新）'
-- AFTER birth_date;

-- kakehashi_periodsテーブルにstudent_idカラムを追加
-- 既存データがある場合はデフォルト値0を設定（後で手動修正が必要）
ALTER TABLE kakehashi_periods
ADD COLUMN student_id INT NOT NULL DEFAULT 0 COMMENT '対象生徒ID' AFTER id;

-- kakehashi_periodsテーブルにperiod_numberカラムを追加
ALTER TABLE kakehashi_periods
ADD COLUMN period_number INT NOT NULL DEFAULT 1 COMMENT '期数（1期、2期...）' AFTER student_id;

-- kakehashi_periodsテーブルにis_auto_generatedカラムを追加
ALTER TABLE kakehashi_periods
ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 0 COMMENT '自動生成フラグ（既存データは0=手動）' AFTER is_active;

-- 外部キー制約を追加（student_id=0のデータがある場合は先に修正してから実行）
-- ALTER TABLE kakehashi_periods
-- ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE;

-- ユニークキー制約を追加（重複データがないことを確認してから実行）
-- ALTER TABLE kakehashi_periods
-- ADD UNIQUE KEY unique_student_period_number (student_id, period_number);
