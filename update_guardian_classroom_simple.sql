-- 既存の保護者のclassroom_idを更新する簡易版SQL
-- PhpMyAdminで _kobetudb データベースを選択してから実行してください

-- まず、classroom_idがNULLの保護者を確認
SELECT
    u.id,
    u.username,
    u.full_name,
    u.classroom_id,
    COUNT(s.id) as student_count
FROM users u
LEFT JOIN students s ON u.id = s.guardian_id
WHERE u.user_type = 'guardian' AND u.classroom_id IS NULL
GROUP BY u.id;

-- スタッフのclassroom_idを取得（最初のスタッフの教室を使用）
SELECT classroom_id
FROM users
WHERE user_type IN ('staff', 'admin') AND classroom_id IS NOT NULL
LIMIT 1;

-- 上記で取得したclassroom_idを使って、以下のクエリの「1」を置き換えて実行してください
-- 例: classroom_idが1の場合
UPDATE users
SET classroom_id = 1
WHERE user_type = 'guardian' AND classroom_id IS NULL;

-- 更新後の確認
SELECT
    u.id,
    u.username,
    u.full_name,
    u.classroom_id,
    c.classroom_name,
    COUNT(s.id) as student_count
FROM users u
LEFT JOIN students s ON u.id = s.guardian_id
LEFT JOIN classrooms c ON u.classroom_id = c.id
WHERE u.user_type = 'guardian'
GROUP BY u.id
ORDER BY u.classroom_id, u.full_name;
