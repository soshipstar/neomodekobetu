<?php
/**
 * セッション情報確認スクリプト
 */

// セッション開始（まだ開始されていない場合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>セッションステータス</h1>";
echo "<p>Session Status: " . session_status() . " (2=active)</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";

echo "<h1>セッション情報</h1>";

if (empty($_SESSION)) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<h2>⚠️ セッションが空です</h2>";
    echo "<p>ログインしていないか、セッションが切れています。</p>";
    echo "<p><a href='login.php' style='color: #004085; font-weight: bold;'>→ ログインページへ</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 10px;'>";
    echo "✓ ログイン中です";
    echo "</div>";
}

echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>活動ID 94の情報</h2>";

require_once __DIR__ . '/config/database.php';
$pdo = getDbConnection();

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
echo "<p>現在のユーザーのclassroom_id: " . ($_SESSION['classroom_id'] ?? 'NULL') . "</p>";
echo "<p>活動作成者のclassroom_id: " . ($activity['classroom_id'] ?? 'NULL') . "</p>";

if (isset($_SESSION['classroom_id']) && isset($activity['classroom_id'])) {
    if ($_SESSION['classroom_id'] == $activity['classroom_id']) {
        echo "<p style='color: green;'>✓ 教室IDが一致しています</p>";
    } else {
        echo "<p style='color: red;'>✗ 教室IDが一致していません</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ どちらかのclassroom_idがNULLです</p>";
}
