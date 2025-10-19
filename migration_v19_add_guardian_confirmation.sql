-- Migration v19: 保護者確認機能の追加
-- 個別支援計画書とモニタリング表に保護者の確認済みフラグと確認日時を追加

-- 個別支援計画書テーブルに保護者確認フィールドを追加
ALTER TABLE individual_support_plans
ADD COLUMN guardian_confirmed TINYINT(1) DEFAULT 0 COMMENT '保護者確認済みフラグ（0:未確認, 1:確認済み）',
ADD COLUMN guardian_confirmed_at DATETIME NULL COMMENT '保護者確認日時';

-- モニタリング表テーブルに保護者確認フィールドを追加
ALTER TABLE monitoring_records
ADD COLUMN guardian_confirmed TINYINT(1) DEFAULT 0 COMMENT '保護者確認済みフラグ（0:未確認, 1:確認済み）',
ADD COLUMN guardian_confirmed_at DATETIME NULL COMMENT '保護者確認日時';

-- 既存のレコードは未確認として扱う（デフォルト値が適用される）
