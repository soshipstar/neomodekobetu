<?php
/**
 * 統合ノートの前後の空白をクリーンアップ
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>統合ノートのスペースクリーンアップ</title>";
echo "<style>
    body { font-family: sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { color: #004085; background: #cce5ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .result { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f8f9fa; }
</style>";
echo "</head><body>";

echo "<h1>🧹 統合ノートのスペースクリーンアップ</h1>";

try {
    // 前後にスペースがある統合ノートを取得
    $stmt = $pdo->query("
        SELECT id, daily_record_id, student_id, integrated_content, is_sent,
               LENGTH(integrated_content) as original_length,
               LENGTH(TRIM(integrated_content)) as trimmed_length
        FROM integrated_notes
        WHERE integrated_content != TRIM(integrated_content)
    ");
    $notesToClean = $stmt->fetchAll();

    $totalCount = count($notesToClean);

    if ($totalCount === 0) {
        echo "<div class='success'>✓ クリーンアップが必要な統合ノートはありません。すべて正常です。</div>";
    } else {
        echo "<div class='info'>⚠️ {$totalCount}件の統合ノートに前後のスペースが見つかりました。</div>";

        echo "<h2>クリーンアップ対象</h2>";
        echo "<table>";
        echo "<tr><th>ID</th><th>活動ID</th><th>生徒ID</th><th>送信済み</th><th>元の長さ</th><th>trim後の長さ</th><th>削除されるスペース</th></tr>";

        foreach ($notesToClean as $note) {
            $spaceDiff = $note['original_length'] - $note['trimmed_length'];
            $isSent = $note['is_sent'] ? '✓' : '✗';
            echo "<tr>";
            echo "<td>{$note['id']}</td>";
            echo "<td>{$note['daily_record_id']}</td>";
            echo "<td>{$note['student_id']}</td>";
            echo "<td>{$isSent}</td>";
            echo "<td>{$note['original_length']}</td>";
            echo "<td>{$note['trimmed_length']}</td>";
            echo "<td>{$spaceDiff}文字</td>";
            echo "</tr>";
        }
        echo "</table>";

        // クリーンアップ実行
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE integrated_notes
            SET integrated_content = TRIM(integrated_content),
                updated_at = NOW()
            WHERE integrated_content != TRIM(integrated_content)
        ");
        $stmt->execute();

        $updatedCount = $stmt->rowCount();

        $pdo->commit();

        echo "<div class='success'>";
        echo "<h2>✓ クリーンアップ完了</h2>";
        echo "<p><strong>{$updatedCount}件</strong>の統合ノートをクリーンアップしました。</p>";
        echo "</div>";

        // 確認
        $stmt = $pdo->query("
            SELECT COUNT(*) as remaining
            FROM integrated_notes
            WHERE integrated_content != TRIM(integrated_content)
        ");
        $remaining = $stmt->fetchColumn();

        echo "<div class='result'>";
        echo "<h3>確認結果</h3>";
        echo "<p>残りのスペース付き統合ノート: <strong>{$remaining}件</strong></p>";
        if ($remaining == 0) {
            echo "<p style='color: green;'>✓ すべての統合ノートがクリーンアップされました。</p>";
        }
        echo "</div>";
    }

    echo "<p style='margin-top: 30px;'><a href='staff/renrakucho_activities.php'>← 活動管理ページに戻る</a></p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h2>❌ エラーが発生しました</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
