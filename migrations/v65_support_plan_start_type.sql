-- v65: 個別支援計画の開始タイミング設定
-- 生徒ごとに、個別支援計画を現在の期間から開始するか、次回の期間から開始するかを選択可能にする

-- studentsテーブルに support_plan_start_type カラムを追加
-- 注意: このALTER TABLEは、カラムが既に存在する場合はエラーになります。その場合は無視してください。
ALTER TABLE students ADD COLUMN support_plan_start_type ENUM('current', 'next') DEFAULT 'current' COMMENT '個別支援計画開始タイプ: current=現在の期間から, next=次回の期間から';
