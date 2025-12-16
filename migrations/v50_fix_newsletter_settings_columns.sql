-- newsletter_settings テーブルに不足しているカラムを追加
-- 既存のテーブルがある場合に安全に追加

-- テーブルが存在しない場合は作成
CREATE TABLE IF NOT EXISTS `newsletter_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `classroom_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_classroom` (`classroom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 不足カラムを追加（既に存在する場合はエラーを無視）
ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_facility_name` TINYINT DEFAULT 1 AFTER `classroom_id`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_logo` TINYINT DEFAULT 1 AFTER `show_facility_name`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_greeting` TINYINT DEFAULT 1 AFTER `show_logo`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_event_calendar` TINYINT DEFAULT 1 AFTER `show_greeting`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `calendar_format` ENUM('list', 'table') DEFAULT 'list' AFTER `show_event_calendar`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_event_details` TINYINT DEFAULT 1 AFTER `calendar_format`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_weekly_reports` TINYINT DEFAULT 1 AFTER `show_event_details`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_weekly_intro` TINYINT DEFAULT 1 AFTER `show_weekly_reports`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_event_results` TINYINT DEFAULT 1 AFTER `show_weekly_intro`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_requests` TINYINT DEFAULT 1 AFTER `show_event_results`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_others` TINYINT DEFAULT 1 AFTER `show_requests`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_elementary_report` TINYINT DEFAULT 1 AFTER `show_others`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_junior_report` TINYINT DEFAULT 1 AFTER `show_elementary_report`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `default_requests` TEXT AFTER `show_junior_report`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `default_others` TEXT AFTER `default_requests`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `greeting_instructions` TEXT AFTER `default_others`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `event_details_instructions` TEXT AFTER `greeting_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `weekly_reports_instructions` TEXT AFTER `event_details_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `weekly_intro_instructions` TEXT AFTER `weekly_reports_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `event_results_instructions` TEXT AFTER `weekly_intro_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `elementary_report_instructions` TEXT AFTER `event_results_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `junior_report_instructions` TEXT AFTER `elementary_report_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `custom_section_title` VARCHAR(100) DEFAULT NULL AFTER `junior_report_instructions`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `custom_section_content` TEXT AFTER `custom_section_title`;

ALTER TABLE `newsletter_settings`
    ADD COLUMN `show_custom_section` TINYINT DEFAULT 0 AFTER `custom_section_content`;
