-- 提出期限管理テーブル
CREATE TABLE IF NOT EXISTS submission_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    student_id INT NOT NULL,
    guardian_id INT NOT NULL,
    created_by INT NOT NULL COMMENT 'スタッフID',
    title VARCHAR(255) NOT NULL COMMENT '提出物タイトル',
    description TEXT COMMENT '詳細説明',
    due_date DATE NOT NULL COMMENT '提出期限',
    is_completed TINYINT(1) DEFAULT 0 COMMENT '提出完了フラグ',
    completed_at DATETIME COMMENT '提出完了日時',
    completed_note TEXT COMMENT '完了時のメモ',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_guardian_id (guardian_id),
    INDEX idx_due_date (due_date),
    INDEX idx_is_completed (is_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提出期限管理';
