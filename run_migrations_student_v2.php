<?php
/**
 * 生徒用機能のマイグレーション実行スクリプト（詳細版）
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>生徒用機能マイグレーション（詳細版）</title>";
echo "<style>
    body { font-family: sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #28a745; }
    .error { color: #721c24; background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #dc3545; }
    .info { color: #004085; background: #cce5ff; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #004085; }
    .warning { color: #856404; background: #fff3cd; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #ffc107; }
    h1 { color: #333; }
    h2 { color: #667eea; margin-top: 30px; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";
echo "</head><body>";

echo "<h1>🎓 生徒用機能マイグレーション（詳細版）</h1>";

// 現在のデータベースを確認
$stmt = $pdo->query("SELECT DATABASE()");
$dbName = $stmt->fetchColumn();
echo "<div class='info'>データベース: <strong>$dbName</strong></div>";

// v31: 生徒用チャット機能
echo "<h2>📋 v31: 生徒用チャット機能</h2>";

try {
    // テーブルが既に存在するかチェック
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_chat_rooms'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<div class='info'>✓ student_chat_rooms は既に存在します</div>";
    } else {
        echo "<div class='warning'>student_chat_rooms を作成します...</div>";

        // student_chat_rooms テーブルを作成
        $sql1 = "CREATE TABLE student_chat_rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL COMMENT '生徒ID',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_chat (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生徒用チャットルーム'";

        echo "<pre>実行するSQL:\n" . htmlspecialchars($sql1) . "</pre>";

        $pdo->exec($sql1);
        echo "<div class='success'>✓ student_chat_rooms テーブルを作成しました</div>";
    }

    // student_chat_messages テーブルをチェック
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_chat_messages'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<div class='info'>✓ student_chat_messages は既に存在します</div>";
    } else {
        echo "<div class='warning'>student_chat_messages を作成します...</div>";

        // student_chat_messages テーブルを作成
        $sql2 = "CREATE TABLE student_chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL COMMENT 'チャットルームID',
            sender_type ENUM('student', 'staff') NOT NULL COMMENT '送信者タイプ',
            sender_id INT NOT NULL COMMENT '送信者ID（studentまたはuserテーブルのID）',
            message_type ENUM('normal', 'absence', 'event') DEFAULT 'normal' COMMENT 'メッセージタイプ',
            message TEXT NOT NULL COMMENT 'メッセージ内容',
            attachment_path VARCHAR(255) NULL COMMENT '添付ファイルパス',
            attachment_original_name VARCHAR(255) NULL COMMENT '添付ファイル元のファイル名',
            attachment_size INT NULL COMMENT '添付ファイルサイズ（バイト）',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES student_chat_rooms(id) ON DELETE CASCADE,
            INDEX idx_room_created (room_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生徒用チャットメッセージ'";

        echo "<pre>実行するSQL:\n" . htmlspecialchars($sql2) . "</pre>";

        $pdo->exec($sql2);
        echo "<div class='success'>✓ student_chat_messages テーブルを作成しました</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>詳細:\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

// v32: 週間計画表機能
echo "<h2>📋 v32: 週間計画表機能</h2>";

try {
    // weekly_plans テーブルをチェック
    $stmt = $pdo->query("SHOW TABLES LIKE 'weekly_plans'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<div class='info'>✓ weekly_plans は既に存在します</div>";
    } else {
        echo "<div class='warning'>weekly_plans を作成します...</div>";

        $sql3 = "CREATE TABLE weekly_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL COMMENT '生徒ID',
            week_start_date DATE NOT NULL COMMENT '週の開始日（月曜日）',
            plan_data JSON NOT NULL COMMENT '週間計画データ（曜日別の計画）',
            created_by_type ENUM('student', 'staff', 'guardian') NOT NULL COMMENT '作成者タイプ',
            created_by_id INT NOT NULL COMMENT '作成者ID',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_week (student_id, week_start_date),
            INDEX idx_student_date (student_id, week_start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='週間計画表'";

        echo "<pre>実行するSQL:\n" . htmlspecialchars($sql3) . "</pre>";

        $pdo->exec($sql3);
        echo "<div class='success'>✓ weekly_plans テーブルを作成しました</div>";
    }

    // weekly_plan_comments テーブルをチェック
    $stmt = $pdo->query("SHOW TABLES LIKE 'weekly_plan_comments'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<div class='info'>✓ weekly_plan_comments は既に存在します</div>";
    } else {
        echo "<div class='warning'>weekly_plan_comments を作成します...</div>";

        $sql4 = "CREATE TABLE weekly_plan_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            weekly_plan_id INT NOT NULL COMMENT '週間計画ID',
            commenter_type ENUM('student', 'staff', 'guardian') NOT NULL COMMENT 'コメント者タイプ',
            commenter_id INT NOT NULL COMMENT 'コメント者ID',
            comment TEXT NOT NULL COMMENT 'コメント内容',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (weekly_plan_id) REFERENCES weekly_plans(id) ON DELETE CASCADE,
            INDEX idx_plan_created (weekly_plan_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='週間計画表コメント'";

        echo "<pre>実行するSQL:\n" . htmlspecialchars($sql4) . "</pre>";

        $pdo->exec($sql4);
        echo "<div class='success'>✓ weekly_plan_comments テーブルを作成しました</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>詳細:\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

echo "<div class='success'>";
echo "<h2>✓ すべてのマイグレーションが完了しました</h2>";
echo "<p><a href='show_all_tables.php'>→ テーブル一覧を確認</a></p>";
echo "<p><a href='staff/student_chats.php'>→ 生徒チャットを開く</a></p>";
echo "<p><a href='staff/student_weekly_plans.php'>→ 週間計画表を開く</a></p>";
echo "</div>";

echo "</body></html>";
