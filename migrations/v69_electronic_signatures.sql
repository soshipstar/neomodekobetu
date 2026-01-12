-- 電子署名機能のためのカラム追加
-- 個別支援計画書とモニタリング表に保護者・職員の署名データを保存

-- 個別支援計画書に署名カラム追加
ALTER TABLE individual_support_plans
    ADD COLUMN guardian_signature_image MEDIUMTEXT DEFAULT NULL COMMENT 'Base64エンコードされた保護者署名画像',
    ADD COLUMN guardian_signature_date DATE DEFAULT NULL COMMENT '保護者署名日',
    ADD COLUMN staff_signature_image MEDIUMTEXT DEFAULT NULL COMMENT 'Base64エンコードされた職員署名画像',
    ADD COLUMN staff_signature_date DATE DEFAULT NULL COMMENT '職員署名日',
    ADD COLUMN staff_signer_name VARCHAR(100) DEFAULT NULL COMMENT '職員署名者名';

-- モニタリング表に署名カラム追加
ALTER TABLE monitoring_records
    ADD COLUMN guardian_signature_image MEDIUMTEXT DEFAULT NULL COMMENT 'Base64エンコードされた保護者署名画像',
    ADD COLUMN guardian_signature_date DATE DEFAULT NULL COMMENT '保護者署名日',
    ADD COLUMN staff_signature_image MEDIUMTEXT DEFAULT NULL COMMENT 'Base64エンコードされた職員署名画像',
    ADD COLUMN staff_signature_date DATE DEFAULT NULL COMMENT '職員署名日',
    ADD COLUMN staff_signer_name VARCHAR(100) DEFAULT NULL COMMENT '職員署名者名',
    ADD COLUMN is_official TINYINT(1) DEFAULT 0 COMMENT '正式版フラグ';
