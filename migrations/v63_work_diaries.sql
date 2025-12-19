-- 業務日誌テーブル
-- v63_work_diaries.sql
--
-- 放課後等デイサービスの業務日誌を管理するテーブル

CREATE TABLE IF NOT EXISTS work_diaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    diary_date DATE NOT NULL,

    -- 前日の振り返り
    previous_day_review TEXT DEFAULT NULL COMMENT '前日の振り返り',

    -- 本日の伝達事項
    daily_communication TEXT DEFAULT NULL COMMENT '本日の伝達事項',

    -- 本日の役割分担
    daily_roles TEXT DEFAULT NULL COMMENT '本日の役割分担',

    -- 児童の状況・特記事項
    children_notes TEXT DEFAULT NULL COMMENT '児童の状況・特記事項',

    -- その他メモ
    other_notes TEXT DEFAULT NULL COMMENT 'その他メモ',

    -- 作成者・更新情報
    created_by INT NOT NULL COMMENT '作成者スタッフID',
    updated_by INT DEFAULT NULL COMMENT '最終更新者スタッフID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- 外部キー
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,

    -- 一意制約（1教室につき1日1件）
    UNIQUE KEY unique_classroom_date (classroom_id, diary_date),

    -- インデックス
    INDEX idx_diary_date (diary_date),
    INDEX idx_classroom_date (classroom_id, diary_date)
);
