<?php
/**
 * かけはし自動生成ヘルパー関数
 * ミニマム版用
 *
 * 【重要】日付計算ルール（変更禁止）
 * - 対象期間: 6ヶ月間（start_date 〜 end_date）
 * - 初回の対象期間開始日 = 支援開始日
 * - 初回の提出期限 = 支援開始日の1日前
 * - 2回目以降の対象期間開始日 = 前回の終了日の翌日
 * - 2回目以降の提出期限 = 対象期間開始日の1ヶ月前
 * - 終了日 = 開始日から6ヶ月後の前日
 */

/**
 * かけはし期間から個別支援計画の開始年月を取得
 *
 * @param array $period かけはし期間データ
 * @return string 個別支援計画開始年月 (例: "2024年4月")
 */
function getIndividualSupportPlanStartMonth($period) {
    if (!$period || !isset($period['start_date'])) {
        return '';
    }

    $startDate = new DateTime($period['start_date']);
    return $startDate->format('Y年n月');
}

/**
 * 正しい日付を計算するヘルパー関数
 * このルールは変更禁止！
 *
 * @param DateTime $supportStartDate 支援開始日
 * @param int $periodNumber 期間番号（1から開始）
 * @param DateTime|null $prevEndDate 前回の終了日（2回目以降で必要）
 * @return array ['start_date', 'end_date', 'submission_deadline']
 */
function calculateKakehashiDates($supportStartDate, $periodNumber, $prevEndDate = null) {
    if ($periodNumber === 1) {
        // 初回
        $startDate = clone $supportStartDate;
        $deadline = clone $supportStartDate;
        $deadline->modify('-1 day');
    } else {
        // 2回目以降: 前回終了日の翌日から開始
        $startDate = clone $prevEndDate;
        $startDate->modify('+1 day');
        // 提出期限: 開始日の1ヶ月前
        $deadline = clone $startDate;
        $deadline->modify('-1 month');
    }

    // 終了日: 開始日から6ヶ月後の前日
    $endDate = clone $startDate;
    $endDate->modify('+6 months');
    $endDate->modify('-1 day');

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'submission_deadline' => $deadline
    ];
}

/**
 * 個別支援計画書の期限を計算するヘルパー関数
 * このルールは変更禁止！
 *
 * ルール:
 * - 初回: かけはしの提出期限と同じ（支援開始日の1日前）
 * - 2回目以降: かけはしの提出期限の1ヶ月後
 *
 * @param DateTime $kakehashiDeadline かけはしの提出期限
 * @param int $periodNumber 期間番号（1から開始）
 * @return DateTime 個別支援計画書の提出期限
 */
function calculateSupportPlanDeadline($kakehashiDeadline, $periodNumber) {
    $deadline = clone $kakehashiDeadline;

    if ($periodNumber === 1) {
        // 初回: かけはしの提出期限と同じ
        return $deadline;
    } else {
        // 2回目以降: かけはしの提出期限の1ヶ月後
        $deadline->modify('+1 month');
        return $deadline;
    }
}

/**
 * かけはし期間から期間番号を取得
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param int $periodId かけはし期間ID
 * @return int 期間番号（1から開始）
 */
function getKakehashiPeriodNumber($pdo, $studentId, $periodId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as period_number
        FROM kakehashi_periods
        WHERE student_id = ? AND id < ?
    ");
    $stmt->execute([$studentId, $periodId]);
    $result = $stmt->fetch();
    return (int)$result['period_number'];
}

/**
 * モニタリング表の期限を計算するヘルパー関数
 * このルールは変更禁止！
 *
 * ルール:
 * - モニタリング期限 = 紐づく個別支援計画書の期限の5ヶ月後
 *
 * @param DateTime $supportPlanDeadline 個別支援計画書の提出期限
 * @return DateTime モニタリング表の提出期限
 */
function calculateMonitoringDeadline($supportPlanDeadline) {
    $deadline = clone $supportPlanDeadline;
    $deadline->modify('+5 months');
    return $deadline;
}

