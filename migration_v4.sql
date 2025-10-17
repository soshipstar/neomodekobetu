-- バージョン4へのマイグレーション
-- スケジュール管理機能（参加予定曜日、休日、イベント）

-- studentsテーブルに参加予定曜日カラムを追加
ALTER TABLE students
ADD COLUMN scheduled_monday TINYINT(1) DEFAULT 0 COMMENT '月曜日参加予定',
ADD COLUMN scheduled_tuesday TINYINT(1) DEFAULT 0 COMMENT '火曜日参加予定',
ADD COLUMN scheduled_wednesday TINYINT(1) DEFAULT 0 COMMENT '水曜日参加予定',
ADD COLUMN scheduled_thursday TINYINT(1) DEFAULT 0 COMMENT '木曜日参加予定',
ADD COLUMN scheduled_friday TINYINT(1) DEFAULT 0 COMMENT '金曜日参加予定',
ADD COLUMN scheduled_saturday TINYINT(1) DEFAULT 0 COMMENT '土曜日参加予定',
ADD COLUMN scheduled_sunday TINYINT(1) DEFAULT 0 COMMENT '日曜日参加予定';

-- 休日テーブル
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    holiday_name VARCHAR(255) NOT NULL COMMENT '休日名',
    holiday_type ENUM('regular', 'special') DEFAULT 'regular' COMMENT 'regular=定期休日, special=特別休日',
    created_by INT NOT NULL COMMENT '登録者ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- イベントテーブル
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    event_name VARCHAR(255) NOT NULL COMMENT 'イベント名',
    event_description TEXT COMMENT 'イベント説明',
    event_color VARCHAR(7) DEFAULT '#28a745' COMMENT 'カレンダー表示色',
    created_by INT NOT NULL COMMENT '登録者ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
