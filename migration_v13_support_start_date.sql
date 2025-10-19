-- マイグレーション v13: 生徒に支援開始日を追加、かけはし自動生成ロジック

-- 1. studentsテーブルにsupport_start_date列を追加（既存チェック付き）
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'students' AND column_name = 'support_start_date');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN support_start_date DATE COMMENT ''支援開始日'' AFTER birth_date',
    'SELECT ''support_start_date column already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 既存生徒の支援開始日をcreated_atの日付で設定（NULLの場合のみ）
UPDATE students
SET support_start_date = DATE(created_at)
WHERE support_start_date IS NULL;

-- 3. 確認
SELECT
    id,
    student_name,
    birth_date,
    support_start_date,
    created_at
FROM students
ORDER BY id
LIMIT 10;
