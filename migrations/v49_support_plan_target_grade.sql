-- v49: 支援案の対象年齢層カラムを追加
-- 対象: 小学生未満、小学生、中学生、高校生（複数選択可）

ALTER TABLE support_plans
ADD COLUMN target_grade SET('preschool', 'elementary', 'junior_high', 'high_school') DEFAULT NULL AFTER plan_type;

-- コメント:
-- preschool = 小学生未満
-- elementary = 小学生
-- junior_high = 中学生
-- high_school = 高校生
