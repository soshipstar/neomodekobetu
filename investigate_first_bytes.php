<?php
/**
 * 最初のバイトを徹底調査
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>最初のバイト調査</title>";
echo "<style>
    body { font-family: monospace; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
    .section { margin: 20px 0; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 14px; }
    th { background: #667eea; color: white; }
    .space { background: #fff3cd; font-weight: bold; }
    .special { background: #f8d7da; font-weight: bold; }
    .normal { background: #d4edda; }
</style>";
echo "</head><body>";

echo "<h1>🔍 最初のバイト徹底調査</h1>";

// 黒野斗真の生徒IDを検索
$stmt = $pdo->prepare("
    SELECT id, student_name FROM students WHERE student_name LIKE '%黒野%' OR student_name LIKE '%斗真%'
");
$stmt->execute();
$students = $stmt->fetchAll();

if (!empty($students)) {
    $studentId = $students[0]['id'];
    echo "<p>生徒: " . htmlspecialchars($students[0]['student_name']) . " (ID: {$studentId})</p>";

    // 10月22日の統合ノートを取得
    $stmt = $pdo->prepare("
        SELECT in1.*, dr.activity_name
        FROM integrated_notes in1
        INNER JOIN daily_records dr ON in1.daily_record_id = dr.id
        WHERE in1.student_id = ? AND dr.record_date = '2025-10-22'
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $note = $stmt->fetch();

    if ($note) {
        $content = $note['integrated_content'];

        echo "<div class='section'>";
        echo "<h2>活動: " . htmlspecialchars($note['activity_name']) . "</h2>";
        echo "<p>統合ノートID: {$note['id']}</p>";
        echo "<p>全体の長さ: " . strlen($content) . " バイト (" . mb_strlen($content, 'UTF-8') . " 文字)</p>";
        echo "</div>";

        // 最初の30バイトを1バイトずつ徹底分析
        echo "<div class='section'>";
        echo "<h2>最初の30バイトの詳細分析</h2>";
        echo "<table>";
        echo "<tr><th>位置</th><th>バイト値(10進)</th><th>16進</th><th>文字</th><th>種類</th><th>説明</th></tr>";

        for ($i = 0; $i < min(30, strlen($content)); $i++) {
            $byte = $content[$i];
            $ord = ord($byte);
            $hex = sprintf('%02X', $ord);

            echo "<tr>";
            echo "<td><strong>{$i}</strong></td>";
            echo "<td>{$ord}</td>";
            echo "<td>0x{$hex}</td>";

            // 文字表示
            if ($ord == 32) {
                echo "<td class='space'>[半角SP]</td>";
                echo "<td class='space'>スペース</td>";
                echo "<td class='space'>半角スペース (ASCII 32)</td>";
            } elseif ($ord == 9) {
                echo "<td class='special'>[TAB]</td>";
                echo "<td class='special'>タブ</td>";
                echo "<td class='special'>タブ文字</td>";
            } elseif ($ord == 10) {
                echo "<td class='special'>[LF]</td>";
                echo "<td class='special'>改行</td>";
                echo "<td class='special'>改行 (Line Feed)</td>";
            } elseif ($ord == 13) {
                echo "<td class='special'>[CR]</td>";
                echo "<td class='special'>復帰</td>";
                echo "<td class='special'>キャリッジリターン</td>";
            } elseif ($ord < 32) {
                echo "<td class='special'>[制御{$ord}]</td>";
                echo "<td class='special'>制御文字</td>";
                echo "<td class='special'>制御文字 (ASCII {$ord})</td>";
            } elseif ($ord >= 32 && $ord < 127) {
                echo "<td class='normal'>" . htmlspecialchars($byte) . "</td>";
                echo "<td class='normal'>ASCII</td>";
                echo "<td class='normal'>通常のASCII文字</td>";
            } else {
                echo "<td>" . htmlspecialchars($byte) . "</td>";
                echo "<td>マルチバイト</td>";

                // 全角スペースのチェック
                if ($i + 2 < strlen($content)) {
                    $three = substr($content, $i, 3);
                    $threeHex = bin2hex($three);
                    if ($threeHex == 'e38080') {
                        echo "<td class='space'><strong>全角スペース (U+3000)</strong></td>";
                    } elseif ($threeHex == 'c2a0') {
                        echo "<td class='space'><strong>ノーブレークスペース (U+00A0)</strong></td>";
                    } else {
                        echo "<td>UTF-8マルチバイト文字の一部</td>";
                    }
                } else {
                    echo "<td>UTF-8マルチバイト文字の一部</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";

        // 16進ダンプ
        echo "<div class='section'>";
        echo "<h2>最初の50バイトの16進ダンプ</h2>";
        echo "<pre style='background: #f8f9fa; padding: 15px; overflow-x: auto;'>";
        $first50 = substr($content, 0, 50);
        echo bin2hex($first50);
        echo "</pre>";
        echo "</div>";

        // UTF-8デコード
        echo "<div class='section'>";
        echo "<h2>UTF-8文字として解釈</h2>";
        echo "<p style='font-size: 18px; background: white; padding: 15px; border: 2px solid #667eea;'>";
        echo "「" . htmlspecialchars(mb_substr($content, 0, 50, 'UTF-8')) . "」";
        echo "</p>";
        echo "</div>";

        // 各種trim結果の比較
        echo "<div class='section'>";
        echo "<h2>各種trim処理の結果</h2>";
        echo "<table>";
        echo "<tr><th>処理</th><th>結果の長さ</th><th>最初の50文字</th></tr>";

        // 通常のtrim
        $trimmed1 = trim($content);
        echo "<tr><td>trim()</td><td>" . strlen($trimmed1) . "</td><td>" . htmlspecialchars(mb_substr($trimmed1, 0, 50, 'UTF-8')) . "</td></tr>";

        // 全角スペースも含むtrim
        $trimmed2 = preg_replace('/^[\s\x{3000}\x{00A0}]+/u', '', $content);
        echo "<tr><td>preg_replace (前方のみ)</td><td>" . strlen($trimmed2) . "</td><td>" . htmlspecialchars(mb_substr($trimmed2, 0, 50, 'UTF-8')) . "</td></tr>";

        // より強力なtrim
        $trimmed3 = preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $content);
        echo "<tr><td>preg_replace (強力版)</td><td>" . strlen($trimmed3) . "</td><td>" . htmlspecialchars(mb_substr($trimmed3, 0, 50, 'UTF-8')) . "</td></tr>";

        echo "</table>";
        echo "</div>";
    } else {
        echo "<p>10月22日の統合ノートが見つかりませんでした。</p>";
    }
} else {
    echo "<p>黒野斗真さんが見つかりませんでした。</p>";
}

echo "</body></html>";
