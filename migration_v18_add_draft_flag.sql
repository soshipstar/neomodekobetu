-- モニタリング表と個別支援計画書に下書きフラグを追加
-- 下書き保存時はスタッフのみ閲覧可能、提出時は保護者も閲覧可能にする

-- モニタリングテーブルに下書きフラグを追加
ALTER TABLE monitoring_records
ADD COLUMN is_draft TINYINT(1) DEFAULT 1 COMMENT '下書きフラグ（0:提出済み, 1:下書き）';

-- 個別支援計画書テーブルに下書きフラグを追加
ALTER TABLE individual_support_plans
ADD COLUMN is_draft TINYINT(1) DEFAULT 1 COMMENT '下書きフラグ（0:提出済み, 1:下書き）';

-- 既存のレコードはすべて提出済みとして扱う
UPDATE monitoring_records SET is_draft = 0 WHERE is_draft IS NULL;
UPDATE individual_support_plans SET is_draft = 0 WHERE is_draft IS NULL;
