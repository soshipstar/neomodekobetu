-- モニタリング表に保護者確認カラムを追加
ALTER TABLE monitoring_records
    ADD COLUMN guardian_confirmed TINYINT(1) DEFAULT 0 COMMENT '保護者確認済みフラグ(0=未確認, 1=確認済み)',
    ADD COLUMN guardian_confirmed_at DATETIME DEFAULT NULL COMMENT '保護者確認日時';
