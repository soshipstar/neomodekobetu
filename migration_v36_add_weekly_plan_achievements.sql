-- 週間計画表に達成度評価カラムを追加

ALTER TABLE weekly_plans
ADD COLUMN weekly_goal_achievement TINYINT DEFAULT 0 COMMENT '今週の目標の達成度 (0:未評価, 1:未達成, 2:一部達成, 3:達成)' AFTER want_to_do,
ADD COLUMN weekly_goal_comment TEXT NULL COMMENT '今週の目標のコメント' AFTER weekly_goal_achievement,
ADD COLUMN shared_goal_achievement TINYINT DEFAULT 0 COMMENT 'いっしょに決めた目標の達成度' AFTER weekly_goal_comment,
ADD COLUMN shared_goal_comment TEXT NULL COMMENT 'いっしょに決めた目標のコメント' AFTER shared_goal_achievement,
ADD COLUMN must_do_achievement TINYINT DEFAULT 0 COMMENT 'やるべきことの達成度' AFTER shared_goal_comment,
ADD COLUMN must_do_comment TEXT NULL COMMENT 'やるべきことのコメント' AFTER must_do_achievement,
ADD COLUMN should_do_achievement TINYINT DEFAULT 0 COMMENT 'やったほうがいいことの達成度' AFTER must_do_comment,
ADD COLUMN should_do_comment TEXT NULL COMMENT 'やったほうがいいことのコメント' AFTER should_do_achievement,
ADD COLUMN want_to_do_achievement TINYINT DEFAULT 0 COMMENT 'やりたいことの達成度' AFTER should_do_comment,
ADD COLUMN want_to_do_comment TEXT NULL COMMENT 'やりたいことのコメント' AFTER want_to_do_achievement,
ADD COLUMN daily_achievement JSON NULL COMMENT '各曜日の達成度データ' AFTER want_to_do_comment,
ADD COLUMN overall_comment TEXT NULL COMMENT '週全体の総合コメント' AFTER daily_achievement,
ADD COLUMN evaluated_at DATETIME NULL COMMENT '評価日時' AFTER overall_comment,
ADD COLUMN evaluated_by_type ENUM('staff', 'admin') NULL COMMENT '評価者タイプ' AFTER evaluated_at,
ADD COLUMN evaluated_by_id INT NULL COMMENT '評価者ID' AFTER evaluated_by_type;
