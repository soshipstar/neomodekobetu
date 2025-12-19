-- 教室定員設定テーブル用マイグレーション
-- v59_classroom_capacity.sql
--
-- 実行方法:
-- mysql -u root -p neomodekobetu < migrations/v59_classroom_capacity.sql

-- 1. 教室定員設定テーブルの作成
CREATE TABLE IF NOT EXISTS classroom_capacity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=日曜, 1=月曜, 2=火曜, 3=水曜, 4=木曜, 5=金曜, 6=土曜',
    max_capacity INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '最大定員',
    is_open TINYINT(1) NOT NULL DEFAULT 1 COMMENT '営業日フラグ（1=営業, 0=休業）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_classroom_day (classroom_id, day_of_week),
    INDEX idx_classroom_capacity_classroom (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='教室の曜日別定員設定';

-- 2. 既存の各教室に対してデフォルトデータを挿入（月〜金を営業日、土日を休業日として設定）
INSERT IGNORE INTO classroom_capacity (classroom_id, day_of_week, max_capacity, is_open)
SELECT
    c.id,
    d.day_of_week,
    10,
    CASE WHEN d.day_of_week BETWEEN 1 AND 5 THEN 1 ELSE 0 END
FROM classrooms c
CROSS JOIN (
    SELECT 0 AS day_of_week UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) d;
