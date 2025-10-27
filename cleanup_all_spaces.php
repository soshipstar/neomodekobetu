<?php
/**
 * 統合ノートの前後の空白を強力にクリーンアップ
 * 全角スペース、タブ、改行、特殊な不可視文字もすべて削除
 */

require_once __DIR__ . '/config/database.php';

// 強力なtrim処理関数
if (!function_exists('powerTrim')) {
    function powerTrim($text) {
        if ($text === null || $text === '') {
            return '';
        }
        return preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $text);
    }
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>統合ノートの強力クリーンアップ</title>";
echo "<style>
    body { font-family: sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #28a745; }
    .info { color: #004085; background: #cce5ff; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #004085; }
    .warning { color: #856404; background: #fff3cd; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #ffc107; }
    .result { margin: 20px 0; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #667eea; color: white; }
    .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    .btn:hover { background: #5568d3; }
    h1 { color: #333; }
    h2 { color: #667eea; }
</style>";
echo "</head><body>";

echo "<h1>🧹 統合ノートの強力クリーンアップ</h1>";

echo "<div class='info'>";
echo "<h3>このスクリプトについて</h3>";
echo "<p>通常のスペース、全角スペース、タブ、改行、その他の不可視文字を統合ノートから削除します。</p>";
echo "<p><strong>削除対象：</strong></p>";
echo "<ul>";
echo "<li>半角スペース、タブ、改行</li>";
echo "<li>全角スペース（U+3000）</li>";
echo "<li>ノーブレークスペース（U+00A0）</li>";
echo "<li>その他の各種スペース文字（U+00A0～U+200B）</li>";
echo "<li>ゼロ幅ノーブレークスペース（BOM）</li>";
echo "</ul>";
echo "</div>";

try {
    // すべての統合ノートを取得
    $stmt = $pdo->query("
        SELECT id, integrated_content,
               LENGTH(integrated_content) as original_length
        FROM integrated_notes
    ");
    $allNotes = $stmt->fetchAll();

    $totalCount = count($allNotes);
    echo "<div class='result'>";
    echo "<p><strong>総統合ノート数:</strong> {$totalCount}件</p>";
    echo "</div>";

    // クリーンアップが必要なものをカウント
    $needsCleaning = 0;
    $cleanupData = [];

    foreach ($allNotes as $note) {
        $original = $note['integrated_content'];
        $cleaned = powerTrim($original);

        if ($original !== $cleaned) {
            $needsCleaning++;
            $cleanupData[] = [
                'id' => $note['id'],
                'original_length' => strlen($original),
                'cleaned_length' => strlen($cleaned),
                'removed_bytes' => strlen($original) - strlen($cleaned),
                'original_preview' => mb_substr($original, 0, 100, 'UTF-8'),
                'cleaned_preview' => mb_substr($cleaned, 0, 100, 'UTF-8')
            ];
        }
    }

    if ($needsCleaning === 0) {
        echo "<div class='success'>";
        echo "<h2>✓ クリーンアップ不要</h2>";
        echo "<p>すべての統合ノートは既にクリーンです。</p>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<h2>⚠️ クリーンアップが必要</h2>";
        echo "<p><strong>{$needsCleaning}件</strong>の統合ノートに前後の不要な文字が見つかりました。</p>";
        echo "</div>";

        // 最初の10件を表示
        echo "<div class='result'>";
        echo "<h3>クリーンアップ対象（最初の10件）</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>元の長さ</th><th>変更後</th><th>削除バイト数</th><th>変更前（最初の100文字）</th></tr>";

        foreach (array_slice($cleanupData, 0, 10) as $data) {
            echo "<tr>";
            echo "<td>{$data['id']}</td>";
            echo "<td>{$data['original_length']}</td>";
            echo "<td>{$data['cleaned_length']}</td>";
            echo "<td><strong>{$data['removed_bytes']}</strong></td>";
            echo "<td>" . htmlspecialchars($data['original_preview']) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";

        // クリーンアップ実行
        echo "<div class='info'>";
        echo "<p>クリーンアップを実行しています...</p>";
        echo "</div>";

        $pdo->beginTransaction();

        $updatedCount = 0;
        foreach ($allNotes as $note) {
            $original = $note['integrated_content'];
            $cleaned = powerTrim($original);

            if ($original !== $cleaned) {
                $stmt = $pdo->prepare("
                    UPDATE integrated_notes
                    SET integrated_content = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$cleaned, $note['id']]);
                $updatedCount++;
            }
        }

        $pdo->commit();

        echo "<div class='success'>";
        echo "<h2>✓ クリーンアップ完了</h2>";
        echo "<p><strong>{$updatedCount}件</strong>の統合ノートをクリーンアップしました。</p>";
        echo "</div>";

        // 確認
        $stmt = $pdo->query("SELECT COUNT(*) FROM integrated_notes");
        $totalAfter = $stmt->fetchColumn();

        echo "<div class='result'>";
        echo "<h3>確認結果</h3>";
        echo "<p>総統合ノート数: <strong>{$totalAfter}件</strong></p>";
        echo "<p style='color: green; font-weight: bold;'>✓ すべての統合ノートがクリーンアップされました。</p>";
        echo "</div>";
    }

    echo "<a href='staff/renrakucho_activities.php' class='btn'>← 活動管理ページに戻る</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div style='color: red; background: #f8d7da; padding: 20px; border-radius: 5px; border-left: 5px solid #dc3545;'>";
    echo "<h2>❌ エラーが発生しました</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
