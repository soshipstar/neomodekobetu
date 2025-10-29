-- Migration v41: 生徒が自分で登録する提出物テーブルの作成

CREATE TABLE IF NOT EXISTS student_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT '生徒ID',
    title VARCHAR(255) NOT NULL COMMENT '提出物タイトル',
    description TEXT COMMENT '詳細説明',
    due_date DATE NOT NULL COMMENT '提出期限',
    is_completed BOOLEAN DEFAULT FALSE COMMENT '完了フラグ',
    completed_at DATETIME NULL COMMENT '完了日時',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_due (student_id, due_date),
    INDEX idx_completed (is_completed, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生徒が自分で登録する提出物';
