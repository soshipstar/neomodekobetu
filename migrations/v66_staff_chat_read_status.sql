-- スタッフごとのチャットメッセージ既読状態テーブル
CREATE TABLE IF NOT EXISTS chat_message_staff_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    staff_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (message_id, staff_id),
    INDEX idx_message_id (message_id),
    INDEX idx_staff_id (staff_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スタッフごとのチャットメッセージ既読状態';

-- チャットルームのピン留めテーブル
CREATE TABLE IF NOT EXISTS chat_room_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    staff_id INT NOT NULL,
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pin (room_id, staff_id),
    INDEX idx_staff_id (staff_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スタッフごとのチャットルームピン留め';
