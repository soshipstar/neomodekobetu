-- 生徒用チャットルームテーブル
CREATE TABLE IF NOT EXISTS student_chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT '生徒ID',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_chat (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生徒用チャットルーム';

-- 生徒用チャットメッセージテーブル
CREATE TABLE IF NOT EXISTS student_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL COMMENT 'チャットルームID',
    sender_type ENUM('student', 'staff') NOT NULL COMMENT '送信者タイプ',
    sender_id INT NOT NULL COMMENT '送信者ID（studentまたはuserテーブルのID）',
    message_type ENUM('normal', 'absence', 'event') DEFAULT 'normal' COMMENT 'メッセージタイプ',
    message TEXT NOT NULL COMMENT 'メッセージ内容',
    attachment_path VARCHAR(255) NULL COMMENT '添付ファイルパス',
    attachment_original_name VARCHAR(255) NULL COMMENT '添付ファイル元のファイル名',
    attachment_size INT NULL COMMENT '添付ファイルサイズ（バイト）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES student_chat_rooms(id) ON DELETE CASCADE,
    INDEX idx_room_created (room_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生徒用チャットメッセージ';
