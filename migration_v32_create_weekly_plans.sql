-- 週間計画表テーブル
CREATE TABLE IF NOT EXISTS weekly_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT '生徒ID',
    week_start_date DATE NOT NULL COMMENT '週の開始日（月曜日）',
    plan_data JSON NOT NULL COMMENT '週間計画データ（曜日別の計画）',
    created_by_type ENUM('student', 'staff', 'guardian') NOT NULL COMMENT '作成者タイプ',
    created_by_id INT NOT NULL COMMENT '作成者ID',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_week (student_id, week_start_date),
    INDEX idx_student_date (student_id, week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='週間計画表';

-- 週間計画表コメントテーブル（保護者・スタッフのコメント用）
CREATE TABLE IF NOT EXISTS weekly_plan_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    weekly_plan_id INT NOT NULL COMMENT '週間計画ID',
    commenter_type ENUM('student', 'staff', 'guardian') NOT NULL COMMENT 'コメント者タイプ',
    commenter_id INT NOT NULL COMMENT 'コメント者ID',
    comment TEXT NOT NULL COMMENT 'コメント内容',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (weekly_plan_id) REFERENCES weekly_plans(id) ON DELETE CASCADE,
    INDEX idx_plan_created (weekly_plan_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='週間計画表コメント';
