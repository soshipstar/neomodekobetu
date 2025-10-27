-- 生徒用ログイン情報を追加
ALTER TABLE students
ADD COLUMN username VARCHAR(50) NULL UNIQUE COMMENT '生徒用ログインID',
ADD COLUMN password_hash VARCHAR(255) NULL COMMENT '生徒用パスワード（ハッシュ化）',
ADD COLUMN password_plain VARCHAR(255) NULL COMMENT '生徒用パスワード（平文・表示用）',
ADD COLUMN last_login DATETIME NULL COMMENT '最終ログイン日時';
