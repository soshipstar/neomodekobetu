-- absence_notificationsテーブルに振替依頼関連カラムを追加

ALTER TABLE absence_notifications
ADD COLUMN makeup_request_date DATE NULL COMMENT '振替希望日' AFTER reason,
ADD COLUMN makeup_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none' COMMENT '振替ステータス(none:なし, pending:承認待ち, approved:承認済み, rejected:却下)' AFTER makeup_request_date,
ADD COLUMN makeup_approved_by INT NULL COMMENT '承認したスタッフID' AFTER makeup_status,
ADD COLUMN makeup_approved_at DATETIME NULL COMMENT '承認日時' AFTER makeup_approved_by,
ADD COLUMN makeup_note TEXT NULL COMMENT '振替に関するメモ（スタッフ用）' AFTER makeup_approved_at,
ADD INDEX idx_makeup_status (makeup_status),
ADD INDEX idx_makeup_request_date (makeup_request_date),
ADD FOREIGN KEY (makeup_approved_by) REFERENCES users(id) ON DELETE SET NULL;
