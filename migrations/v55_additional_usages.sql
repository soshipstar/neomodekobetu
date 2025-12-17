-- 追加利用日テーブル
-- v55_additional_usages.sql

CREATE TABLE IF NOT EXISTS `additional_usages` (
    `id` int NOT NULL AUTO_INCREMENT,
    `student_id` int NOT NULL COMMENT '生徒ID',
    `usage_date` date NOT NULL COMMENT '利用日',
    `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'メモ',
    `created_by` int DEFAULT NULL COMMENT '作成者（スタッフID）',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_student_date` (`student_id`, `usage_date`),
    KEY `idx_student_id` (`student_id`),
    KEY `idx_usage_date` (`usage_date`),
    CONSTRAINT `fk_additional_usages_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='追加利用日';
