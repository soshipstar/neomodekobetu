-- 管理者アカウントとマスター管理者アカウントの作成

-- 1. 既存のadmin/admin123ユーザーを管理者に変更
UPDATE users
SET user_type = 'admin',
    is_master = 0
WHERE username = 'admin' AND user_type = 'staff';

-- 2. マスター管理者アカウントを作成
-- パスワード: master123 のハッシュ値
INSERT INTO users (username, password, full_name, email, user_type, is_active, is_master, classroom_id, created_at)
SELECT 'master', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'マスター管理者', 'master@example.com', 'admin', 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'master');

-- 3. 通常の管理者アカウントを作成（admin123はそのまま）
-- パスワード: admin123 のハッシュ値
INSERT INTO users (username, password, full_name, email, user_type, is_active, is_master, classroom_id, created_at)
SELECT 'administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理者', 'admin@example.com', 'admin', 1, 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'administrator');

-- 4. 確認用: ユーザー一覧を表示
SELECT
    id,
    username,
    full_name,
    user_type,
    is_master,
    classroom_id,
    is_active
FROM users
WHERE user_type = 'admin' OR username = 'admin'
ORDER BY is_master DESC, username;
