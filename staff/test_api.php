<?php
/**
 * OpenAI API接続テスト
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>API接続テスト</title></head><body>";
echo "<h1>OpenAI API接続テスト</h1>";

$apiKey = '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';

echo "<h2>1. APIキーの確認</h2>";
echo "<p>APIキー（最初の20文字）: " . substr($apiKey, 0, 20) . "...</p>";

echo "<h2>2. 簡単なテストリクエスト</h2>";

$data = [
    'model' => 'gpt-4-turbo-preview',
    'messages' => [
        [
            'role' => 'user',
            'content' => 'こんにちは。簡単な応答をお願いします。'
        ]
    ],
    'max_tokens' => 100
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h3>HTTPステータスコード: " . $httpCode . "</h3>";

if ($curlError) {
    echo "<p style='color: red;'>CURLエラー: " . htmlspecialchars($curlError) . "</p>";
}

echo "<h3>レスポンス:</h3>";
echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>";
echo htmlspecialchars($response);
echo "</pre>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo "<h3 style='color: green;'>✓ API接続成功！</h3>";
        echo "<p>応答内容: " . htmlspecialchars($result['choices'][0]['message']['content']) . "</p>";
    }
} else {
    echo "<h3 style='color: red;'>✗ API接続失敗</h3>";

    $error = json_decode($response, true);
    if (isset($error['error']['message'])) {
        echo "<p style='color: red;'>エラーメッセージ: " . htmlspecialchars($error['error']['message']) . "</p>";
    }
}

echo "</body></html>";
