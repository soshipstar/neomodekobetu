<?php
/**
 * 管理者アカウントとマスター管理者アカウントの作成
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>管理者アカウント作成</title></head><body>";
echo "<h1>管理者アカウント作成</h1>";
echo "<pre>";

try {
    // 1. 既存のadmin/admin123ユーザーのuser_typeを確認・変更
    echo "=== 1. 既存adminユーザーの確認 ===\n";
    $stmt = $pdo->prepare("SELECT id, username, user_type, is_master FROM users WHERE username = 'admin'");
    $stmt->execute();
    $existingAdmin = $stmt->fetch();

    if ($existingAdmin) {
        echo "既存のadminユーザーが見つかりました:\n";
        echo "  ID: {$existingAdmin['id']}\n";
        echo "  ユーザー名: {$existingAdmin['username']}\n";
        echo "  ユーザータイプ: {$existingAdmin['user_type']}\n";
        echo "  マスターフラグ: {$existingAdmin['is_master']}\n\n";

        if ($existingAdmin['user_type'] !== 'admin') {
            echo "user_typeを'admin'に変更します...\n";
            $stmt = $pdo->prepare("UPDATE users SET user_type = 'admin', is_master = 0 WHERE username = 'admin'");
            $stmt->execute();
            echo "✓ 変更完了\n\n";
        } else {
            echo "✓ 既にadminタイプです\n\n";
        }
    } else {
        echo "⚠ adminユーザーが見つかりません\n\n";
    }

    // 2. マスター管理者アカウントを作成
    echo "=== 2. マスター管理者アカウント作成 ===\n";
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'master'");
    $stmt->execute();
    $existingMaster = $stmt->fetch();

    if (!$existingMaster) {
        $username = 'master';
        $password = password_hash('master123', PASSWORD_DEFAULT);
        $fullName = 'マスター管理者';
        $email = 'master@example.com';

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, user_type, is_active, is_master, classroom_id, created_at)
            VALUES (?, ?, ?, ?, 'admin', 1, 1, 1, NOW())
        ");
        $stmt->execute([$username, $password, $fullName, $email]);

        echo "✓ マスター管理者アカウントを作成しました\n";
        echo "  ユーザー名: master\n";
        echo "  パスワード: master123\n";
        echo "  権限: マスター管理者 (is_master=1)\n\n";
    } else {
        echo "✓ masterユーザーは既に存在します (ID: {$existingMaster['id']})\n\n";
    }

    // 3. 通常の管理者アカウントを作成
    echo "=== 3. 通常管理者アカウント作成 ===\n";
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'administrator'");
    $stmt->execute();
    $existingAdministrator = $stmt->fetch();

    if (!$existingAdministrator) {
        $username = 'administrator';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $fullName = '通常管理者';
        $email = 'administrator@example.com';

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, user_type, is_active, is_master, classroom_id, created_at)
            VALUES (?, ?, ?, ?, 'admin', 1, 0, 1, NOW())
        ");
        $stmt->execute([$username, $password, $fullName, $email]);

        echo "✓ 通常管理者アカウントを作成しました\n";
        echo "  ユーザー名: administrator\n";
        echo "  パスワード: admin123\n";
        echo "  権限: 通常管理者 (is_master=0)\n\n";
    } else {
        echo "✓ administratorユーザーは既に存在します (ID: {$existingAdministrator['id']})\n\n";
    }

    // 4. 作成されたアカウント一覧を表示
    echo "=== 4. 管理者アカウント一覧 ===\n";
    $stmt = $pdo->query("
        SELECT
            id,
            username,
            full_name,
            user_type,
            is_master,
            classroom_id,
            is_active
        FROM users
        WHERE user_type = 'admin'
        ORDER BY is_master DESC, username
    ");
    $admins = $stmt->fetchAll();

    echo "ID | ユーザー名 | 氏名 | タイプ | マスター | 教室ID | 有効\n";
    echo "---|----------|------|--------|---------|--------|-----\n";
    foreach ($admins as $admin) {
        printf(
            "%2d | %-15s | %-20s | %-6s | %-8s | %6s | %s\n",
            $admin['id'],
            $admin['username'],
            $admin['full_name'],
            $admin['user_type'],
            $admin['is_master'] ? 'はい' : 'いいえ',
            $admin['classroom_id'] ?? 'NULL',
            $admin['is_active'] ? '有効' : '無効'
        );
    }

    echo "\n";
    echo "=== 完了 ===\n";
    echo "以下のアカウントでログインできます:\n\n";
    echo "【マスター管理者】\n";
    echo "  ユーザー名: master\n";
    echo "  パスワード: master123\n\n";
    echo "【通常管理者】\n";
    echo "  ユーザー名: administrator\n";
    echo "  パスワード: admin123\n\n";
    echo "【既存管理者（修正済み）】\n";
    echo "  ユーザー名: admin\n";
    echo "  パスワード: admin123\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<p><a href='login.php'>ログイン画面に戻る</a></p>";
echo "</body></html>";
