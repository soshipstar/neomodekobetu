-- ユーザーアカウントのみを作成するSQL
-- テーブルは既に存在する前提

-- 既存ユーザーを削除（再セットアップ用）
-- DELETE FROM users;

-- 管理者アカウント（admin / admin123）
INSERT INTO users (username, password, full_name, user_type, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理者', 'admin', 'admin@example.com')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- スタッフアカウント（staff01 / staff123）
INSERT INTO users (username, password, full_name, user_type, email) VALUES
('staff01', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'スタッフ01', 'staff', 'staff01@example.com')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- 保護者アカウント（guardian01 / guardian123）
INSERT INTO users (username, password, full_name, user_type, email) VALUES
('guardian01', '$2y$10$E9xYz5z/IKWxK5CwXL7ZOuKX8z1Z9qP7R8QqL7K5X0Y8Z9P7Q8R9S', '保護者01', 'guardian', 'guardian01@example.com')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- サンプル生徒データ（guardian_id = 3 は保護者01を想定）
INSERT INTO students (student_name, guardian_id, birth_date) VALUES
('山田太郎', 3, '2015-04-15'),
('佐藤花子', 3, '2016-08-20')
ON DUPLICATE KEY UPDATE student_name = VALUES(student_name);
