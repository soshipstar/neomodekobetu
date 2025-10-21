-- データベースを選択
USE `_kobetudb`;

-- 教室一覧を確認
SELECT '=== 現在の教室一覧 ===' AS info;
SELECT id, classroom_name FROM classrooms;

-- 「narZE」教室のIDを確認
SELECT '=== narZE教室のID ===' AS info;
SELECT id, classroom_name FROM classrooms WHERE classroom_name = 'narZE';

-- 現在の保護者の所属教室を確認
SELECT '=== 変更前の保護者一覧 ===' AS info;
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

-- 全ての保護者の所属教室を「narZE」に変更
UPDATE users
SET classroom_id = (
    SELECT id FROM (
        SELECT id FROM classrooms WHERE classroom_name = 'narZE' LIMIT 1
    ) AS temp
)
WHERE user_type = 'guardian';

-- スタッフ・管理者も「narZE」に変更
UPDATE users
SET classroom_id = (
    SELECT id FROM (
        SELECT id FROM classrooms WHERE classroom_name = 'narZE' LIMIT 1
    ) AS temp
)
WHERE user_type IN ('staff', 'admin');

-- 変更後の確認
SELECT '=== 変更後の保護者一覧 ===' AS info;
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

SELECT '=== 変更後のスタッフ・管理者一覧 ===' AS info;
SELECT
    u.id,
    u.username,
    u.full_name,
    u.user_type,
    u.classroom_id,
    c.classroom_name
FROM users u
LEFT JOIN classrooms c ON u.classroom_id = c.id
WHERE u.user_type IN ('staff', 'admin')
ORDER BY u.id;
