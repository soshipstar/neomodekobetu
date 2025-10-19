<?php
/**
 * かけはし生成デバッグスクリプト
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>かけはし生成デバッグ</h1>";

// 田中ボウリン健太郎のデータで直接テスト
$studentId = 6;
$studentName = '田中ボウリン健太郎';
$supportStartDate = '2022-08-07';

echo "<p>生徒ID: {$studentId}</p>";
echo "<p>生徒名: {$studentName}</p>";
echo "<p>支援開始日: {$supportStartDate}</p>";
echo "<hr>";

try {
    // 既存削除
    echo "<p>既存データ削除...</p>";
    $pdo->exec("DELETE FROM kakehashi_guardian WHERE student_id = {$studentId}");
    $pdo->exec("DELETE FROM kakehashi_staff WHERE student_id = {$studentId}");
    $pdo->exec("DELETE FROM kakehashi_periods WHERE student_id = {$studentId}");
    echo "<p style='color:green'>✓ 削除完了</p>";

    // 1回目を手動生成
    echo "<h2>1回目かけはし生成</h2>";
    $supportStartDateTime = new DateTime($supportStartDate);
    $firstDeadline = clone $supportStartDateTime;
    $firstDeadline->modify('-1 day');

    $firstStartDate = clone $firstDeadline;
    $firstStartDate->modify('+1 day');

    $firstEndDate = clone $firstStartDate;
    $firstEndDate->modify('+6 months');

    echo "<p>提出期限: " . $firstDeadline->format('Y-m-d') . "</p>";
    echo "<p>開始日: " . $firstStartDate->format('Y-m-d') . "</p>";
    echo "<p>終了日: " . $firstEndDate->format('Y-m-d') . "</p>";

    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        "初回かけはし（{$studentName}）",
        $firstStartDate->format('Y-m-d'),
        $firstEndDate->format('Y-m-d'),
        $firstDeadline->format('Y-m-d')
    ]);
    $firstPeriodId = $pdo->lastInsertId();
    echo "<p style='color:green'>✓ 1回目生成完了 (ID: {$firstPeriodId})</p>";

    // 2回目を手動生成
    echo "<h2>2回目かけはし生成</h2>";
    $secondDeadline = clone $firstDeadline;
    $secondDeadline->modify('+4 months');

    $secondStartDate = clone $secondDeadline;
    $secondStartDate->modify('+1 day');

    $secondEndDate = clone $secondStartDate;
    $secondEndDate->modify('+6 months');

    echo "<p>提出期限: " . $secondDeadline->format('Y-m-d') . "</p>";
    echo "<p>開始日: " . $secondStartDate->format('Y-m-d') . "</p>";
    echo "<p>終了日: " . $secondEndDate->format('Y-m-d') . "</p>";

    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        "2回目かけはし（{$studentName}）",
        $secondStartDate->format('Y-m-d'),
        $secondEndDate->format('Y-m-d'),
        $secondDeadline->format('Y-m-d')
    ]);
    $secondPeriodId = $pdo->lastInsertId();
    echo "<p style='color:green'>✓ 2回目生成完了 (ID: {$secondPeriodId})</p>";

    // 3回目を手動生成
    echo "<h2>3回目かけはし生成</h2>";
    $thirdDeadline = clone $secondDeadline;
    $thirdDeadline->modify('+6 months');

    $thirdStartDate = clone $thirdDeadline;
    $thirdStartDate->modify('+1 day');

    $thirdEndDate = clone $thirdStartDate;
    $thirdEndDate->modify('+6 months');

    echo "<p>提出期限: " . $thirdDeadline->format('Y-m-d') . "</p>";
    echo "<p>開始日: " . $thirdStartDate->format('Y-m-d') . "</p>";
    echo "<p>終了日: " . $thirdEndDate->format('Y-m-d') . "</p>";

    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        "3回目かけはし（{$studentName}）",
        $thirdStartDate->format('Y-m-d'),
        $thirdEndDate->format('Y-m-d'),
        $thirdDeadline->format('Y-m-d')
    ]);
    $thirdPeriodId = $pdo->lastInsertId();
    echo "<p style='color:green'>✓ 3回目生成完了 (ID: {$thirdPeriodId})</p>";

    // 4回目以降を生成
    echo "<h2>4回目以降生成</h2>";
    $today = new DateTime();
    $currentDeadline = clone $thirdDeadline;
    $periodCount = 4;

    while (true) {
        $nextDeadline = clone $currentDeadline;
        $nextDeadline->modify('+6 months');

        if ($nextDeadline > $today) {
            echo "<p>次の期限 " . $nextDeadline->format('Y-m-d') . " は本日より未来なので終了</p>";
            break;
        }

        $nextStartDate = clone $nextDeadline;
        $nextStartDate->modify('+1 day');

        $nextEndDate = clone $nextStartDate;
        $nextEndDate->modify('+6 months');

        echo "<p>{$periodCount}回目 - 提出期限: " . $nextDeadline->format('Y-m-d') . ", 期間: " . $nextStartDate->format('Y-m-d') . " ～ " . $nextEndDate->format('Y-m-d') . "</p>";

        $stmt = $pdo->prepare("
            INSERT INTO kakehashi_periods (
                student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $studentId,
            "{$periodCount}回目かけはし（{$studentName}）",
            $nextStartDate->format('Y-m-d'),
            $nextEndDate->format('Y-m-d'),
            $nextDeadline->format('Y-m-d')
        ]);

        echo "<p style='color:green'>✓ {$periodCount}回目生成完了</p>";

        $currentDeadline = $nextDeadline;
        $periodCount++;
    }

    echo "<hr>";
    echo "<h2>生成完了！</h2>";
    echo "<p><a href='check_student_data.php'>データ確認ページへ</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
