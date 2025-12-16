-- 活動種マスタテーブル
CREATE TABLE IF NOT EXISTS `activity_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `classroom_id` INT NOT NULL,
    `activity_name` VARCHAR(100) NOT NULL COMMENT '活動名',
    `day_type` ENUM('weekday', 'holiday', 'both') DEFAULT 'both' COMMENT '対象日種別（weekday=平日, holiday=学校休業日, both=両方）',
    `description` TEXT COMMENT '活動の説明',
    `display_order` INT DEFAULT 0 COMMENT '表示順',
    `is_active` TINYINT DEFAULT 1 COMMENT '有効フラグ',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_classroom` (`classroom_id`),
    KEY `idx_day_type` (`day_type`),
    CONSTRAINT `fk_activity_types_classroom`
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
