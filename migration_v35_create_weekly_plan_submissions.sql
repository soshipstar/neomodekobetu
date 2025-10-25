-- 週間計画表の提出物管理テーブル
CREATE TABLE IF NOT EXISTS weekly_plan_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    weekly_plan_id INT NOT NULL COMMENT '週間計画ID',
    submission_item VARCHAR(255) NOT NULL COMMENT '提出物名',
    due_date DATE NOT NULL COMMENT '提出期限',
    is_completed BOOLEAN DEFAULT FALSE COMMENT '完了フラグ',
    completed_at DATETIME NULL COMMENT '完了日時',
    completed_by_type ENUM('student', 'staff') NULL COMMENT '完了者タイプ',
    completed_by_id INT NULL COMMENT '完了者ID',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (weekly_plan_id) REFERENCES weekly_plans(id) ON DELETE CASCADE,
    INDEX idx_plan_due_date (weekly_plan_id, due_date),
    INDEX idx_completed (is_completed, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='週間計画表の提出物管理';
