<?php
/**
 * VRチャットの活動を送信済みに更新
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

$activityId = 94; // VRチャットのアバター作り

// 統合ノートを送信済みに更新
$stmt = $pdo->prepare("
    UPDATE integrated_notes
    SET is_sent = 1, sent_at = NOW()
    WHERE daily_record_id = ? AND is_sent = 0
");
$stmt->execute([$activityId]);

$updatedCount = $stmt->rowCount();

echo "<h1>VRチャットの活動を送信済みに更新</h1>";
echo "<p style='color: green; font-size: 18px;'>✓ {$updatedCount}件の統合ノートを送信済みに更新しました。</p>";

// 確認
$stmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = ? AND is_sent = 0) as unsent_count,
        (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = ? AND is_sent = 1) as sent_count
");
$stmt->execute([$activityId, $activityId]);
$counts = $stmt->fetch();

echo "<h2>現在の状態</h2>";
echo "<p>未送信: {$counts['unsent_count']}</p>";
echo "<p>送信済み: {$counts['sent_count']}</p>";

echo "<p><a href='staff/renrakucho_activities.php?date=2025-10-24'>連絡帳活動一覧へ戻る</a></p>";
