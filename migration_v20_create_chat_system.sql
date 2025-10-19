-- Migration v20: チャット機能の追加
-- 保護者とスタッフの間でチャットができるシステム

-- チャットルームテーブル（保護者と生徒の組み合わせごとにルームを作成）
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    guardian_id INT NOT NULL,
    last_message_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_guardian (student_id, guardian_id),
    INDEX idx_guardian (guardian_id),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='チャットルーム';

-- チャットメッセージテーブル
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('guardian', 'staff', 'admin') NOT NULL COMMENT '送信者タイプ',
    message TEXT NOT NULL COMMENT 'メッセージ内容',
    is_read TINYINT(1) DEFAULT 0 COMMENT '既読フラグ（0:未読, 1:既読）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_room (room_id),
    INDEX idx_created (created_at),
    INDEX idx_unread (room_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='チャットメッセージ';
