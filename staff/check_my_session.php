<?php
/**
 * スタッフ用セッション確認スクリプト
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// ログイン必須
requireLogin();

$currentUser = getCurrentUser();
$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>セッション確認</title></head><body>";

echo "<h1>✓ ログイン成功</h1>";

echo "<h2>現在のユーザー情報</h2>";
echo "<pre>";
print_r($currentUser);
echo "</pre>";

echo "<h2>セッション情報</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>活動ID 94の情報</h2>";

$stmt = $pdo->prepare("
    SELECT dr.id, dr.activity_name, dr.staff_id,
           u.username, u.user_type, u.classroom_id
    FROM daily_records dr
    INNER JOIN users u ON dr.staff_id = u.id
    WHERE dr.id = 94
");
$stmt->execute();
$activity = $stmt->fetch();

echo "<pre>";
print_r($activity);
echo "</pre>";

echo "<h2>比較</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>項目</th><th>値</th></tr>";
echo "<tr><td>現在のユーザーID</td><td>" . $currentUser['id'] . "</td></tr>";
echo "<tr><td>現在のユーザー名</td><td>" . htmlspecialchars($currentUser['username']) . "</td></tr>";
echo "<tr><td>現在のユーザータイプ</td><td>" . $currentUser['user_type'] . "</td></tr>";
echo "<tr><td>現在のclassroom_id</td><td>" . ($currentUser['classroom_id'] ?? 'NULL') . "</td></tr>";
echo "<tr><td>---</td><td>---</td></tr>";
echo "<tr><td>活動作成者ID</td><td>" . $activity['staff_id'] . "</td></tr>";
echo "<tr><td>活動作成者名</td><td>" . htmlspecialchars($activity['username']) . "</td></tr>";
echo "<tr><td>活動作成者のclassroom_id</td><td>" . ($activity['classroom_id'] ?? 'NULL') . "</td></tr>";
echo "</table>";

if (isset($currentUser['classroom_id']) && isset($activity['classroom_id'])) {
    if ($currentUser['classroom_id'] == $activity['classroom_id']) {
        echo "<p style='color: green; font-size: 20px; font-weight: bold;'>✓ 教室IDが一致しています - 送信可能です</p>";
    } else {
        echo "<p style='color: red; font-size: 20px; font-weight: bold;'>✗ 教室IDが一致していません - 送信できません</p>";
        echo "<p>現在の教室ID: {$currentUser['classroom_id']}</p>";
        echo "<p>活動の教室ID: {$activity['classroom_id']}</p>";
    }
} elseif (!isset($currentUser['classroom_id']) || $currentUser['classroom_id'] === null) {
    echo "<p style='color: orange; font-size: 20px; font-weight: bold;'>⚠ あなたのclassroom_idがNULLです</p>";
    echo "<p>マスター管理者の場合は正常です。スタッフ/管理者の場合は問題があります。</p>";
} else {
    echo "<p style='color: orange; font-size: 20px; font-weight: bold;'>⚠ 活動のclassroom_idがNULLです</p>";
}

echo "<p><a href='renrakucho_activities.php'>← 活動一覧に戻る</a></p>";

echo "</body></html>";
