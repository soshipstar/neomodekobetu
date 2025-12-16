-- 施設通信設定テーブル
CREATE TABLE IF NOT EXISTS `newsletter_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `classroom_id` INT NOT NULL,

    -- ヘッダー設定
    `show_facility_name` TINYINT DEFAULT 1 COMMENT '施設名を表示',
    `show_logo` TINYINT DEFAULT 1 COMMENT 'ロゴを表示',

    -- セクション表示設定
    `show_greeting` TINYINT DEFAULT 1 COMMENT '挨拶文を表示',
    `show_event_calendar` TINYINT DEFAULT 1 COMMENT 'イベントカレンダーを表示',
    `calendar_format` ENUM('list', 'table') DEFAULT 'list' COMMENT 'カレンダー形式（list=一覧, table=表形式）',
    `show_event_details` TINYINT DEFAULT 1 COMMENT 'イベント詳細を表示',
    `show_weekly_reports` TINYINT DEFAULT 1 COMMENT '活動紹介まとめを表示',
    `show_weekly_intro` TINYINT DEFAULT 1 COMMENT '曜日別活動紹介を表示',
    `show_event_results` TINYINT DEFAULT 1 COMMENT 'イベント結果報告を表示',
    `show_requests` TINYINT DEFAULT 1 COMMENT '施設からのお願いを表示',
    `show_others` TINYINT DEFAULT 1 COMMENT 'その他を表示',
    `show_elementary_report` TINYINT DEFAULT 1 COMMENT '小学生の活動報告を表示',
    `show_junior_report` TINYINT DEFAULT 1 COMMENT '中学生の活動報告を表示',

    -- デフォルト内容
    `default_requests` TEXT COMMENT '施設からのお願いのデフォルト文',
    `default_others` TEXT COMMENT 'その他のデフォルト文',

    -- AI生成設定
    `greeting_instructions` TEXT COMMENT '挨拶文生成の追加指示',
    `event_details_instructions` TEXT COMMENT 'イベント詳細生成の追加指示',
    `weekly_reports_instructions` TEXT COMMENT '活動紹介まとめ生成の追加指示',
    `weekly_intro_instructions` TEXT COMMENT '曜日別活動紹介の追加指示',
    `event_results_instructions` TEXT COMMENT 'イベント結果生成の追加指示',
    `elementary_report_instructions` TEXT COMMENT '小学生活動報告の追加指示',
    `junior_report_instructions` TEXT COMMENT '中学生活動報告の追加指示',

    -- カスタムセクション
    `custom_section_title` VARCHAR(100) DEFAULT NULL COMMENT 'カスタムセクションのタイトル',
    `custom_section_content` TEXT COMMENT 'カスタムセクションの内容',
    `show_custom_section` TINYINT DEFAULT 0 COMMENT 'カスタムセクションを表示',

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_classroom` (`classroom_id`),
    CONSTRAINT `fk_newsletter_settings_classroom`
        FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
