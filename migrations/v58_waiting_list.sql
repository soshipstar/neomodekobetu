-- 待機児童管理機能用マイグレーション
-- v58_waiting_list.sql
--
-- 実行方法:
-- mysql -u root -p neomodekobetu < migrations/v58_waiting_list.sql
--
-- 注意: このスクリプトはphpMyAdminで1ステートメントずつ実行するか、
-- 以下のPHPスクリプトを使用して実行してください。

-- 1. studentsテーブルのstatus enumに'waiting'を追加
ALTER TABLE students
MODIFY COLUMN status ENUM('trial', 'active', 'short_term', 'withdrawn', 'waiting')
COLLATE utf8mb4_unicode_ci DEFAULT 'active'
COMMENT '在籍状況: trial=体験, active=在籍, short_term=短期利用, withdrawn=退所, waiting=待機';

-- 2. 待機児童専用カラムを追加
-- 注意: カラムが既に存在する場合はエラーが出ますが、無視して続行してください

-- desired_start_date: 入所希望日
ALTER TABLE students
ADD COLUMN desired_start_date DATE DEFAULT NULL
COMMENT '入所希望日' AFTER withdrawal_date;

-- desired_weekly_count: 希望利用回数（週次）
ALTER TABLE students
ADD COLUMN desired_weekly_count TINYINT UNSIGNED DEFAULT NULL
COMMENT '希望利用回数（週次）' AFTER desired_start_date;

-- 希望曜日フラグ
ALTER TABLE students
ADD COLUMN desired_monday TINYINT(1) DEFAULT 0
COMMENT '月曜日希望' AFTER desired_weekly_count;

ALTER TABLE students
ADD COLUMN desired_tuesday TINYINT(1) DEFAULT 0
COMMENT '火曜日希望' AFTER desired_monday;

ALTER TABLE students
ADD COLUMN desired_wednesday TINYINT(1) DEFAULT 0
COMMENT '水曜日希望' AFTER desired_tuesday;

ALTER TABLE students
ADD COLUMN desired_thursday TINYINT(1) DEFAULT 0
COMMENT '木曜日希望' AFTER desired_wednesday;

ALTER TABLE students
ADD COLUMN desired_friday TINYINT(1) DEFAULT 0
COMMENT '金曜日希望' AFTER desired_thursday;

ALTER TABLE students
ADD COLUMN desired_saturday TINYINT(1) DEFAULT 0
COMMENT '土曜日希望' AFTER desired_friday;

ALTER TABLE students
ADD COLUMN desired_sunday TINYINT(1) DEFAULT 0
COMMENT '日曜日希望' AFTER desired_saturday;

-- waiting_notes: 待機に関するメモ
ALTER TABLE students
ADD COLUMN waiting_notes TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL
COMMENT '待機に関するメモ' AFTER desired_sunday;

-- 3. 待機児童検索用インデックス
ALTER TABLE students ADD INDEX idx_students_waiting (status, desired_start_date);
