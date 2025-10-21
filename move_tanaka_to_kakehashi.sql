-- 田中ボウリン健太郎をかけはし教室に移動するSQL

-- 1. 現在の教室一覧を確認
SELECT '=== 教室一覧 ===' AS info;
SELECT id, classroom_name FROM classrooms ORDER BY id;

-- 2. 田中ボウリン健太郎の現在の状態を確認
SELECT '=== 田中ボウリン健太郎の現在の状態 ===' AS info;
SELECT
    s.id,
    s.student_name,
    s.classroom_id,
    c.classroom_name as current_classroom,
    u.full_name as guardian_name
FROM students s
LEFT JOIN users u ON s.guardian_id = u.id
LEFT JOIN classrooms c ON s.classroom_id = c.id
WHERE s.student_name LIKE '%田中%'
  AND s.student_name LIKE '%ボウリン%'
  AND s.student_name LIKE '%健太郎%';

-- 3. かけはし教室のIDを確認
SELECT '=== かけはし教室のID ===' AS info;
SELECT id, classroom_name
FROM classrooms
WHERE classroom_name LIKE '%かけはし%' OR classroom_name LIKE '%カケハシ%';

-- 4. 田中ボウリン健太郎をかけはし教室に移動
-- ※実行前に上記の確認結果を見て、IDが正しいことを確認してください
UPDATE students
SET classroom_id = (
    SELECT id FROM classrooms WHERE classroom_name LIKE '%かけはし%' LIMIT 1
)
WHERE student_name LIKE '%田中%'
  AND student_name LIKE '%ボウリン%'
  AND student_name LIKE '%健太郎%';

-- 5. 更新後の確認
SELECT '=== 更新後の確認 ===' AS info;
SELECT
    s.id,
    s.student_name,
    s.classroom_id,
    c.classroom_name,
    u.full_name as guardian_name
FROM students s
LEFT JOIN users u ON s.guardian_id = u.id
LEFT JOIN classrooms c ON s.classroom_id = c.id
WHERE s.student_name LIKE '%田中%'
  AND s.student_name LIKE '%ボウリン%'
  AND s.student_name LIKE '%健太郎%';
