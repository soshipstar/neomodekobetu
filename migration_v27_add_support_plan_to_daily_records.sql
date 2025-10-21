-- daily_recordsテーブルにsupport_plan_idカラムを追加
ALTER TABLE daily_records
ADD COLUMN support_plan_id INT DEFAULT NULL COMMENT '使用した支援案ID' AFTER staff_id,
ADD FOREIGN KEY (support_plan_id) REFERENCES support_plans(id) ON DELETE SET NULL,
ADD INDEX idx_support_plan_id (support_plan_id);
