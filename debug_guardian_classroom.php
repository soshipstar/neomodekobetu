<?php
/**
 * 保護者の教室情報デバッグ
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<h2>保護者アカウントの教室ID確認</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>ユーザー名</th><th>氏名</th><th>教室ID</th><th>教室名</th></tr>";

$stmt = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.classroom_id,
        c.classroom_name
    FROM users u
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    WHERE u.user_type = 'guardian'
    ORDER BY u.id
    LIMIT 10
");

$guardians = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($guardians as $guardian) {
    echo "<tr>";
    echo "<td>{$guardian['id']}</td>";
    echo "<td>{$guardian['username']}</td>";
    echo "<td>{$guardian['full_name']}</td>";
    echo "<td>" . ($guardian['classroom_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($guardian['classroom_name'] ?? '-') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>全教室一覧</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>教室名</th><th>住所</th><th>ロゴパス</th></tr>";

$stmt = $pdo->query("SELECT * FROM classrooms ORDER BY id");
$classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($classrooms as $classroom) {
    echo "<tr>";
    echo "<td>{$classroom['id']}</td>";
    echo "<td>{$classroom['classroom_name']}</td>";
    echo "<td>" . ($classroom['address'] ?? '-') . "</td>";
    echo "<td>" . ($classroom['logo_path'] ?? '-') . "</td>";
    echo "</tr>";
}

echo "</table>";
