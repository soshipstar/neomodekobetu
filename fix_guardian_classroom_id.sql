-- データベースを選択
USE `_kobetudb`;

-- classroom_idがNULLの保護者を確認
SELECT '=== classroom_idがNULLの保護者一覧 ===' AS info;
SELECT
    u.id,
    u.username,
    u.full_name,
    u.classroom_id
FROM users u
WHERE u.user_type = 'guardian' AND u.classroom_id IS NULL;

-- スタッフ・管理者の教室IDを確認
SELECT '=== スタッフ・管理者の教室ID ===' AS info;
SELECT DISTINCT classroom_id
FROM users
WHERE user_type IN ('staff', 'admin') AND classroom_id IS NOT NULL;

-- classroom_idがNULLの保護者を、最初のスタッフの教室に更新
UPDATE users
SET classroom_id = (
    SELECT classroom_id
    FROM users
    WHERE user_type IN ('staff', 'admin') AND classroom_id IS NOT NULL
    LIMIT 1
)
WHERE user_type = 'guardian' AND classroom_id IS NULL;

-- 更新結果を確認
SELECT '=== 更新後の保護者一覧 ===' AS info;
SELECT
    u.id,
    u.username,
    u.full_name,
    u.classroom_id,
    c.classroom_name
FROM users u
LEFT JOIN classrooms c ON u.classroom_id = c.id
WHERE u.user_type = 'guardian'
ORDER BY u.classroom_id, u.id;
