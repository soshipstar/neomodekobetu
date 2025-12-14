-- 個別支援連絡帳システム データベース設計
-- Docker初期化用（セキュリティ強化版）

-- 教室テーブル
CREATE TABLE IF NOT EXISTS classrooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- デフォルト教室を作成
INSERT INTO classrooms (name, description) VALUES ('デフォルト教室', 'デフォルトの教室');

-- ユーザーテーブル（管理者・スタッフ・保護者・タブレットユーザー）
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    user_type ENUM('admin', 'staff', 'guardian', 'tablet_user') NOT NULL,
    email VARCHAR(255),
    classroom_id INT,
    is_master TINYINT(1) DEFAULT 0 COMMENT 'マスター管理者フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
    INDEX idx_user_type (user_type),
    INDEX idx_username (username),
    INDEX idx_classroom_id (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 生徒テーブル
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(100) NOT NULL,
    guardian_id INT,
    birth_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_name (student_name),
    INDEX idx_guardian_id (guardian_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 日次記録テーブル（共通活動記録）
CREATE TABLE IF NOT EXISTS daily_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_date DATE NOT NULL,
    staff_id INT NOT NULL,
    common_activity TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_record_date (record_date),
    INDEX idx_staff_id (staff_id),
    UNIQUE KEY unique_date_staff (record_date, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 生徒別記録テーブル
CREATE TABLE IF NOT EXISTS student_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_record_id INT NOT NULL,
    student_id INT NOT NULL,
    domain1 ENUM('health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations') NOT NULL COMMENT '健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性',
    domain1_content TEXT,
    domain2 ENUM('health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations') COMMENT '健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性',
    domain2_content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (daily_record_id) REFERENCES daily_records(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_daily_record_id (daily_record_id),
    INDEX idx_student_id (student_id),
    UNIQUE KEY unique_daily_student (daily_record_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- セキュリティ関連テーブル
-- ============================================

-- ログイン試行記録テーブル
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(100),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0,
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 監査ログテーブル
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    target_table VARCHAR(100),
    target_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 初期データ
-- ============================================

-- 初期管理者アカウント作成（パスワード: admin123）マスター管理者として作成
INSERT INTO users (username, password, full_name, user_type, email, classroom_id, is_master) VALUES
('admin', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', '管理者', 'admin', 'admin@example.com', 1, 1);

-- サンプルスタッフアカウント作成（パスワード: staff123）
INSERT INTO users (username, password, full_name, user_type, email, classroom_id) VALUES
('staff01', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', 'スタッフ01', 'staff', 'staff01@example.com', 1);

-- サンプル保護者アカウント作成（パスワード: guardian123）
INSERT INTO users (username, password, full_name, user_type, email, classroom_id) VALUES
('guardian01', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', '保護者01', 'guardian', 'guardian01@example.com', 1);

-- サンプル生徒データ
INSERT INTO students (student_name, guardian_id, birth_date) VALUES
('山田太郎', 3, '2015-04-15'),
('佐藤花子', 3, '2016-08-20');

-- ============================================
-- 追加テーブル
-- ============================================

-- 休日テーブル
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_type ENUM('regular', 'special', 'temporary') DEFAULT 'regular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    INDEX idx_holiday_date (holiday_date),
    INDEX idx_classroom_id (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- イベントテーブル
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    event_description TEXT,
    event_color VARCHAR(20) DEFAULT '#007AFF',
    staff_comment TEXT,
    guardian_message TEXT,
    target_audience ENUM('all', 'staff', 'guardian') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    INDEX idx_event_date (event_date),
    INDEX idx_classroom_id (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 支援計画テーブル
CREATE TABLE IF NOT EXISTS support_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    staff_id INT,
    activity_name VARCHAR(200),
    plan_content TEXT,
    goal TEXT,
    start_date DATE,
    end_date DATE,
    status ENUM('draft', 'active', 'completed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_staff_id (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- daily_recordsにactivity_nameとsupport_plan_idカラムを追加
ALTER TABLE daily_records ADD COLUMN activity_name VARCHAR(200) AFTER record_date;
ALTER TABLE daily_records ADD COLUMN support_plan_id INT AFTER activity_name;
ALTER TABLE daily_records ADD CONSTRAINT fk_daily_records_support_plan FOREIGN KEY (support_plan_id) REFERENCES support_plans(id) ON DELETE SET NULL;

-- 統合メモテーブル
CREATE TABLE IF NOT EXISTS integrated_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_record_id INT NOT NULL,
    student_id INT NOT NULL,
    note_content TEXT,
    is_sent TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (daily_record_id) REFERENCES daily_records(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_daily_record_id (daily_record_id),
    INDEX idx_student_id (student_id),
    INDEX idx_is_sent (is_sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- かけはし期間テーブル
CREATE TABLE IF NOT EXISTS kakehashi_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    period_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    INDEX idx_classroom_id (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- チャットメッセージテーブル
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- お便りテーブル
CREATE TABLE IF NOT EXISTS newsletters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    file_path VARCHAR(500),
    published_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    INDEX idx_classroom_id (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 欠席通知テーブル
CREATE TABLE IF NOT EXISTS absence_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    guardian_id INT NOT NULL,
    absence_date DATE NOT NULL,
    reason TEXT,
    is_makeup_requested TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_absence_date (absence_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
