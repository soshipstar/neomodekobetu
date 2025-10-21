-- 支援案テーブルの作成
CREATE TABLE IF NOT EXISTS support_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_name VARCHAR(255) NOT NULL COMMENT '活動名',
    activity_purpose TEXT COMMENT '活動の目的',
    activity_content TEXT COMMENT '活動の内容',
    five_domains_consideration TEXT COMMENT '五領域への配慮',
    other_notes TEXT COMMENT 'その他',
    staff_id INT NOT NULL COMMENT '作成したスタッフID',
    classroom_id INT COMMENT '教室ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
    INDEX idx_staff_id (staff_id),
    INDEX idx_classroom_id (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支援案マスタ';
