-- classroom_idがNULLの生徒をnarZE教室に変更するSQL

-- 1. narZE教室のIDを確認
SELECT '=== narZE教室の情報 ===' AS info;
SELECT id, classroom_name
FROM classrooms
WHERE classroom_name LIKE '%narZE%' OR classroom_name LIKE '%ナルゼ%';

-- 2. classroom_idがNULLの生徒を確認
SELECT '=== classroom_idがNULLの生徒一覧 ===' AS info;
SELECT
    s.id,
    s.student_name,
    s.classroom_id,
    u.full_name as guardian_name
FROM students s
LEFT JOIN users u ON s.guardian_id = u.id
WHERE s.classroom_id IS NULL;

-- 3. classroom_idがNULLの生徒をnarZE教室（ID=2）に更新
-- ※narZE教室のIDが2であることを事前に確認してください
UPDATE students
SET classroom_id = (
    SELECT id FROM classrooms WHERE classroom_name LIKE '%narZE%' LIMIT 1
)
WHERE classroom_id IS NULL;

-- 4. 更新後の確認
SELECT '=== 更新後の生徒一覧（narZE教室） ===' AS info;
SELECT
    s.id,
    s.student_name,
    s.classroom_id,
    c.classroom_name,
    u.full_name as guardian_name
FROM students s
LEFT JOIN users u ON s.guardian_id = u.id
LEFT JOIN classrooms c ON s.classroom_id = c.id
WHERE s.classroom_id = (SELECT id FROM classrooms WHERE classroom_name LIKE '%narZE%' LIMIT 1)
ORDER BY s.id;
