<?php
/**
 * 統合ノートのデバッグスクリプト
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

// VRチャットのアバター作りの活動を探す
$stmt = $pdo->prepare("
    SELECT id, activity_name, record_date
    FROM daily_records
    WHERE activity_name LIKE '%VRチャット%' OR activity_name LIKE '%3Dモデル%'
    ORDER BY record_date DESC
    LIMIT 5
");
$stmt->execute();
$activities = $stmt->fetchAll();

echo "<h1>VRチャット関連の活動</h1>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>活動名</th><th>日付</th></tr>";
foreach ($activities as $activity) {
    echo "<tr>";
    echo "<td>{$activity['id']}</td>";
    echo "<td>" . htmlspecialchars($activity['activity_name']) . "</td>";
    echo "<td>{$activity['record_date']}</td>";
    echo "</tr>";
}
echo "</table>";

// 最新の活動の統合ノートを確認
if (!empty($activities)) {
    $activityId = $activities[0]['id'];

    echo "<h2>活動ID {$activityId} の統合ノート</h2>";

    $stmt = $pdo->prepare("
        SELECT *
        FROM integrated_notes
        WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);
    $notes = $stmt->fetchAll();

    echo "<p>統合ノート数: " . count($notes) . "</p>";

    if (count($notes) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>生徒ID</th><th>送信済み</th><th>送信日時</th><th>内容（最初の100文字）</th></tr>";
        foreach ($notes as $note) {
            echo "<tr>";
            echo "<td>{$note['id']}</td>";
            echo "<td>{$note['student_id']}</td>";
            echo "<td>" . ($note['is_sent'] ? '✓' : '✗') . "</td>";
            echo "<td>" . ($note['sent_at'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars(mb_substr($note['integrated_content'], 0, 100)) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>統合ノートが見つかりません！</p>";
    }

    // サブクエリでのカウントを確認
    echo "<h2>サブクエリカウントの確認</h2>";
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = ? AND is_sent = 0) as unsent_count,
            (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = ? AND is_sent = 1) as sent_count
    ");
    $stmt->execute([$activityId, $activityId]);
    $counts = $stmt->fetch();

    echo "<p>unsent_count: {$counts['unsent_count']}</p>";
    echo "<p>sent_count: {$counts['sent_count']}</p>";
}

// integrated_notesテーブルの構造を確認
echo "<h2>integrated_notesテーブルの構造</h2>";
$stmt = $pdo->query("DESCRIBE integrated_notes");
$columns = $stmt->fetchAll();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>カラム名</th><th>型</th><th>NULL可</th><th>キー</th><th>デフォルト</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";
