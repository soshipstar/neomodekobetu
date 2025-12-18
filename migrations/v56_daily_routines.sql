-- 毎日の支援（ルーティーン活動）テーブル
-- 各教室で最大5つまで登録可能なルーティーン活動を管理

CREATE TABLE IF NOT EXISTS daily_routines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    routine_name VARCHAR(100) NOT NULL COMMENT 'ルーティーン名',
    routine_content TEXT COMMENT '活動内容',
    scheduled_time VARCHAR(20) COMMENT '実施時間（例: 15:00〜15:30）',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_classroom_order (classroom_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- インデックス追加
CREATE INDEX idx_daily_routines_classroom ON daily_routines(classroom_id);
CREATE INDEX idx_daily_routines_active ON daily_routines(is_active);
