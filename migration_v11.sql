-- マイグレーションv11: モニタリング表テーブルを作成

-- モニタリング表マスターテーブル
CREATE TABLE IF NOT EXISTS monitoring_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL COMMENT '対象の個別支援計画書ID',
    student_id INT NOT NULL COMMENT '対象生徒ID',
    student_name VARCHAR(100) NOT NULL COMMENT '生徒氏名',
    monitoring_date DATE NOT NULL COMMENT 'モニタリング実施日',
    overall_comment TEXT COMMENT '総合所見',
    created_by INT COMMENT '作成者（スタッフID）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_id (plan_id),
    INDEX idx_student_id (student_id),
    INDEX idx_monitoring_date (monitoring_date),
    FOREIGN KEY (plan_id) REFERENCES individual_support_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='モニタリング表マスター';

-- モニタリング表明細テーブル
CREATE TABLE IF NOT EXISTS monitoring_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monitoring_id INT NOT NULL COMMENT 'モニタリング表ID',
    plan_detail_id INT NOT NULL COMMENT '個別支援計画書明細ID',
    achievement_status VARCHAR(50) COMMENT '達成状況（未着手/進行中/達成/継続中等）',
    monitoring_comment TEXT COMMENT 'モニタリングコメント',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monitoring_id (monitoring_id),
    INDEX idx_plan_detail_id (plan_detail_id),
    FOREIGN KEY (monitoring_id) REFERENCES monitoring_records(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_detail_id) REFERENCES individual_support_plan_details(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='モニタリング表明細';
