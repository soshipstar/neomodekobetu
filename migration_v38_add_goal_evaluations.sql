-- モニタリング表に短期目標・長期目標の評価項目を追加（存在しない場合のみ）

-- short_term_goal_achievement カラムを追加
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE monitoring_records ADD COLUMN short_term_goal_achievement VARCHAR(50) COMMENT ''短期目標達成状況（未着手/進行中/達成/継続中等）'' AFTER overall_comment',
        'SELECT ''short_term_goal_achievement already exists'' AS message'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'monitoring_records'
    AND COLUMN_NAME = 'short_term_goal_achievement'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- short_term_goal_comment カラムを追加
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE monitoring_records ADD COLUMN short_term_goal_comment TEXT COMMENT ''短期目標に対するコメント'' AFTER short_term_goal_achievement',
        'SELECT ''short_term_goal_comment already exists'' AS message'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'monitoring_records'
    AND COLUMN_NAME = 'short_term_goal_comment'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- long_term_goal_achievement カラムを追加
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE monitoring_records ADD COLUMN long_term_goal_achievement VARCHAR(50) COMMENT ''長期目標達成状況（未着手/進行中/達成/継続中等）'' AFTER short_term_goal_comment',
        'SELECT ''long_term_goal_achievement already exists'' AS message'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'monitoring_records'
    AND COLUMN_NAME = 'long_term_goal_achievement'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- long_term_goal_comment カラムを追加
SET @query = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE monitoring_records ADD COLUMN long_term_goal_comment TEXT COMMENT ''長期目標に対するコメント'' AFTER long_term_goal_achievement',
        'SELECT ''long_term_goal_comment already exists'' AS message'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'monitoring_records'
    AND COLUMN_NAME = 'long_term_goal_comment'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
