-- 業務日誌テーブルの児童関連カラムを分割
-- v64_work_diaries_split_children_notes.sql
--
-- children_notes を prev_day_children_status と children_special_notes に分割

-- 前日の児童の状況カラムを追加
ALTER TABLE work_diaries
ADD COLUMN prev_day_children_status TEXT DEFAULT NULL COMMENT '前日の児童の状況' AFTER daily_roles;

-- 児童に関する特記事項カラムを追加（既存の children_notes をリネーム相当）
ALTER TABLE work_diaries
ADD COLUMN children_special_notes TEXT DEFAULT NULL COMMENT '児童に関する特記事項' AFTER prev_day_children_status;

-- 既存データを移行（children_notes → children_special_notes）
UPDATE work_diaries SET children_special_notes = children_notes WHERE children_notes IS NOT NULL;

-- 旧カラムを削除
ALTER TABLE work_diaries DROP COLUMN children_notes;
