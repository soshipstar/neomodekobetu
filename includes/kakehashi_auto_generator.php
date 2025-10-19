<?php
/**
 * かけはし自動生成ヘルパー関数
 */

/**
 * 生徒のかけはし期間を自動生成
 *
 * ルール:
 * - 初回: 支援開始日の1日前を提出期限とする
 * - 2回目: 初回期限の4ヶ月後に生成、生成日の1ヶ月後を期限
 * - 3回目以降: 6ヶ月ごとに自動生成、生成日の1ヶ月後を期限
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $supportStartDate 支援開始日 (YYYY-MM-DD)
 * @return array 生成されたかけはし期間の配列
 */
function generateKakehashiPeriodsForStudent($pdo, $studentId, $supportStartDate) {
    $generatedPeriods = [];

    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT student_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception("生徒が見つかりません: ID={$studentId}");
    }

    $studentName = $student['student_name'];

    // 既存のかけはし期間を確認
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as period_count, MAX(submission_deadline) as latest_deadline
        FROM kakehashi_periods
        WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $existingData = $stmt->fetch();
    $periodCount = (int)$existingData['period_count'];

    // すでにかけはしが存在する場合はスキップ
    if ($periodCount > 0) {
        error_log("Student {$studentId} already has kakehashi periods. Skipping auto-generation.");
        return $generatedPeriods;
    }

    // 生成上限日を計算（本日+1ヶ月）
    $today = new DateTime();
    $generationLimit = clone $today;
    $generationLimit->modify('+1 month');

    // 1. 初回かけはし: 支援開始日の1日前を提出期限
    $supportStartDateTime = new DateTime($supportStartDate);
    $firstDeadline = clone $supportStartDateTime;
    $firstDeadline->modify('-1 day');

    // 提出期限が生成上限より未来の場合はスキップ
    if ($firstDeadline > $generationLimit) {
        error_log("First kakehashi deadline {$firstDeadline->format('Y-m-d')} is beyond generation limit. Skipping.");
        return $generatedPeriods;
    }

    // 初回期間開始日は提出期限の翌日（支援開始日）
    $firstStartDate = clone $firstDeadline;
    $firstStartDate->modify('+1 day');

    // 初回期間終了日は開始日の6ヶ月後
    $firstEndDate = clone $firstStartDate;
    $firstEndDate->modify('+6 months');

    // 1. 初回かけはし
    $periodName = "初回かけはし（{$studentName}）";
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        $periodName,
        $firstStartDate->format('Y-m-d'),
        $firstEndDate->format('Y-m-d'),
        $firstDeadline->format('Y-m-d')
    ]);
    $firstPeriodId = $pdo->lastInsertId();

    $generatedPeriods[] = [
        'id' => $firstPeriodId,
        'period_name' => $periodName,
        'submission_deadline' => $firstDeadline->format('Y-m-d'),
        'type' => '初回'
    ];

    // 初回かけはし用の保護者・スタッフレコードを作成
    createKakehashiRecordsForPeriod($pdo, $firstPeriodId, $studentId);

    // 初回のモニタリングシートを作成
    createMonitoringForPeriod($pdo, $studentId, $firstDeadline->format('Y-m-d'));

    // 2. 2回目かけはし: 初回期限の4ヶ月後が提出期限
    $secondDeadline = clone $firstDeadline;
    $secondDeadline->modify('+4 months');

    // 提出期限が生成上限より未来の場合はスキップ
    if ($secondDeadline > $generationLimit) {
        error_log("Second kakehashi deadline {$secondDeadline->format('Y-m-d')} is beyond generation limit. Stopping at first period.");
        return $generatedPeriods;
    }

    // 期間開始日は提出期限の翌日
    $secondStartDate = clone $secondDeadline;
    $secondStartDate->modify('+1 day');

    // 期間終了日は開始日の6ヶ月後
    $secondEndDate = clone $secondStartDate;
    $secondEndDate->modify('+6 months');

    $periodName = "2回目かけはし（{$studentName}）";
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        $periodName,
        $secondStartDate->format('Y-m-d'),
        $secondEndDate->format('Y-m-d'),
        $secondDeadline->format('Y-m-d')
    ]);
    $secondPeriodId = $pdo->lastInsertId();

    $generatedPeriods[] = [
        'id' => $secondPeriodId,
        'period_name' => $periodName,
        'submission_deadline' => $secondDeadline->format('Y-m-d'),
        'type' => '2回目'
    ];

    createKakehashiRecordsForPeriod($pdo, $secondPeriodId, $studentId);

    // 2回目のモニタリングシートを作成
    createMonitoringForPeriod($pdo, $studentId, $secondDeadline->format('Y-m-d'));

    // 3. 3回目かけはし: 2回目期限の6ヶ月後が提出期限
    $thirdDeadline = clone $secondDeadline;
    $thirdDeadline->modify('+6 months');

    // 提出期限が生成上限より未来の場合はスキップ
    if ($thirdDeadline > $generationLimit) {
        error_log("Third kakehashi deadline {$thirdDeadline->format('Y-m-d')} is beyond generation limit. Stopping at second period.");
        return $generatedPeriods;
    }

    // 期間開始日は提出期限の翌日
    $thirdStartDate = clone $thirdDeadline;
    $thirdStartDate->modify('+1 day');

    // 期間終了日は開始日の6ヶ月後
    $thirdEndDate = clone $thirdStartDate;
    $thirdEndDate->modify('+6 months');

    $periodName = "3回目かけはし（{$studentName}）";
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        $periodName,
        $thirdStartDate->format('Y-m-d'),
        $thirdEndDate->format('Y-m-d'),
        $thirdDeadline->format('Y-m-d')
    ]);
    $thirdPeriodId = $pdo->lastInsertId();

    $generatedPeriods[] = [
        'id' => $thirdPeriodId,
        'period_name' => $periodName,
        'submission_deadline' => $thirdDeadline->format('Y-m-d'),
        'type' => '3回目'
    ];

    createKakehashiRecordsForPeriod($pdo, $thirdPeriodId, $studentId);

    // 3回目のモニタリングシートを作成
    createMonitoringForPeriod($pdo, $studentId, $thirdDeadline->format('Y-m-d'));

    // 4回目以降: 本日+1ヶ月以内に提出期限が来るものまで生成（6ヶ月ごと）
    $currentDeadline = clone $thirdDeadline;
    $periodCount = 4;

    while (true) {
        // 次の提出期限を計算（前回期限の6ヶ月後）
        $nextDeadline = clone $currentDeadline;
        $nextDeadline->modify('+6 months');

        // 次の期限が「本日+1ヶ月」より未来の場合は終了
        if ($nextDeadline > $generationLimit) {
            break;
        }

        // 期間開始日は提出期限の翌日
        $nextStartDate = clone $nextDeadline;
        $nextStartDate->modify('+1 day');

        // 期間終了日は開始日の6ヶ月後
        $nextEndDate = clone $nextStartDate;
        $nextEndDate->modify('+6 months');

        $periodName = "{$periodCount}回目かけはし（{$studentName}）";
        $stmt = $pdo->prepare("
            INSERT INTO kakehashi_periods (
                student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $studentId,
            $periodName,
            $nextStartDate->format('Y-m-d'),
            $nextEndDate->format('Y-m-d'),
            $nextDeadline->format('Y-m-d')
        ]);
        $nextPeriodId = $pdo->lastInsertId();

        $generatedPeriods[] = [
            'id' => $nextPeriodId,
            'period_name' => $periodName,
            'submission_deadline' => $nextDeadline->format('Y-m-d'),
            'type' => "{$periodCount}回目"
        ];

        createKakehashiRecordsForPeriod($pdo, $nextPeriodId, $studentId);

        // 4回目以降のモニタリングシートを作成
        createMonitoringForPeriod($pdo, $studentId, $nextDeadline->format('Y-m-d'));

        $currentDeadline = $nextDeadline;
        $periodCount++;
    }

    return $generatedPeriods;
}

