-- 教室ごとのタグ設定テーブル
-- 各教室で独自のタグを設定可能

CREATE TABLE IF NOT EXISTS classroom_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    tag_name VARCHAR(50) NOT NULL COMMENT 'タグ名',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_classroom_tag (classroom_id, tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- インデックス追加
CREATE INDEX idx_classroom_tags_classroom ON classroom_tags(classroom_id);
CREATE INDEX idx_classroom_tags_active ON classroom_tags(is_active);
