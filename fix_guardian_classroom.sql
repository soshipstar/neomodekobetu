-- 既存保護者アカウントに教室IDを設定

-- 1. 現在の状況を確認
SELECT
    u.id,
    u.username,
    u.full_name,
    u.classroom_id,
    c.classroom_name
FROM users u
LEFT JOIN classrooms c ON u.classroom_id = c.id
WHERE u.user_type = 'guardian'
ORDER BY u.id;

-- 2. classroom_idがNULLの保護者に教室ID=1を設定
UPDATE users
SET classroom_id = 1
WHERE user_type = 'guardian' AND classroom_id IS NULL;

-- 3. 更新後の確認
SELECT
    u.id,
    u.username,
    u.full_name,
    u.classroom_id,
    c.classroom_name
FROM users u
LEFT JOIN classrooms c ON u.classroom_id = c.id
WHERE u.user_type = 'guardian'
ORDER BY u.id;
