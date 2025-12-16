-- 学校休業日活動テーブル
-- 学校が休みの日（夏休み、春休み等）に施設が活動する日を管理

CREATE TABLE IF NOT EXISTS `school_holiday_activities` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `activity_date` DATE NOT NULL COMMENT '学校休業日活動の日付',
    `classroom_id` INT NOT NULL COMMENT '教室ID',
    `note` VARCHAR(255) DEFAULT NULL COMMENT 'メモ（例：夏休み、春休み等）',
    `created_by` INT NOT NULL COMMENT '登録者ID',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_date_classroom` (`activity_date`, `classroom_id`),
    KEY `idx_classroom` (`classroom_id`),
    KEY `idx_date` (`activity_date`),
    CONSTRAINT `fk_school_holiday_classroom`
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_school_holiday_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
