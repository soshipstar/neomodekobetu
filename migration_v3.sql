-- バージョン3へのマイグレーション
-- ChatGPT統合機能と保護者送信機能

-- 統合された連絡帳テーブル（生徒ごとに統合された文章を保存）
CREATE TABLE IF NOT EXISTS integrated_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_record_id INT NOT NULL,
    student_id INT NOT NULL,
    integrated_content TEXT NOT NULL COMMENT 'ChatGPTで統合された文章',
    is_sent TINYINT(1) DEFAULT 0 COMMENT '保護者に送信済みか',
    sent_at TIMESTAMP NULL COMMENT '送信日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (daily_record_id) REFERENCES daily_records(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_daily_record_id (daily_record_id),
    INDEX idx_student_id (student_id),
    INDEX idx_is_sent (is_sent),
    UNIQUE KEY unique_daily_student (daily_record_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 送信履歴テーブル
CREATE TABLE IF NOT EXISTS send_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    integrated_note_id INT NOT NULL,
    guardian_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL COMMENT '既読日時',
    FOREIGN KEY (integrated_note_id) REFERENCES integrated_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_integrated_note_id (integrated_note_id),
    INDEX idx_guardian_id (guardian_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
