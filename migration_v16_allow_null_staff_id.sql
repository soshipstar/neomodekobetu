-- migration_v16: kakehashi_staff の staff_id を NULL 許可に変更
-- 自動生成時はスタッフが未定のため

-- 既存の外部キー制約を削除
ALTER TABLE kakehashi_staff DROP FOREIGN KEY kakehashi_staff_ibfk_3;

-- staff_id カラムを NULL 許可に変更
ALTER TABLE kakehashi_staff MODIFY COLUMN staff_id int DEFAULT NULL COMMENT 'スタッフID（NULL=未割当）';

-- 外部キー制約を再作成
ALTER TABLE kakehashi_staff
ADD CONSTRAINT kakehashi_staff_ibfk_3
FOREIGN KEY (staff_id)
REFERENCES users (id)
ON DELETE SET NULL;

SELECT 'Migration v16 completed: staff_id now allows NULL' AS result;
