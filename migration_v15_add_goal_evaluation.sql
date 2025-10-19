-- migration_v15: モニタリング表に目標評価カラムを追加

-- 長期目標評価カラム
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'monitoring_records'
    AND column_name = 'long_term_goal_evaluation'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE monitoring_records ADD COLUMN long_term_goal_evaluation TEXT COMMENT ''長期目標の評価・進捗'' AFTER monitoring_date',
    'SELECT "long_term_goal_evaluation column already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 短期目標評価カラム
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'monitoring_records'
    AND column_name = 'short_term_goal_evaluation'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE monitoring_records ADD COLUMN short_term_goal_evaluation TEXT COMMENT ''短期目標の評価・進捗'' AFTER long_term_goal_evaluation',
    'SELECT "short_term_goal_evaluation column already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration v15 completed: Added goal evaluation columns to monitoring_records' AS result;
