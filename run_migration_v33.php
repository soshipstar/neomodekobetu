<?php
/**
 * v33マイグレーション実行スクリプト - 平文パスワード保存
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>v33マイグレーション</title>";
echo "<style>
    body { font-family: sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #28a745; }
    .error { color: #721c24; background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #dc3545; }
    .info { color: #004085; background: #cce5ff; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #004085; }
    h1 { color: #333; }
</style>";
echo "</head><body>";

echo "<h1>📊 v33マイグレーション - 平文パスワード保存</h1>";

try {
    // studentsテーブルに password_plain カラムがあるかチェック
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'students'
        AND COLUMN_NAME = 'password_plain'
    ");
    $studentColumnExists = $stmt->fetchColumn();

    // usersテーブルに password_plain カラムがあるかチェック
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'password_plain'
    ");
    $userColumnExists = $stmt->fetchColumn();

    if ($studentColumnExists > 0 && $userColumnExists > 0) {
        echo "<div class='info'>✓ 既に適用済みです。</div>";
    } else {
        // マイグレーション実行（DDL文は自動コミットされるため、トランザクション不要）
        // studentsテーブルにカラムを追加（存在しない場合のみ）
        if ($studentColumnExists == 0) {
            $pdo->exec("
                ALTER TABLE students
                ADD COLUMN password_plain VARCHAR(255) NULL COMMENT '生徒用パスワード（平文・管理者のみ閲覧可能）'
            ");
            echo "<div class='success'>✓ students.password_plain カラムを追加しました</div>";
        } else {
            echo "<div class='info'>✓ students.password_plain は既に存在します</div>";
        }

        // usersテーブルにカラムを追加（存在しない場合のみ）
        if ($userColumnExists == 0) {
            $pdo->exec("
                ALTER TABLE users
                ADD COLUMN password_plain VARCHAR(255) NULL COMMENT 'パスワード（平文・管理者のみ閲覧可能）'
            ");
            echo "<div class='success'>✓ users.password_plain カラムを追加しました</div>";
        } else {
            echo "<div class='info'>✓ users.password_plain は既に存在します</div>";
        }

        echo "<div class='success'>✓ マイグレーション完了</div>";
        echo "<div class='info'>
            <p><strong>注意：</strong></p>
            <ul>
                <li>平文パスワードは管理者のみが閲覧できます</li>
                <li>既存のアカウントのパスワードは、次回変更時に平文でも保存されます</li>
            </ul>
        </div>";
    }

    echo "<p><a href='admin/index.php'>← 管理画面に戻る</a></p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div class='error'>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
