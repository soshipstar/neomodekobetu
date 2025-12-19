-- grade_level ENUM拡張マイグレーション
-- v60_expand_grade_level_enum.sql
--
-- 実行方法:
-- mysql -u root -p neomodekobetu < migrations/v60_expand_grade_level_enum.sql
--
-- 問題: calculateGradeLevel()関数が詳細な学年（elementary_1, junior_high_2等）を返すが、
--       ENUMは 'elementary', 'junior_high', 'high_school' のみを許可していた

-- 1. grade_level ENUMを拡張
ALTER TABLE students
MODIFY COLUMN grade_level ENUM(
    'preschool',
    'elementary', 'elementary_1', 'elementary_2', 'elementary_3', 'elementary_4', 'elementary_5', 'elementary_6',
    'junior_high', 'junior_high_1', 'junior_high_2', 'junior_high_3',
    'high_school', 'high_school_1', 'high_school_2', 'high_school_3'
) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'elementary' COMMENT '学年レベル（詳細）';

-- 2. 既存の生徒データの学年を詳細値に更新（オプション）
-- 注意: この更新は必須ではありません。新規登録・更新時に自動計算されます。
-- 実行する場合は以下のコメントを外してください:
-- UPDATE students SET grade_level = 'elementary_1' WHERE grade_level = 'elementary' AND birth_date IS NOT NULL;
