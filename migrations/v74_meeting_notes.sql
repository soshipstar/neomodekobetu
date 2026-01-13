-- 面談予約: 面談当日のご案内カラムを追加

ALTER TABLE meeting_requests ADD COLUMN meeting_notes TEXT DEFAULT NULL COMMENT '面談当日のご案内（持ち物、注意事項など）' AFTER purpose_detail;
