<?php
/**
 * 10月22日の黒野斗真のデータを詳細調査
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>スペース問題調査</title>";
echo "<style>
    body { font-family: monospace; max-width: 1200px; margin: 50px auto; padding: 20px; }
    .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
    .hex { background: #fff3cd; padding: 10px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background: #e9ecef; }
    .highlight { background: yellow; }
</style>";
echo "</head><body>";

echo "<h1>🔍 10月22日 黒野斗真のデータ調査</h1>";

// 黒野斗真の生徒IDを検索
$stmt = $pdo->prepare("
    SELECT id, student_name FROM students WHERE student_name LIKE '%黒野%' OR student_name LIKE '%斗真%'
");
$stmt->execute();
$students = $stmt->fetchAll();

echo "<div class='section'>";
echo "<h2>黒野斗真の検索結果</h2>";
foreach ($students as $student) {
    echo "<p>生徒ID: {$student['id']}, 氏名: " . htmlspecialchars($student['student_name']) . "</p>";
}
echo "</div>";

if (!empty($students)) {
    $studentId = $students[0]['id'];

    // 10月22日の統合ノートを取得
    $stmt = $pdo->prepare("
        SELECT in1.*, dr.activity_name, dr.record_date
        FROM integrated_notes in1
        INNER JOIN daily_records dr ON in1.daily_record_id = dr.id
        WHERE in1.student_id = ? AND dr.record_date = '2025-10-22'
    ");
    $stmt->execute([$studentId]);
    $notes = $stmt->fetchAll();

    echo "<div class='section'>";
    echo "<h2>10月22日の統合ノート</h2>";
    echo "<p>見つかった件数: " . count($notes) . "</p>";
    echo "</div>";

    foreach ($notes as $note) {
        echo "<div class='section'>";
        echo "<h3>活動: " . htmlspecialchars($note['activity_name']) . " (ID: {$note['id']})</h3>";

        $content = $note['integrated_content'];

        // 文字数情報
        echo "<table>";
        echo "<tr><th>項目</th><th>値</th></tr>";
        echo "<tr><td>全体の長さ</td><td>" . strlen($content) . " バイト</td></tr>";
        echo "<tr><td>文字数（mb_strlen）</td><td>" . mb_strlen($content, 'UTF-8') . " 文字</td></tr>";
        echo "<tr><td>trim後の長さ</td><td>" . strlen(trim($content)) . " バイト</td></tr>";
        echo "</table>";

        // 最初の100文字の16進ダンプ
        echo "<h4>最初の100バイトの16進ダンプ</h4>";
        echo "<div class='hex'>";
        $first100 = substr($content, 0, 100);
        for ($i = 0; $i < strlen($first100); $i++) {
            $hex = bin2hex($first100[$i]);
            $char = $first100[$i];

            // 特殊文字を強調表示
            if ($hex == '20') {
                echo "<span class='highlight' title='半角スペース (0x20)'>[SP]</span>";
            } elseif ($hex == '09') {
                echo "<span class='highlight' title='タブ (0x09)'>[TAB]</span>";
            } elseif ($hex == '0a') {
                echo "<span class='highlight' title='改行 (0x0A)'>[LF]</span>";
            } elseif ($hex == '0d') {
                echo "<span class='highlight' title='復帰 (0x0D)'>[CR]</span>";
            } elseif (in_array($hex, ['e38080', '3000'])) {
                echo "<span class='highlight' title='全角スペース'>[全角SP]</span>";
            } elseif (ord($char) < 32) {
                echo "<span class='highlight' title='制御文字 (0x{$hex})'>[0x{$hex}]</span>";
            } else {
                echo htmlspecialchars($char);
            }
        }
        echo "</div>";

        // バイト配列表示
        echo "<h4>最初の200バイトの詳細</h4>";
        echo "<table>";
        echo "<tr><th>位置</th><th>16進</th><th>文字</th><th>説明</th></tr>";
        for ($i = 0; $i < min(200, strlen($content)); $i++) {
            $byte = $content[$i];
            $hex = bin2hex($byte);
            $ord = ord($byte);

            echo "<tr>";
            echo "<td>{$i}</td>";
            echo "<td>0x{$hex}</td>";

            if ($ord < 32 || $ord == 127) {
                echo "<td><span class='highlight'>[制御文字]</span></td>";
            } else {
                echo "<td>" . htmlspecialchars($byte) . "</td>";
            }

            // 説明
            if ($hex == '20') {
                echo "<td class='highlight'>半角スペース</td>";
            } elseif ($hex == '09') {
                echo "<td class='highlight'>タブ</td>";
            } elseif ($hex == '0a') {
                echo "<td class='highlight'>改行(LF)</td>";
            } elseif ($hex == '0d') {
                echo "<td class='highlight'>復帰(CR)</td>";
            } elseif ($hex == 'e3' && $i + 2 < strlen($content) && bin2hex(substr($content, $i, 3)) == 'e38080') {
                echo "<td class='highlight'>全角スペース (開始)</td>";
            } elseif ($ord >= 32 && $ord < 127) {
                echo "<td>ASCII文字</td>";
            } elseif ($ord >= 128) {
                echo "<td>マルチバイト文字</td>";
            } else {
                echo "<td>制御文字 (0x{$hex})</td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        // 実際の表示
        echo "<h4>実際の内容（最初の500文字）</h4>";
        echo "<pre style='background: white; padding: 15px; border: 1px solid #ddd;'>";
        echo htmlspecialchars(mb_substr($content, 0, 500, 'UTF-8'));
        echo "</pre>";
        echo "</div>";
    }
}

echo "</body></html>";
