-- バージョン7へのマイグレーション
-- かけはし五領域ファイルシステムの追加

-- かけはし提出期日テーブル
CREATE TABLE IF NOT EXISTS kakehashi_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_name VARCHAR(100) NOT NULL COMMENT '期間名（例：2025年度前期）',
    start_date DATE NOT NULL COMMENT '対象期間開始日',
    end_date DATE NOT NULL COMMENT '対象期間終了日',
    submission_deadline DATE NOT NULL COMMENT '提出期限',
    is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_submission_deadline (submission_deadline),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 保護者入力かけはしテーブル
CREATE TABLE IF NOT EXISTS kakehashi_guardian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL COMMENT '期間ID',
    student_id INT NOT NULL COMMENT '生徒ID',
    home_challenges TEXT COMMENT '家庭での課題',
    short_term_goal TEXT COMMENT '短期目標（6か月）',
    long_term_goal TEXT COMMENT '長期目標（1年以上）',
    domain_health_life TEXT COMMENT '健康・生活領域の課題',
    domain_motor_sensory TEXT COMMENT '運動・感覚領域の課題',
    domain_cognitive_behavior TEXT COMMENT '認知・行動領域の課題',
    domain_language_communication TEXT COMMENT '言語・コミュニケーション領域の課題',
    domain_social_relations TEXT COMMENT '人間関係・社会性領域の課題',
    other_challenges TEXT COMMENT 'その他の課題',
    is_submitted TINYINT(1) DEFAULT 0 COMMENT '提出済みフラグ',
    submitted_at TIMESTAMP NULL COMMENT '提出日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES kakehashi_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_period_student (period_id, student_id),
    INDEX idx_period_id (period_id),
    INDEX idx_student_id (student_id),
    INDEX idx_is_submitted (is_submitted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- スタッフ入力かけはしテーブル
CREATE TABLE IF NOT EXISTS kakehashi_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL COMMENT '期間ID',
    student_id INT NOT NULL COMMENT '生徒ID',
    staff_id INT NOT NULL COMMENT 'スタッフID',
    short_term_goal TEXT COMMENT '短期目標（6か月）',
    long_term_goal TEXT COMMENT '長期目標（1年以上）',
    domain_health_life TEXT COMMENT '健康・生活領域の課題',
    domain_motor_sensory TEXT COMMENT '運動・感覚領域の課題',
    domain_cognitive_behavior TEXT COMMENT '認知・行動領域の課題',
    domain_language_communication TEXT COMMENT '言語・コミュニケーション領域の課題',
    domain_social_relations TEXT COMMENT '人間関係・社会性領域の課題',
    other_challenges TEXT COMMENT 'その他の課題',
    is_submitted TINYINT(1) DEFAULT 0 COMMENT '提出済みフラグ',
    submitted_at TIMESTAMP NULL COMMENT '提出日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES kakehashi_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_period_student_staff (period_id, student_id),
    INDEX idx_period_id (period_id),
    INDEX idx_student_id (student_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_is_submitted (is_submitted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
