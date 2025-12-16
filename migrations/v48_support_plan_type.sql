-- v48: 支援案の種別カラムを追加
-- 種別: 通常活動、イベント、その他

ALTER TABLE support_plans
ADD COLUMN plan_type ENUM('normal', 'event', 'other') DEFAULT 'normal' AFTER activity_name;

-- インデックスを追加
ALTER TABLE support_plans ADD INDEX idx_plan_type (plan_type);
