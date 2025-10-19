-- migration_v14: kakehashi_periodsのユニーク制約を削除
-- 新しいかけはし自動生成システムでは期間番号を使わないため、この制約は不要

-- ユニーク制約が存在するか確認
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = 'kakehashi_periods'
    AND index_name = 'unique_student_period_number'
);

-- 制約が存在する場合のみ削除
SET @sql = IF(
    @constraint_exists > 0,
    'ALTER TABLE kakehashi_periods DROP INDEX unique_student_period_number',
    'SELECT "unique_student_period_number constraint does not exist" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- period_numberカラムも不要なので削除（存在する場合のみ）
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'kakehashi_periods'
    AND column_name = 'period_number'
);

SET @sql = IF(
    @col_exists > 0,
    'ALTER TABLE kakehashi_periods DROP COLUMN period_number',
    'SELECT "period_number column does not exist" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration v14 completed: Removed unique constraint and period_number column' AS result;
