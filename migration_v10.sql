-- マイグレーションv10: 個別支援計画書テーブルを作成

-- 個別支援計画書マスターテーブル
CREATE TABLE IF NOT EXISTS individual_support_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT '対象生徒ID',
    student_name VARCHAR(100) NOT NULL COMMENT '生徒氏名',
    created_date DATE NOT NULL COMMENT '作成年月日',
    life_intention TEXT COMMENT '利用児及び家族の生活に対する意向',
    overall_policy TEXT COMMENT '総合的な支援の方針',
    long_term_goal_date DATE COMMENT '長期目標日',
    long_term_goal_text TEXT COMMENT '長期目標内容',
    short_term_goal_date DATE COMMENT '短期目標日',
    short_term_goal_text TEXT COMMENT '短期目標内容',
    manager_name VARCHAR(100) COMMENT '管理責任者氏名',
    consent_date DATE COMMENT '同意日',
    guardian_signature VARCHAR(100) COMMENT '保護者署名',
    created_by INT COMMENT '作成者（スタッフID）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_created_date (created_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='個別支援計画書マスター';

-- 個別支援計画書明細テーブル
CREATE TABLE IF NOT EXISTS individual_support_plan_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL COMMENT '計画書ID',
    row_order INT NOT NULL DEFAULT 0 COMMENT '行順序',
    category VARCHAR(50) COMMENT '項目（本人支援/家族支援/地域支援等）',
    sub_category VARCHAR(100) COMMENT 'サブカテゴリ（生活習慣、コミュニケーション等）',
    support_goal TEXT COMMENT '支援目標（具体的な到達目標）',
    support_content TEXT COMMENT '支援内容（内容・支援の提供上のポイント・5領域との関連性等）',
    achievement_date DATE COMMENT '達成時期',
    staff_organization TEXT COMMENT '担当者／提供機関',
    notes TEXT COMMENT '留意事項',
    priority INT COMMENT '優先順位',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_id (plan_id),
    INDEX idx_row_order (row_order),
    FOREIGN KEY (plan_id) REFERENCES individual_support_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='個別支援計画書明細';
