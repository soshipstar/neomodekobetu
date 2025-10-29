<?php
/**
 * 週間計画表の提出物保存テスト
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<h1>週間計画表提出物の保存テスト</h1>";

// テスト用の週間計画を作成
$studentId = 12; // テスト用生徒ID
$weekStartDate = date('Y-m-d', strtotime('monday this week'));

echo "<h2>テスト条件</h2>";
echo "<p>生徒ID: {$studentId}</p>";
echo "<p>週開始日: {$weekStartDate}</p>";

try {
    $pdo->beginTransaction();

    // 既存の計画があるかチェック
    $stmt = $pdo->prepare("
        SELECT id FROM weekly_plans
        WHERE student_id = ? AND week_start_date = ?
    ");
    $stmt->execute([$studentId, $weekStartDate]);
    $existingPlan = $stmt->fetch();

    if ($existingPlan) {
        $planId = $existingPlan['id'];
        echo "<p>✓ 既存の週間計画を使用 (ID: {$planId})</p>";
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO weekly_plans (
                student_id,
                week_start_date,
                weekly_goal,
                plan_data,
                created_by_type,
                created_by_id,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, 'staff', 1, NOW(), NOW())
        ");
        $stmt->execute([
            $studentId,
            $weekStartDate,
            'テスト用週間目標',
            json_encode(['day_0' => 'テスト計画'])
        ]);
        $planId = $pdo->lastInsertId();
        echo "<p>✓ 新規週間計画を作成 (ID: {$planId})</p>";
    }

    // 既存の提出物を削除
    $stmt = $pdo->prepare("DELETE FROM weekly_plan_submissions WHERE weekly_plan_id = ?");
    $stmt->execute([$planId]);

    // テスト用提出物を追加
    $testSubmissions = [
        ['item' => '数学の宿題', 'due_date' => date('Y-m-d', strtotime('+3 days'))],
        ['item' => '理科のレポート', 'due_date' => date('Y-m-d', strtotime('+5 days'))],
        ['item' => '読書感想文', 'due_date' => date('Y-m-d', strtotime('+7 days'))]
    ];

    echo "<h2>提出物の保存</h2>";
    foreach ($testSubmissions as $sub) {
        $stmt = $pdo->prepare("
            INSERT INTO weekly_plan_submissions (
                weekly_plan_id,
                submission_item,
                due_date,
                is_completed,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, 0, NOW(), NOW())
        ");
        $stmt->execute([
            $planId,
            $sub['item'],
            $sub['due_date']
        ]);
        echo "<p>✓ 保存: {$sub['item']} (期限: {$sub['due_date']})</p>";
    }

    $pdo->commit();

    echo "<h2>保存結果の確認</h2>";

    // 保存された提出物を取得
    $stmt = $pdo->prepare("
        SELECT * FROM weekly_plan_submissions
        WHERE weekly_plan_id = ?
        ORDER BY due_date
    ");
    $stmt->execute([$planId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($submissions)) {
        echo "<p><strong>保存成功！ " . count($submissions) . "件の提出物が保存されました。</strong></p>";
        echo "<pre>";
        print_r($submissions);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>エラー: 提出物が保存されませんでした。</p>";
    }

    // 生徒の提出物画面で取得されるか確認
    echo "<h2>生徒画面での取得確認</h2>";
    $stmt = $pdo->prepare("
        SELECT
            wps.id,
            wps.submission_item as title,
            wps.due_date,
            wps.is_completed,
            'weekly_plan' as source
        FROM weekly_plan_submissions wps
        INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
        WHERE wp.student_id = ?
    ");
    $stmt->execute([$studentId]);
    $studentSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($studentSubmissions)) {
        echo "<p><strong>✓ 生徒画面で取得可能: " . count($studentSubmissions) . "件</strong></p>";
        echo "<pre>";
        print_r($studentSubmissions);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ 生徒画面で取得できません</p>";
    }

    echo "<hr>";
    echo "<p><a href='debug_submissions.php?test_student_id={$studentId}'>デバッグページで確認する</a></p>";
    echo "<p><a href='staff/student_weekly_plan_detail.php?student_id={$studentId}&date={$weekStartDate}'>週間計画表を編集する</a></p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color: red;'>エラー: " . $e->getMessage() . "</p>";
}
