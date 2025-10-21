-- support_plansテーブルにactivity_dateカラムを追加
-- 既存データのために一時的にNULLを許可してから、デフォルト値を設定
ALTER TABLE support_plans
ADD COLUMN activity_date DATE DEFAULT NULL COMMENT '活動予定日' AFTER activity_name;

-- 既存のデータにcreated_atの日付を設定
UPDATE support_plans
SET activity_date = DATE(created_at)
WHERE activity_date IS NULL;

-- カラムをNOT NULLに変更
ALTER TABLE support_plans
MODIFY COLUMN activity_date DATE NOT NULL COMMENT '活動予定日';

-- インデックスを追加
ALTER TABLE support_plans
ADD INDEX idx_activity_date (activity_date);
