<?php
/**
 * かけはし自動生成テストスクリプト
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/kakehashi_auto_generator.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

// テスト対象の生徒を取得
$stmt = $pdo->query("
    SELECT id, student_name, support_start_date
    FROM students
    WHERE is_active = 1
    ORDER BY id DESC
    LIMIT 5
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>かけはし生成テスト</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>かけはし自動生成テスト</h1>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
        $studentId = (int)$_POST['student_id'];

        echo "<h2>生成結果</h2>";

        // 既存のかけはし期間を削除
        echo "<p>既存のかけはし期間を削除中...</p>";
        $stmt = $pdo->prepare("DELETE FROM kakehashi_guardian WHERE student_id = ?");
        $stmt->execute([$studentId]);

        $stmt = $pdo->prepare("DELETE FROM kakehashi_staff WHERE student_id = ?");
        $stmt->execute([$studentId]);

        $stmt = $pdo->prepare("DELETE FROM kakehashi_periods WHERE student_id = ?");
        $stmt->execute([$studentId]);
        echo "<p class='success'>✓ 削除完了</p>";

        // 生徒情報を取得
        $stmt = $pdo->prepare("SELECT student_name, support_start_date FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) {
            echo "<p class='error'>生徒が見つかりません</p>";
        } elseif (!$student['support_start_date']) {
            echo "<p class='error'>支援開始日が設定されていません</p>";
        } else {
            echo "<p>生徒名: {$student['student_name']}</p>";
            echo "<p>支援開始日: {$student['support_start_date']}</p>";
            echo "<p>生成開始...</p>";

            try {
                // エラー表示を有効化
                error_reporting(E_ALL);
                ini_set('display_errors', 1);

                $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $studentId, $student['support_start_date']);

                echo "<p class='success'>✓ 生成成功: " . count($generatedPeriods) . " 件</p>";

                echo "<h3>生成されたかけはし期間</h3>";
                echo "<table>";
                echo "<tr><th>回</th><th>期間名</th><th>提出期限</th></tr>";
                foreach ($generatedPeriods as $period) {
                    echo "<tr>";
                    echo "<td>{$period['type']}</td>";
                    echo "<td>{$period['period_name']}</td>";
                    echo "<td>{$period['submission_deadline']}</td>";
                    echo "</tr>";
                }
                echo "</table>";

                // データベースから確認
                echo "<h3>データベース確認</h3>";
                $stmt = $pdo->prepare("
                    SELECT * FROM kakehashi_periods
                    WHERE student_id = ?
                    ORDER BY submission_deadline
                ");
                $stmt->execute([$studentId]);
                $dbPeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<p>データベースに保存された件数: " . count($dbPeriods) . "</p>";

                if (!empty($dbPeriods)) {
                    echo "<table>";
                    echo "<tr><th>ID</th><th>期間名</th><th>開始日</th><th>終了日</th><th>提出期限</th></tr>";
                    foreach ($dbPeriods as $period) {
                        echo "<tr>";
                        echo "<td>{$period['id']}</td>";
                        echo "<td>{$period['period_name']}</td>";
                        echo "<td>{$period['start_date']}</td>";
                        echo "<td>{$period['end_date']}</td>";
                        echo "<td>{$period['submission_deadline']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }

            } catch (Exception $e) {
                echo "<p class='error'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
        }

        echo "<p><a href='test_kakehashi_generation.php'>戻る</a></p>";

    } else {
        // 生徒選択フォーム
        echo "<h2>テスト対象の生徒を選択</h2>";
        echo "<form method='POST'>";
        echo "<table>";
        echo "<tr><th>選択</th><th>ID</th><th>生徒名</th><th>支援開始日</th></tr>";

        foreach ($students as $student) {
            echo "<tr>";
            echo "<td><input type='radio' name='student_id' value='{$student['id']}' required></td>";
            echo "<td>{$student['id']}</td>";
            echo "<td>" . htmlspecialchars($student['student_name']) . "</td>";
            echo "<td>" . ($student['support_start_date'] ?? '<span class="error">未設定</span>') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "<button type='submit'>かけはし期間を生成</button>";
        echo "</form>";
    }
    ?>
</body>
</html>
