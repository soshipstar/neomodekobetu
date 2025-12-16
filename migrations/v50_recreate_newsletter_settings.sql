-- newsletter_settings テーブルを再作成
-- 注意: 既存のデータは削除されます

DROP TABLE IF EXISTS `newsletter_settings`;

CREATE TABLE `newsletter_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `classroom_id` INT NOT NULL,
    `show_facility_name` TINYINT DEFAULT 1,
    `show_logo` TINYINT DEFAULT 1,
    `show_greeting` TINYINT DEFAULT 1,
    `show_event_calendar` TINYINT DEFAULT 1,
    `calendar_format` ENUM('list', 'table') DEFAULT 'list',
    `show_event_details` TINYINT DEFAULT 1,
    `show_weekly_reports` TINYINT DEFAULT 1,
    `show_weekly_intro` TINYINT DEFAULT 1,
    `show_event_results` TINYINT DEFAULT 1,
    `show_requests` TINYINT DEFAULT 1,
    `show_others` TINYINT DEFAULT 1,
    `show_elementary_report` TINYINT DEFAULT 1,
    `show_junior_report` TINYINT DEFAULT 1,
    `default_requests` TEXT,
    `default_others` TEXT,
    `greeting_instructions` TEXT,
    `event_details_instructions` TEXT,
    `weekly_reports_instructions` TEXT,
    `weekly_intro_instructions` TEXT,
    `event_results_instructions` TEXT,
    `elementary_report_instructions` TEXT,
    `junior_report_instructions` TEXT,
    `custom_section_title` VARCHAR(100) DEFAULT NULL,
    `custom_section_content` TEXT,
    `show_custom_section` TINYINT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_classroom` (`classroom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
