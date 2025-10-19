-- かけはしテーブルに非表示フラグを追加
-- 作成が不要なかけはしを非表示にできるようにする

-- 保護者かけはしテーブルに非表示フラグを追加
ALTER TABLE kakehashi_guardian
ADD COLUMN is_hidden TINYINT(1) DEFAULT 0 COMMENT '非表示フラグ（0:表示, 1:非表示）';

-- スタッフかけはしテーブルに非表示フラグを追加
ALTER TABLE kakehashi_staff
ADD COLUMN is_hidden TINYINT(1) DEFAULT 0 COMMENT '非表示フラグ（0:表示, 1:非表示）';
