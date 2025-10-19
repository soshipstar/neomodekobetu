<?php
/**
 * 生徒データの確認スクリプト
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>生徒データ確認</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .null { color: red; font-weight: bold; }
        .ok { color: green; }
    </style>
</head>
<body>
    <h1>生徒データ確認</h1>

    <?php
    $pdo = getDbConnection();

    echo "<h2>最近の生徒データ (support_start_date 確認)</h2>";

    $stmt = $pdo->query("
        SELECT
            id,
            student_name,
            birth_date,
            support_start_date,
            created_at,
            is_active
        FROM students
        ORDER BY id DESC
        LIMIT 10
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>生徒名</th><th>生年月日</th><th>支援開始日</th><th>作成日時</th><th>有効</th></tr>";

    foreach ($students as $student) {
        $supportStartClass = empty($student['support_start_date']) ? 'null' : 'ok';
        $supportStartText = empty($student['support_start_date']) ? 'NULL' : htmlspecialchars($student['support_start_date']);

        echo "<tr>";
        echo "<td>{$student['id']}</td>";
        echo "<td>" . htmlspecialchars($student['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($student['birth_date']) . "</td>";
        echo "<td class='{$supportStartClass}'>{$supportStartText}</td>";
        echo "<td>" . htmlspecialchars($student['created_at']) . "</td>";
        echo "<td>" . ($student['is_active'] ? 'はい' : 'いいえ') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<h2>かけはし期間データ</h2>";

    $stmt = $pdo->query("
        SELECT
            kp.id,
            kp.student_id,
            s.student_name,
            kp.period_name,
            kp.start_date,
            kp.end_date,
            kp.submission_deadline,
            kp.is_active
        FROM kakehashi_periods kp
        INNER JOIN students s ON kp.student_id = s.id
        ORDER BY kp.id DESC
        LIMIT 20
    ");
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($periods)) {
        echo "<p style='color: red;'>かけはし期間データがありません。</p>";
    } else {
        echo "<table>";
        echo "<tr><th>期間ID</th><th>生徒ID</th><th>生徒名</th><th>期間名</th><th>開始日</th><th>終了日</th><th>提出期限</th><th>有効</th></tr>";

        foreach ($periods as $period) {
            echo "<tr>";
            echo "<td>{$period['id']}</td>";
            echo "<td>{$period['student_id']}</td>";
            echo "<td>" . htmlspecialchars($period['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($period['period_name']) . "</td>";
            echo "<td>" . htmlspecialchars($period['start_date']) . "</td>";
            echo "<td>" . htmlspecialchars($period['end_date']) . "</td>";
            echo "<td>" . htmlspecialchars($period['submission_deadline']) . "</td>";
            echo "<td>" . ($period['is_active'] ? 'はい' : 'いいえ') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "<h2>studentsテーブルのカラム構造</h2>";

    $stmt = $pdo->query("DESCRIBE students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>カラム名</th><th>型</th><th>NULL許可</th><th>デフォルト</th></tr>";

    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    ?>

    <p><a href="javascript:location.reload()">リロード</a></p>
</body>
</html>
