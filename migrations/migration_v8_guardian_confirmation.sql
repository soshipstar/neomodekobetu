-- 保護者確認機能の追加
-- integrated_notesテーブルに保護者確認のカラムを追加

ALTER TABLE integrated_notes
ADD COLUMN guardian_confirmed TINYINT(1) DEFAULT 0 COMMENT '保護者確認済みフラグ(0=未確認, 1=確認済み)',
ADD COLUMN guardian_confirmed_at DATETIME DEFAULT NULL COMMENT '保護者確認日時';

-- インデックスを追加（未確認の連絡帳を検索しやすくする）
CREATE INDEX idx_guardian_confirmed ON integrated_notes(guardian_confirmed, is_sent);
