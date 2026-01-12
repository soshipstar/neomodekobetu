-- 個別支援計画に全体所感関連カラムを追加
-- v67_add_basis_content_to_plans.sql

ALTER TABLE individual_support_plans
ADD COLUMN basis_content TEXT DEFAULT NULL COMMENT '全体所感（AI生成）' AFTER overall_policy,
ADD COLUMN basis_generated_at DATETIME DEFAULT NULL COMMENT '全体所感生成日時' AFTER basis_content,
ADD COLUMN source_period_id INT DEFAULT NULL COMMENT '根拠となったかけはし期間ID' AFTER basis_generated_at;

-- インデックスを追加
ALTER TABLE individual_support_plans
ADD INDEX idx_source_period_id (source_period_id);