/**
 * 生徒のかけはし期間を自動生成（新規生徒用）
 *
 * ルール:
 * - 初回: 支援開始日の1日前を提出期限とする、対象期間開始日は支援開始日
 * - 2回目以降: 前回終了日の翌日から6ヶ月間、提出期限は開始日の1ヶ月前
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $supportStartDate 支援開始日 (YYYY-MM-DD)
 * @return array 生成されたかけはし期間の配列
 */
function generateKakehashiPeriodsForStudent($pdo, $studentId, $supportStartDate) {
    $generatedPeriods = [];

    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT student_name, withdrawal_date FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception("生徒が見つかりません: ID={$studentId}");
    }

    $studentName = $student['student_name'];
    $withdrawalDate = $student['withdrawal_date'] ? new DateTime($student['withdrawal_date']) : null;

    // 既存のかけはし期間を確認
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as period_count
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

    $supportStartDateTime = new DateTime($supportStartDate);
    $prevEndDate = null;
    $currentPeriodNumber = 1;

    while (true) {
        // 正しい日付を計算
        $dates = calculateKakehashiDates($supportStartDateTime, $currentPeriodNumber, $prevEndDate);

        // 提出期限が生成上限より未来の場合は終了
        if ($dates['submission_deadline'] > $generationLimit) {
            error_log("Kakehashi deadline {$dates['submission_deadline']->format('Y-m-d')} is beyond generation limit. Stopping.");
            break;
        }

        // 退所日が設定されている場合、対象期間開始日が退所日以降ならスキップ
        if ($withdrawalDate && $dates['start_date'] >= $withdrawalDate) {
            error_log("Kakehashi start_date {$dates['start_date']->format('Y-m-d')} is after withdrawal date. Stopping.");
            break;
        }

        // 期間名を設定
        $periodName = "{$currentPeriodNumber}回目かけはし（{$studentName}）";

        // 挿入
        $stmt = $pdo->prepare("
            INSERT INTO kakehashi_periods (
                student_id, period_name, start_date, end_date, submission_deadline, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $studentId,
            $periodName,
            $dates['start_date']->format('Y-m-d'),
            $dates['end_date']->format('Y-m-d'),
            $dates['submission_deadline']->format('Y-m-d')
        ]);
        $newPeriodId = $pdo->lastInsertId();

        $generatedPeriods[] = [
            'id' => $newPeriodId,
            'period_name' => $periodName,
            'submission_deadline' => $dates['submission_deadline']->format('Y-m-d'),
            'type' => "{$currentPeriodNumber}回目"
        ];

        // 保護者・スタッフレコードを作成
        createKakehashiRecordsForPeriod($pdo, $newPeriodId, $studentId);

        // モニタリングシートを作成
        createMonitoringForPeriod($pdo, $studentId, $dates['submission_deadline']->format('Y-m-d'));

        // 次の期間のために終了日を保存
        $prevEndDate = $dates['end_date'];
        $currentPeriodNumber++;
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
    // 保護者かけはしレコードを作成（is_hidden = 0 を明示的に設定）
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_guardian (period_id, student_id, is_hidden, created_at)
        VALUES (?, ?, 0, NOW())
        ON DUPLICATE KEY UPDATE period_id = period_id
    ");
    $stmt->execute([$periodId, $studentId]);

    // スタッフかけはしレコードを作成（staff_id は NULL で作成、is_hidden = 0）
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_staff (period_id, student_id, staff_id, is_hidden, created_at)
        VALUES (?, ?, NULL, 0, NOW())
        ON DUPLICATE KEY UPDATE period_id = period_id
    ");
    $stmt->execute([$periodId, $studentId]);
}

/**
 * 次のかけはし期間を自動生成すべきか確認
 * 次のかけはしの提出期限の1ヶ月前になったら生成する
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @return bool 生成すべき場合true
 */
