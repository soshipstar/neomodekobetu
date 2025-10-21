-- マイグレーション v27: 生徒の退所日カラムを追加

-- studentsテーブルにwithdrawal_dateカラムを追加
ALTER TABLE students
ADD COLUMN withdrawal_date DATE NULL COMMENT '退所日' AFTER status;

-- 既存の退所済み生徒のデータは変更しない（NULLのまま）
