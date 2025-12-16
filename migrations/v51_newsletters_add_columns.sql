-- newsletters テーブルに不足しているカラムを追加

-- 曜日別活動紹介
ALTER TABLE `newsletters`
ADD COLUMN `weekly_intro` TEXT NULL AFTER `weekly_reports`;

-- 小学生の活動報告
ALTER TABLE `newsletters`
ADD COLUMN `elementary_report` TEXT NULL AFTER `event_results`;

-- 中学生・高校生の活動報告
ALTER TABLE `newsletters`
ADD COLUMN `junior_report` TEXT NULL AFTER `elementary_report`;
