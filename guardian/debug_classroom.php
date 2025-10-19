<?php
/**
 * 保護者ページの教室情報デバッグ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();
checkUserType('guardian');

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>教室情報デバッグ</title>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f0f0f0;}</style>";
echo "</head><body>";

echo "<h1>教室情報デバッグ</h1>";

// 1. ログインユーザー情報
echo "<h2>1. ログインユーザー情報</h2>";
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$guardianId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>項目</th><th>値</th></tr>";
foreach ($user as $key => $value) {
    if ($key === 'password') continue;
    echo "<tr><td>{$key}</td><td>" . ($value ?? 'NULL') . "</td></tr>";
}
echo "</table>";

// 2. 教室情報（JOIN使用）
echo "<h2>2. 教室情報取得（JOIN使用）</h2>";
$stmt = $pdo->prepare("
    SELECT c.*, u.classroom_id as user_classroom_id
    FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroomJoin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($classroomJoin) {
    echo "<table>";
    echo "<tr><th>項目</th><th>値</th></tr>";
    foreach ($classroomJoin as $key => $value) {
        echo "<tr><td>{$key}</td><td>" . ($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ JOIN使用では教室情報が取得できませんでした</p>";
}

// 3. 教室情報（直接取得）
echo "<h2>3. 教室情報取得（直接取得）</h2>";
if ($user['classroom_id']) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$user['classroom_id']]);
    $classroomDirect = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($classroomDirect) {
        echo "<p style='color:green;'>✓ 教室情報が取得できました</p>";
        echo "<table>";
        echo "<tr><th>項目</th><th>値</th></tr>";
        foreach ($classroomDirect as $key => $value) {
            echo "<tr><td>{$key}</td><td>" . ($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";

        // ロゴファイルの存在確認
        if (!empty($classroomDirect['logo_path'])) {
            $logoFullPath = __DIR__ . '/../' . $classroomDirect['logo_path'];
            echo "<h3>ロゴファイル確認</h3>";
            echo "<p>ロゴパス: {$classroomDirect['logo_path']}</p>";
            echo "<p>フルパス: {$logoFullPath}</p>";
            echo "<p>ファイル存在: " . (file_exists($logoFullPath) ? '✓ あり' : '❌ なし') . "</p>";
            if (file_exists($logoFullPath)) {
                echo "<p>プレビュー:</p>";
                echo "<img src='../{$classroomDirect['logo_path']}' style='max-height:100px;border:1px solid #ddd;'>";
            }
        } else {
            echo "<p>ロゴパスが設定されていません</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ 教室ID={$user['classroom_id']}の教室が見つかりませんでした</p>";
    }
} else {
    echo "<p style='color:red;'>❌ ユーザーにclassroom_idが設定されていません</p>";
}

// 4. 全教室一覧
echo "<h2>4. 全教室一覧</h2>";
$stmt = $pdo->query("SELECT * FROM classrooms ORDER BY id");
$allClassrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>教室名</th><th>住所</th><th>電話番号</th><th>ロゴパス</th></tr>";
foreach ($allClassrooms as $c) {
    echo "<tr>";
    echo "<td>{$c['id']}</td>";
    echo "<td>{$c['classroom_name']}</td>";
    echo "<td>" . ($c['address'] ?? '-') . "</td>";
    echo "<td>" . ($c['phone'] ?? '-') . "</td>";
    echo "<td>" . ($c['logo_path'] ?? '-') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='index.php'>← 保護者ページに戻る</a></p>";
echo "</body></html>";
