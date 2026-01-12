-- 個別支援計画書の保護者レビューコメント機能追加
-- 保護者が計画書案を確認し、変更希望のコメントを送信できるようにする

ALTER TABLE individual_support_plans
    ADD COLUMN guardian_review_comment TEXT DEFAULT NULL COMMENT '保護者からの変更希望コメント',
    ADD COLUMN guardian_review_comment_at DATETIME DEFAULT NULL COMMENT '保護者コメント送信日時',
    ADD COLUMN is_official TINYINT(1) DEFAULT 0 COMMENT '正式版フラグ（0=案、1=正式版）';
