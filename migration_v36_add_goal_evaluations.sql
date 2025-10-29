-- モニタリング表に短期目標・長期目標の評価項目を追加

ALTER TABLE monitoring_records
    ADD COLUMN short_term_goal_achievement VARCHAR(50) COMMENT '短期目標達成状況（未着手/進行中/達成/継続中等）' AFTER overall_comment,
    ADD COLUMN short_term_goal_comment TEXT COMMENT '短期目標に対するコメント' AFTER short_term_goal_achievement,
    ADD COLUMN long_term_goal_achievement VARCHAR(50) COMMENT '長期目標達成状況（未着手/進行中/達成/継続中等）' AFTER short_term_goal_comment,
    ADD COLUMN long_term_goal_comment TEXT COMMENT '長期目標に対するコメント' AFTER long_term_goal_achievement;
