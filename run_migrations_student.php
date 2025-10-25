<?php
/**
 * 生徒用機能のマイグレーション実行スクリプト
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>生徒用機能マイグレーション</title>";
echo "<style>
    body { font-family: sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #28a745; }
    .error { color: #721c24; background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #dc3545; }
    .info { color: #004085; background: #cce5ff; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #004085; }
    h1 { color: #333; }
    h2 { color: #667eea; margin-top: 30px; }
</style>";
echo "</head><body>";

echo "<h1>🎓 生徒用機能マイグレーション</h1>";

$migrations = [
    'v30' => [
        'file' => 'migration_v30_add_student_login.sql',
        'description' => '生徒用ログイン情報の追加',
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'students'
                    AND COLUMN_NAME = 'username'"
    ],
    'v31' => [
        'file' => 'migration_v31_create_student_chat.sql',
        'description' => '生徒用チャット機能',
        'check' => "SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'student_chat_rooms'"
    ],
    'v32' => [
        'file' => 'migration_v32_create_weekly_plans.sql',
        'description' => '週間計画表機能',
        'check' => "SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'weekly_plans'"
    ]
];

foreach ($migrations as $version => $migration) {
    echo "<h2>📋 {$version}: {$migration['description']}</h2>";

    try {
        // 既に適用済みかチェック
        $stmt = $pdo->query($migration['check']);
        $exists = $stmt->fetchColumn();

        if ($exists > 0) {
            echo "<div class='info'>✓ 既に適用済みです。スキップします。</div>";
            continue;
        }

        // マイグレーションファイルを読み込んで実行
        $sqlFile = __DIR__ . '/' . $migration['file'];
        if (!file_exists($sqlFile)) {
            echo "<div class='error'>❌ ファイルが見つかりません: {$migration['file']}</div>";
            continue;
        }

        $sql = file_get_contents($sqlFile);

        // 複数のステートメントを分割して実行
        // DDL文は自動コミットされるため、トランザクション不要
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            $pdo->exec($statement);
        }

        echo "<div class='success'>✓ マイグレーション完了</div>";

    } catch (Exception $e) {
        echo "<div class='error'>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "<div class='success'>";
echo "<h2>✓ すべてのマイグレーションが完了しました</h2>";
echo "<p><a href='admin/index.php'>← 管理画面に戻る</a></p>";
echo "</div>";

echo "</body></html>";
