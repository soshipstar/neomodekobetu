<?php
/**
 * パスワードハッシュ生成ツール
 * データベースに登録する前に正しいハッシュを生成します
 */

$passwords = [
    'admin123' => 'admin',
    'staff123' => 'staff01',
    'guardian123' => 'guardian01'
];

echo "-- 正しいパスワードハッシュ\n\n";

foreach ($passwords as $password => $username) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "-- ユーザー: {$username} / パスワード: {$password}\n";
    echo "-- ハッシュ: {$hash}\n\n";
}

echo "\n-- 使用例:\n";
echo "INSERT INTO users (username, password, full_name, user_type, email) VALUES\n";

$adminHash = password_hash('admin123', PASSWORD_DEFAULT);
echo "('admin', '{$adminHash}', '管理者', 'admin', 'admin@example.com');\n\n";

$staffHash = password_hash('staff123', PASSWORD_DEFAULT);
echo "INSERT INTO users (username, password, full_name, user_type, email) VALUES\n";
echo "('staff01', '{$staffHash}', 'スタッフ01', 'staff', 'staff01@example.com');\n\n";

$guardianHash = password_hash('guardian123', PASSWORD_DEFAULT);
echo "INSERT INTO users (username, password, full_name, user_type, email) VALUES\n";
echo "('guardian01', '{$guardianHash}', '保護者01', 'guardian', 'guardian01@example.com');\n";
