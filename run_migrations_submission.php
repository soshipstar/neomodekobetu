<?php
/**
 * 提出期限管理機能のマイグレーション実行スクリプト
 * このスクリプトを実行すると、submission_requests関連のテーブルとカラムが作成されます
 */

require_once __DIR__ . '/config/database.php';

echo "========================================\n";
echo "提出期限管理機能のマイグレーション実行\n";
echo "========================================\n\n";

try {
    $pdo = getDbConnection();

    // v24: submission_requestsテーブルの作成
    echo "【ステップ1】submission_requestsテーブルを確認中...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'submission_requests'");
    if ($stmt->rowCount() > 0) {
        echo "✓ submission_requestsテーブルは既に存在します\n\n";
    } else {
        echo "→ submission_requestsテーブルを作成します...\n";

        $sql = "
        CREATE TABLE IF NOT EXISTS submission_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            student_id INT NOT NULL,
            guardian_id INT NOT NULL,
            created_by INT NOT NULL COMMENT 'スタッフID',
            title VARCHAR(255) NOT NULL COMMENT '提出物タイトル',
            description TEXT COMMENT '詳細説明',
            due_date DATE NOT NULL COMMENT '提出期限',
            is_completed TINYINT(1) DEFAULT 0 COMMENT '提出完了フラグ',
            completed_at DATETIME COMMENT '提出完了日時',
            completed_note TEXT COMMENT '完了時のメモ',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_student_id (student_id),
            INDEX idx_guardian_id (guardian_id),
            INDEX idx_due_date (due_date),
            INDEX idx_is_completed (is_completed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提出期限管理';
        ";

        $pdo->exec($sql);
        echo "✓ submission_requestsテーブルを作成しました\n\n";
    }

    // v25: 添付ファイル関連カラムの追加
    echo "【ステップ2】添付ファイル関連カラムを確認中...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM submission_requests LIKE 'attachment_path'");
    if ($stmt->rowCount() > 0) {
        echo "✓ 添付ファイル関連カラムは既に存在します\n\n";
    } else {
        echo "→ 添付ファイル関連カラムを追加します...\n";

        $pdo->exec("ALTER TABLE submission_requests ADD COLUMN attachment_path VARCHAR(255) COMMENT '添付ファイルパス'");
        $pdo->exec("ALTER TABLE submission_requests ADD COLUMN attachment_original_name VARCHAR(255) COMMENT '添付ファイル元のファイル名'");
        $pdo->exec("ALTER TABLE submission_requests ADD COLUMN attachment_size INT COMMENT '添付ファイルサイズ（バイト）'");

        echo "✓ 添付ファイル関連カラムを追加しました\n\n";
    }

    echo "========================================\n";
    echo "✅ マイグレーション完了！\n";
    echo "========================================\n\n";
    echo "提出期限管理機能が使用できるようになりました。\n";
    echo "submission_management.php にアクセスしてください。\n";

} catch (PDOException $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    echo "\nエラーの詳細:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
