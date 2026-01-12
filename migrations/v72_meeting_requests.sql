-- 面談予約機能用テーブル
CREATE TABLE meeting_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL COMMENT '教室ID',
    student_id INT NOT NULL COMMENT '生徒ID',
    guardian_id INT NOT NULL COMMENT '保護者ID',
    staff_id INT NOT NULL COMMENT 'スタッフID',

    purpose VARCHAR(255) NOT NULL COMMENT '面談目的（個別支援計画、モニタリング、その他）',
    purpose_detail TEXT DEFAULT NULL COMMENT '面談目的詳細',
    related_plan_id INT DEFAULT NULL COMMENT '関連する個別支援計画ID',
    related_monitoring_id INT DEFAULT NULL COMMENT '関連するモニタリングID',

    -- 候補日時（スタッフ提案）
    candidate_date1 DATETIME NOT NULL COMMENT '候補日時1',
    candidate_date2 DATETIME DEFAULT NULL COMMENT '候補日時2',
    candidate_date3 DATETIME DEFAULT NULL COMMENT '候補日時3',

    -- 保護者からの対案（3つの候補が合わない場合）
    guardian_counter_date1 DATETIME DEFAULT NULL COMMENT '保護者対案日時1',
    guardian_counter_date2 DATETIME DEFAULT NULL COMMENT '保護者対案日時2',
    guardian_counter_date3 DATETIME DEFAULT NULL COMMENT '保護者対案日時3',
    guardian_counter_message TEXT DEFAULT NULL COMMENT '保護者からのメッセージ',

    -- スタッフからの再提案
    staff_counter_date1 DATETIME DEFAULT NULL COMMENT 'スタッフ再提案日時1',
    staff_counter_date2 DATETIME DEFAULT NULL COMMENT 'スタッフ再提案日時2',
    staff_counter_date3 DATETIME DEFAULT NULL COMMENT 'スタッフ再提案日時3',
    staff_counter_message TEXT DEFAULT NULL COMMENT 'スタッフからのメッセージ',

    -- 確定情報
    confirmed_date DATETIME DEFAULT NULL COMMENT '確定した面談日時',
    confirmed_by ENUM('guardian', 'staff') DEFAULT NULL COMMENT '確定した側',
    confirmed_at DATETIME DEFAULT NULL COMMENT '確定日時',

    -- ステータス
    status ENUM('pending', 'guardian_counter', 'staff_counter', 'confirmed', 'cancelled') DEFAULT 'pending' COMMENT 'ステータス',

    -- 面談実施後
    is_completed TINYINT(1) DEFAULT 0 COMMENT '面談実施済みフラグ',
    completed_at DATETIME DEFAULT NULL COMMENT '面談実施日時',
    meeting_notes TEXT DEFAULT NULL COMMENT '面談メモ',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_classroom (classroom_id),
    INDEX idx_student (student_id),
    INDEX idx_guardian (guardian_id),
    INDEX idx_staff (staff_id),
    INDEX idx_status (status),
    INDEX idx_confirmed_date (confirmed_date),

    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='面談予約リクエスト';

-- chat_messagesにメッセージタイプと面談リクエストIDを追加
ALTER TABLE chat_messages
    ADD COLUMN message_type VARCHAR(50) DEFAULT 'text' COMMENT 'メッセージタイプ(text, meeting_request, etc)',
    ADD COLUMN meeting_request_id INT DEFAULT NULL COMMENT '関連する面談リクエストID',
    ADD INDEX idx_meeting_request (meeting_request_id);
