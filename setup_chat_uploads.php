<?php
/**
 * チャット添付ファイル用ディレクトリのセットアップ
 */

$uploadDir = __DIR__ . '/uploads/chat_attachments';

// ディレクトリが存在しない場合は作成
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "✓ ディレクトリを作成しました: {$uploadDir}\n";
    } else {
        echo "✗ ディレクトリの作成に失敗しました: {$uploadDir}\n";
        exit(1);
    }
} else {
    echo "✓ ディレクトリは既に存在します: {$uploadDir}\n";
}

// .htaccessファイルを作成（直接アクセスを防ぐ）
$htaccessPath = $uploadDir . '/.htaccess';
$htaccessContent = "# チャット添付ファイル - 直接アクセス制限\n";
$htaccessContent .= "Order Deny,Allow\n";
$htaccessContent .= "Deny from all\n";

if (file_put_contents($htaccessPath, $htaccessContent)) {
    echo "✓ .htaccessファイルを作成しました\n";
} else {
    echo "✗ .htaccessファイルの作成に失敗しました\n";
}

echo "\nセットアップ完了！\n";
?>
