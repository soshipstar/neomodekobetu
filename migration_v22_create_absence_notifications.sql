-- 欠席連絡テーブルの作成
CREATE TABLE IF NOT EXISTS absence_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    student_id INT NOT NULL,
    absence_date DATE NOT NULL,
    reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_absence_date (absence_date),
    INDEX idx_student_date (student_id, absence_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- チャットメッセージテーブルにメッセージタイプを追加
ALTER TABLE chat_messages
ADD COLUMN message_type ENUM('normal', 'absence_notification') DEFAULT 'normal' AFTER sender_type;
