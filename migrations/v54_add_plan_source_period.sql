-- 個別支援計画の根拠追跡用カラムを追加
-- 2024-12-16

-- 計画がどのかけはし期間から生成されたかを記録
ALTER TABLE individual_support_plans
ADD COLUMN source_period_id INT NULL COMMENT '根拠となったかけはし期間ID' AFTER guardian_confirmed_at,
ADD COLUMN source_monitoring_id INT NULL COMMENT '根拠となったモニタリングID' AFTER source_period_id,
ADD COLUMN basis_generated_at DATETIME NULL COMMENT '根拠文書が生成された日時' AFTER source_monitoring_id,
ADD COLUMN basis_content TEXT NULL COMMENT 'AI生成された根拠文書の内容' AFTER basis_generated_at;

-- インデックス追加
CREATE INDEX idx_plans_source_period ON individual_support_plans(source_period_id);
CREATE INDEX idx_plans_source_monitoring ON individual_support_plans(source_monitoring_id);
