-- 平文パスワード保存用カラムを追加（管理者のみ閲覧可能）
ALTER TABLE students
ADD COLUMN password_plain VARCHAR(255) NULL COMMENT '生徒用パスワード（平文・管理者のみ閲覧可能）';

ALTER TABLE users
ADD COLUMN password_plain VARCHAR(255) NULL COMMENT 'パスワード（平文・管理者のみ閲覧可能）';
