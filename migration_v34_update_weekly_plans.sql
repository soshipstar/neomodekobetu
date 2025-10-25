-- 週間計画表テーブルを更新
ALTER TABLE weekly_plans
ADD COLUMN weekly_goal TEXT NULL COMMENT '今週の目標' AFTER week_start_date,
ADD COLUMN shared_goal TEXT NULL COMMENT 'いっしょに決めた目標' AFTER weekly_goal,
ADD COLUMN must_do TEXT NULL COMMENT 'やるべきこと' AFTER shared_goal,
ADD COLUMN should_do TEXT NULL COMMENT 'やったほうがいいこと' AFTER must_do,
ADD COLUMN want_to_do TEXT NULL COMMENT 'やりたいこと' AFTER should_do,
MODIFY COLUMN plan_data JSON NULL COMMENT '各曜日の計画・目標データ';
