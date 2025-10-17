-- 個別支援連絡帳システム データベース設計
-- データベース名: _kobetudb

-- ユーザーテーブル（管理者・スタッフ・保護者）
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    user_type ENUM('admin', 'staff', 'guardian') NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_user_type (user_type),
    INDEX idx_username (username)
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

-- 初期管理者アカウント作成（パスワード: admin123）
-- ハッシュは password_hash('admin123', PASSWORD_DEFAULT) で生成
INSERT INTO users (username, password, full_name, user_type, email) VALUES
('admin', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', '管理者', 'admin', 'admin@example.com');

-- サンプルスタッフアカウント作成（パスワード: staff123）
-- ハッシュは password_hash('staff123', PASSWORD_DEFAULT) で生成
INSERT INTO users (username, password, full_name, user_type, email) VALUES
('staff01', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', 'スタッフ01', 'staff', 'staff01@example.com');

-- サンプル保護者アカウント作成（パスワード: guardian123）
-- ハッシュは password_hash('guardian123', PASSWORD_DEFAULT) で生成
INSERT INTO users (username, password, full_name, user_type, email) VALUES
('guardian01', '$2y$10$vLvXZhEbeQOYpxZBPPH.XeD1RWvHG.eL5dMXKjYzRZy3mY1F5DzJ2', '保護者01', 'guardian', 'guardian01@example.com');

-- サンプル生徒データ
INSERT INTO students (student_name, guardian_id, birth_date) VALUES
('山田太郎', 3, '2015-04-15'),
('佐藤花子', 3, '2016-08-20');