/**
 * かけはし期間に対応する保護者・スタッフレコードを作成
 *
 * @param PDO $pdo データベース接続
 * @param int $periodId かけはし期間ID
 * @param int $studentId 生徒ID
 */
function createKakehashiRecordsForPeriod($pdo, $periodId, $studentId) {
    // 保護者かけはしレコードを作成
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_guardian (period_id, student_id, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE period_id = period_id
    ");
    $stmt->execute([$periodId, $studentId]);

    // スタッフかけはしレコードを作成（staff_id は NULL で作成）
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_staff (period_id, student_id, staff_id, created_at)
        VALUES (?, ?, NULL, NOW())
        ON DUPLICATE KEY UPDATE period_id = period_id
    ");
    $stmt->execute([$periodId, $studentId]);
}

/**
 * 次のかけはし期間を自動生成すべきか確認
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @return bool 生成すべき場合true
 */
function shouldGenerateNextKakehashi($pdo, $studentId) {
    // 最新のかけはし期間を取得
    $stmt = $pdo->prepare("
        SELECT submission_deadline, created_at
        FROM kakehashi_periods
        WHERE student_id = ?
        ORDER BY submission_deadline DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $latestPeriod = $stmt->fetch();

    if (!$latestPeriod) {
        return false; // かけはし期間が存在しない
    }

    // 最新期限から6ヶ月経過していたら次を生成
    $latestDeadline = new DateTime($latestPeriod['submission_deadline']);
    $nextGenerationDate = clone $latestDeadline;
    $nextGenerationDate->modify('+6 months');

    $today = new DateTime();

    return $today >= $nextGenerationDate;
}

/**
 * 定期的に次のかけはし期間を自動生成（cron等で実行）
 *
 * @param PDO $pdo データベース接続
 * @return array 生成されたかけはし期間の情報
 */
function autoGenerateNextKakehashiPeriods($pdo) {
    $generatedPeriods = [];

    // 3回目以降のかけはしを持つ全生徒を取得
    $stmt = $pdo->query("
        SELECT DISTINCT s.id, s.student_name, s.support_start_date
        FROM students s
        INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
        WHERE s.is_active = 1
        GROUP BY s.id
        HAVING COUNT(kp.id) >= 3
    ");
    $students = $stmt->fetchAll();

    foreach ($students as $student) {
        if (shouldGenerateNextKakehashi($pdo, $student['id'])) {
            // 次のかけはし期間を生成
            $newPeriod = generateNextKakehashiPeriod($pdo, $student['id'], $student['student_name']);
            $generatedPeriods[] = $newPeriod;
        }
    }

    return $generatedPeriods;
}

/**
 * 次のかけはし期間を生成（6ヶ月サイクル）
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $studentName 生徒名
 * @return array 生成されたかけはし期間の情報
 */
function generateNextKakehashiPeriod($pdo, $studentId, $studentName) {
    // 最新のかけはし期間を取得
    $stmt = $pdo->prepare("
        SELECT submission_deadline
        FROM kakehashi_periods
        WHERE student_id = ?
        ORDER BY submission_deadline DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $latestPeriod = $stmt->fetch();

    if (!$latestPeriod) {
        throw new Exception("既存のかけはし期間が見つかりません");
    }

    // 前回期限の6ヶ月後が生成日（期間開始日）
    $lastDeadline = new DateTime($latestPeriod['submission_deadline']);
    $nextStartDate = clone $lastDeadline;
    $nextStartDate->modify('+6 months');

    // 生成日の1ヶ月後が提出期限
    $nextDeadline = clone $nextStartDate;
    $nextDeadline->modify('+1 month');

    // 期間終了日は提出期限と同じ
    $nextEndDate = clone $nextDeadline;

    // 期間回数を計算
    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as next_count FROM kakehashi_periods WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $countData = $stmt->fetch();
    $periodCount = $countData['next_count'];

    $periodName = "{$periodCount}回目かけはし（{$studentName}）";

    // 新しい期間を挿入
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $studentId,
        $periodName,
        $nextStartDate->format('Y-m-d'),
        $nextEndDate->format('Y-m-d'),
        $nextDeadline->format('Y-m-d')
    ]);
    $newPeriodId = $pdo->lastInsertId();

    // レコードを作成
    createKakehashiRecordsForPeriod($pdo, $newPeriodId, $studentId);

    return [
        'id' => $newPeriodId,
        'student_id' => $studentId,
        'period_name' => $periodName,
        'submission_deadline' => $nextDeadline->format('Y-m-d'),
        'type' => '定期'
    ];
}

/**
 * かけはし期間に対応するモニタリングシートを自動作成
 * 最新の個別支援計画の内容をコピーして、評価欄のみ編集可能なモニタリングを作成
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $monitoringDate モニタリング実施日（かけはし提出期限）
 */
function createMonitoringForPeriod($pdo, $studentId, $monitoringDate) {
    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT student_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        error_log("Student not found: {$studentId}");
        return;
    }

    // 最新の個別支援計画を取得
    $stmt = $pdo->prepare("
        SELECT * FROM individual_support_plans
        WHERE student_id = ?
        ORDER BY created_date DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $latestPlan = $stmt->fetch();

    if (!$latestPlan) {
        error_log("No support plan found for student {$studentId}. Skipping monitoring creation.");
        return;
    }

    // 同じモニタリング日のモニタリングシートが既に存在するか確認
    $stmt = $pdo->prepare("
        SELECT id FROM monitoring_records
        WHERE student_id = ? AND monitoring_date = ?
    ");
    $stmt->execute([$studentId, $monitoringDate]);
    if ($stmt->fetch()) {
        error_log("Monitoring already exists for student {$studentId} on {$monitoringDate}");
        return;
    }

    try {
        // モニタリング記録を作成
        $stmt = $pdo->prepare("
            INSERT INTO monitoring_records (
                plan_id, student_id, student_name, monitoring_date, overall_comment, created_at
            ) VALUES (?, ?, ?, ?, '', NOW())
        ");
        $stmt->execute([
            $latestPlan['id'],
            $studentId,
            $student['student_name'],
            $monitoringDate
        ]);
        $monitoringId = $pdo->lastInsertId();

        // 個別支援計画の明細を取得
        $stmt = $pdo->prepare("
            SELECT * FROM individual_support_plan_details
            WHERE plan_id = ?
            ORDER BY row_order
        ");
        $stmt->execute([$latestPlan['id']]);
        $planDetails = $stmt->fetchAll();

        // 各明細に対してモニタリング明細を作成（評価欄は空白）
        foreach ($planDetails as $detail) {
            $stmt = $pdo->prepare("
                INSERT INTO monitoring_details (
                    monitoring_id, plan_detail_id, achievement_status, monitoring_comment, created_at
                ) VALUES (?, ?, NULL, NULL, NOW())
            ");
            $stmt->execute([
                $monitoringId,
                $detail['id']
            ]);
        }

        error_log("Created monitoring sheet (ID: {$monitoringId}) for student {$studentId} on {$monitoringDate}");

    } catch (Exception $e) {
        error_log("Error creating monitoring: " . $e->getMessage());
    }
}