function shouldGenerateNextKakehashi($pdo, $studentId) {
    // 最新のかけはし期間を取得（end_date順）
    $stmt = $pdo->prepare("
        SELECT end_date
        FROM kakehashi_periods
        WHERE student_id = ?
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $latestPeriod = $stmt->fetch();

    if (!$latestPeriod) {
        return false; // かけはし期間が存在しない
    }

    $latestEndDate = new DateTime($latestPeriod['end_date']);
    $oneMonthBeforeEndDate = clone $latestEndDate;
    $oneMonthBeforeEndDate->modify('-1 month');

    $today = new DateTime();

    return $today >= $oneMonthBeforeEndDate;
}

/**
 * 定期的に次のかけはし期間を自動生成（cron等で実行）
 *
 * @param PDO $pdo データベース接続
 * @return array 生成されたかけはし期間の情報
 */
function autoGenerateNextKakehashiPeriods($pdo) {
    $generatedPeriods = [];

    // 1つ以上のかけはしを持つ全生徒を取得
    $stmt = $pdo->query("
        SELECT DISTINCT s.id, s.student_name, s.support_start_date, s.withdrawal_date
        FROM students s
        INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
        WHERE s.is_active = 1
        AND (s.withdrawal_date IS NULL OR s.withdrawal_date > CURDATE())
        GROUP BY s.id
        HAVING COUNT(kp.id) >= 1
    ");
    $students = $stmt->fetchAll();

    foreach ($students as $student) {
        if (shouldGenerateNextKakehashi($pdo, $student['id'])) {
            // 次のかけはし期間を生成
            $newPeriod = generateNextKakehashiPeriod($pdo, $student['id'], $student['student_name']);
            if ($newPeriod !== null) {
                $generatedPeriods[] = $newPeriod;
            }
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
 * @return array|null 生成されたかけはし期間の情報、または既に存在する場合はnull
 */
function generateNextKakehashiPeriod($pdo, $studentId, $studentName) {
    // 生徒の退所日を確認
    $stmt = $pdo->prepare("SELECT withdrawal_date, support_start_date FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    $withdrawalDate = $student['withdrawal_date'] ? new DateTime($student['withdrawal_date']) : null;
    $supportStartDate = $student['support_start_date'] ? new DateTime($student['support_start_date']) : null;

    // 最新のかけはし期間を取得
    $stmt = $pdo->prepare("
        SELECT id, submission_deadline, start_date, end_date
        FROM kakehashi_periods
        WHERE student_id = ?
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $latestPeriod = $stmt->fetch();

    if (!$latestPeriod) {
        throw new Exception("既存のかけはし期間が見つかりません");
    }

    // 期間回数を計算
    $stmt = $pdo->prepare("SELECT COUNT(*) as current_count FROM kakehashi_periods WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $countData = $stmt->fetch();
    $nextPeriodNumber = (int)$countData['current_count'] + 1;

    // 前回の終了日
    $lastEndDate = new DateTime($latestPeriod['end_date']);

    // calculateKakehashiDates を使用して正しい日付を計算
    $dates = calculateKakehashiDates($supportStartDate, $nextPeriodNumber, $lastEndDate);

    $nextStartDate = $dates['start_date'];
    $nextEndDate = $dates['end_date'];
    $nextDeadline = $dates['submission_deadline'];

    // 既にこの対象期間開始日のかけはしが存在するかチェック
    $stmt = $pdo->prepare("
        SELECT id FROM kakehashi_periods
        WHERE student_id = ? AND start_date = ?
    ");
    $stmt->execute([$studentId, $nextStartDate->format('Y-m-d')]);
    if ($stmt->fetch()) {
        error_log("Kakehashi period for student {$studentId} with start_date {$nextStartDate->format('Y-m-d')} already exists. Skipping.");
        return null;
    }

    // 退所日が設定されている場合、対象期間開始日が退所日以降ならスキップ
    if ($withdrawalDate && $nextStartDate >= $withdrawalDate) {
        error_log("Next kakehashi start_date is after withdrawal date for student {$studentId}. Skipping generation.");
        return null;
    }

    $periodName = "{$nextPeriodNumber}回目かけはし（{$studentName}）";

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
