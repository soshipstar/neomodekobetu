<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>かけはし期間デバッグ</h1>";

// 全生徒のかけはし期間数を確認
$stmt = $pdo->query("
    SELECT
        s.id,
        s.student_name,
        s.support_start_date,
        COUNT(kp.id) as period_count
    FROM students s
    LEFT JOIN kakehashi_periods kp ON s.id = kp.student_id
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY s.id
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>全生徒のかけはし期間数</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>生徒ID</th><th>生徒名</th><th>支援開始日</th><th>かけはし期間数</th><th>アクション</th></tr>";

foreach ($students as $student) {
    $color = $student['period_count'] > 1 ? 'green' : ($student['period_count'] == 1 ? 'orange' : 'red');
    echo "<tr style='color: {$color};'>";
    echo "<td>{$student['id']}</td>";
    echo "<td>" . htmlspecialchars($student['student_name']) . "</td>";
    echo "<td>" . ($student['support_start_date'] ?? '<span style="color:red;">未設定</span>') . "</td>";
    echo "<td>{$student['period_count']}</td>";
    echo "<td><a href='?detail={$student['id']}'>詳細</a>";

    if ($student['period_count'] > 0) {
        echo " | <a href='?delete={$student['id']}' onclick='return confirm(\"この生徒の全かけはしを削除しますか？\")'>削除</a>";
    }

    if ($student['support_start_date'] && $student['period_count'] == 0) {
        echo " | <a href='?generate={$student['id']}' style='color: green;'>生成</a>";
    }

    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><small>※ 緑=複数 / オレンジ=1件のみ / 赤=0件</small></p>";

// 削除処理
if (isset($_GET['delete'])) {
    $studentId = (int)$_GET['delete'];

    $pdo->prepare("DELETE FROM kakehashi_guardian WHERE student_id = ?")->execute([$studentId]);
    $pdo->prepare("DELETE FROM kakehashi_staff WHERE student_id = ?")->execute([$studentId]);
    $pdo->prepare("DELETE FROM kakehashi_periods WHERE student_id = ?")->execute([$studentId]);

    echo "<p style='color: green;'>✓ 生徒ID {$studentId} のかけはしを削除しました</p>";
    echo "<script>setTimeout(function(){ location.href='debug_kakehashi_periods.php'; }, 1000);</script>";
}

// 生成処理
if (isset($_GET['generate'])) {
    require_once __DIR__ . '/includes/kakehashi_auto_generator.php';

    $studentId = (int)$_GET['generate'];

    $stmt = $pdo->prepare("SELECT student_name, support_start_date FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if ($student && $student['support_start_date']) {
        try {
            $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $studentId, $student['support_start_date']);
            echo "<p style='color: green;'>✓ 生徒ID {$studentId} のかけはしを生成しました（" . count($generatedPeriods) . "件）</p>";
            echo "<script>setTimeout(function(){ location.href='debug_kakehashi_periods.php'; }, 1000);</script>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// 詳細表示
if (isset($_GET['detail'])) {
    $studentId = (int)$_GET['detail'];

    $stmt = $pdo->prepare("SELECT student_name, support_start_date FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    echo "<hr>";
    echo "<h2>生徒ID {$studentId}: " . htmlspecialchars($student['student_name']) . " のかけはし期間</h2>";
    echo "<p>支援開始日: " . ($student['support_start_date'] ?? '未設定') . "</p>";

    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_periods
        WHERE student_id = ?
        ORDER BY submission_deadline
    ");
    $stmt->execute([$studentId]);
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($periods)) {
        echo "<p style='color: red;'>かけはし期間がありません</p>";

        if ($student['support_start_date']) {
            echo "<p><a href='?generate={$studentId}' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>かけはし期間を生成</a></p>";
        } else {
            echo "<p style='color: orange;'>支援開始日が設定されていないため、生成できません</p>";
        }
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>期間名</th><th>開始日</th><th>終了日</th><th>提出期限</th><th>有効</th></tr>";

        foreach ($periods as $period) {
            echo "<tr>";
            echo "<td>{$period['id']}</td>";
            echo "<td>" . htmlspecialchars($period['period_name']) . "</td>";
            echo "<td>{$period['start_date']}</td>";
            echo "<td>{$period['end_date']}</td>";
            echo "<td>{$period['submission_deadline']}</td>";
            echo "<td>" . ($period['is_active'] ? 'はい' : 'いいえ') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "<p><a href='debug_kakehashi_periods.php'>← 一覧に戻る</a></p>";
}
