-- 既存の保護者のclassroom_idを更新するスクリプト
-- 生徒の担当スタッフの教室に保護者を所属させる

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

-- 生徒が紐づいている保護者の場合、その生徒を担当するスタッフの教室に所属させる
UPDATE users u
INNER JOIN students s ON u.id = s.guardian_id
INNER JOIN (
    SELECT DISTINCT
        s.guardian_id,
        staff.classroom_id
    FROM students s
    INNER JOIN daily_records dr ON s.id = dr.student_id
    INNER JOIN users staff ON dr.staff_id = staff.id
    WHERE staff.classroom_id IS NOT NULL
    LIMIT 1
) staff_classroom ON u.id = staff_classroom.guardian_id
SET u.classroom_id = staff_classroom.classroom_id
WHERE u.user_type = 'guardian' AND u.classroom_id IS NULL;

-- 上記で更新されなかった保護者（生徒がいない、または記録がない）は、最初の教室に所属させる
UPDATE users
SET classroom_id = (SELECT MIN(id) FROM classrooms)
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
