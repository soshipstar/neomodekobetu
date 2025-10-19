-- マイグレーション v12: 教室管理・マスター管理者・生徒ステータス追加

-- 1. 教室テーブル作成
CREATE TABLE IF NOT EXISTS classrooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    classroom_name VARCHAR(100) NOT NULL COMMENT '教室名',
    address VARCHAR(255) COMMENT '住所',
    phone VARCHAR(20) COMMENT '電話番号',
    logo_path VARCHAR(255) COMMENT 'ロゴ画像パス',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT '教室情報';

-- 2. studentsテーブルにstatus列とclassroom_id列を追加（既存チェック付き）

-- status列が存在しない場合のみ追加
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'students' AND column_name = 'status');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN status ENUM(''active'', ''withdrawn'', ''trial'') DEFAULT ''active'' COMMENT ''在籍状況: active=在籍, withdrawn=退所, trial=体験'' AFTER is_active',
    'SELECT ''status column already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- classroom_id列が存在しない場合のみ追加
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'students' AND column_name = 'classroom_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN classroom_id INT COMMENT ''所属教室ID'' AFTER id',
    'SELECT ''classroom_id column already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 外部キー制約が存在しない場合のみ追加
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE table_schema = DATABASE() AND table_name = 'students' AND constraint_name = 'fk_students_classroom');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE students ADD CONSTRAINT fk_students_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id)',
    'SELECT ''foreign key fk_students_classroom already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. usersテーブル（guardians/staff/admins統合テーブル）にclassroom_id列を追加（既存チェック付き）

-- classroom_id列が存在しない場合のみ追加
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'classroom_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN classroom_id INT COMMENT ''所属教室ID'' AFTER id',
    'SELECT ''classroom_id column already exists in users'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 外部キー制約が存在しない場合のみ追加
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE table_schema = DATABASE() AND table_name = 'users' AND constraint_name = 'fk_users_classroom');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id)',
    'SELECT ''foreign key fk_users_classroom already exists'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. usersテーブルにis_master列を追加（既存チェック付き）
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'is_master');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN is_master TINYINT(1) DEFAULT 0 COMMENT ''マスター管理者フラグ: 1=マスター, 0=通常管理者'' AFTER user_type',
    'SELECT ''is_master column already exists in users'' AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. デフォルト教室データを挿入（既存チェック付き）
INSERT INTO classrooms (classroom_name, address, phone)
SELECT 'メイン教室', '', ''
WHERE NOT EXISTS (SELECT 1 FROM classrooms WHERE id = 1);

-- 7. 既存データに教室IDを設定（最初の教室IDを割り当て）
UPDATE students SET classroom_id = 1 WHERE classroom_id IS NULL;
UPDATE users SET classroom_id = 1 WHERE classroom_id IS NULL;

-- 8. is_activeカラムをstatusで置き換える準備（既存データをマイグレート）
UPDATE students SET status = 'active' WHERE is_active = 1;
UPDATE students SET status = 'withdrawn' WHERE is_active = 0;
