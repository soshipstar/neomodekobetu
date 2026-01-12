<?php
/**
 * かけはし自動生成ヘルパー関数
 *
 * 【重要】日付計算ルール（変更禁止）
 * - 対象期間: 6ヶ月間（start_date ～ end_date）
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

    // support_plan_start_type カラムの存在チェック
    $hasSupportPlanStartType = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'support_plan_start_type'");
        $hasSupportPlanStartType = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasSupportPlanStartType = false;
    }

    // 生徒情報を取得
    $selectCols = $hasSupportPlanStartType
        ? "student_name, withdrawal_date, support_plan_start_type"
        : "student_name, withdrawal_date";
    $stmt = $pdo->prepare("SELECT {$selectCols} FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception("生徒が見つかりません: ID={$studentId}");
    }

    $studentName = $student['student_name'];
    $withdrawalDate = $student['withdrawal_date'] ? new DateTime($student['withdrawal_date']) : null;
    $supportPlanStartType = $hasSupportPlanStartType ? ($student['support_plan_start_type'] ?? 'current') : 'current';

    // support_plan_start_type が 'next' の場合は次回の期間から開始
    // 1回目のかけはしは作成せず、2回目以降から作成する
    // ただし、提出期限が来たら通常通り作成する（autoGenerateNextKakehashiPeriodsで処理）
    if ($supportPlanStartType === 'next') {
        error_log("Student {$studentId} has support_plan_start_type='next'. Skipping initial kakehashi generation. Will generate when next period is due.");
        return $generatedPeriods;
    }

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
 * 次の提出期限 = 現在の対象期間終了日の翌日（次の開始日）の1ヶ月前
 * つまり、現在の対象期間終了日の2ヶ月前になったら次を生成
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

    // 次の提出期限 = 現在の終了日の翌日（次の開始日）の1ヶ月前 = 現在の終了日
    // その提出期限の1ヶ月前に生成 = 現在の終了日の1ヶ月前
    $latestEndDate = new DateTime($latestPeriod['end_date']);
    $oneMonthBeforeEndDate = clone $latestEndDate;
    $oneMonthBeforeEndDate->modify('-1 month');

    $today = new DateTime();

    return $today >= $oneMonthBeforeEndDate;
}

/**
 * 定期的に次のかけはし期間を自動生成（cron等で実行）
 * 最新の期限の1ヶ月前になったら次のかけはし期間を生成する
 *
 * @param PDO $pdo データベース接続
 * @return array 生成されたかけはし期間の情報
 */
function autoGenerateNextKakehashiPeriods($pdo) {
    $generatedPeriods = [];

    // support_plan_start_type カラムの存在チェック
    $hasSupportPlanStartType = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'support_plan_start_type'");
        $hasSupportPlanStartType = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasSupportPlanStartType = false;
    }

    // 1つ以上のかけはしを持つ全生徒を取得（退所していない、または退所日が未来の生徒のみ）
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

    // support_plan_start_type='next' でまだかけはしがない生徒も処理（カラムが存在する場合のみ）
    // 次回の期間（2回目相当）の提出期限が近づいたら、1回目のかけはしから生成
    if ($hasSupportPlanStartType) {
        $stmt = $pdo->query("
            SELECT s.id, s.student_name, s.support_start_date, s.withdrawal_date
            FROM students s
            LEFT JOIN kakehashi_periods kp ON s.id = kp.student_id
            WHERE s.is_active = 1
            AND s.support_plan_start_type = 'next'
            AND (s.withdrawal_date IS NULL OR s.withdrawal_date > CURDATE())
            AND s.support_start_date IS NOT NULL
            GROUP BY s.id
            HAVING COUNT(kp.id) = 0
        ");
        $studentsWithNext = $stmt->fetchAll();

        foreach ($studentsWithNext as $student) {
            if (shouldGenerateFirstKakehashiForNextType($student['support_start_date'])) {
                // 1回目のかけはしから生成を開始
                $newPeriods = generateKakehashiPeriodsForStudentForced($pdo, $student['id'], $student['support_start_date']);
                $generatedPeriods = array_merge($generatedPeriods, $newPeriods);
            }
        }
    }

    return $generatedPeriods;
}

/**
 * support_plan_start_type='next' の生徒で、初回かけはし生成のタイミングかチェック
 * 次回の期間（初回終了後の期間）の提出期限の1ヶ月前になったら生成
 *
 * @param string $supportStartDate 支援開始日
 * @return bool 生成すべき場合true
 */
function shouldGenerateFirstKakehashiForNextType($supportStartDate) {
    $startDate = new DateTime($supportStartDate);

    // 初回の仮想的な終了日を計算（支援開始日から6ヶ月後の前日）
    $firstEndDate = clone $startDate;
    $firstEndDate->modify('+6 months');
    $firstEndDate->modify('-1 day');

    // 次回（2回目）の提出期限 = 初回終了日の翌日（2回目開始日）の1ヶ月前 = 初回終了日
    // その1ヶ月前に生成 = 初回終了日の1ヶ月前
    $generationTriggerDate = clone $firstEndDate;
    $generationTriggerDate->modify('-1 month');

    $today = new DateTime();

    return $today >= $generationTriggerDate;
}

/**
 * support_plan_start_type を無視してかけはし期間を強制生成
 * support_plan_start_type='next' の生徒で、提出期限が来た場合に使用
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $supportStartDate 支援開始日
 * @return array 生成されたかけはし期間の配列
 */
function generateKakehashiPeriodsForStudentForced($pdo, $studentId, $supportStartDate) {
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
        error_log("Student {$studentId} already has kakehashi periods. Skipping forced generation.");
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

    error_log("Forced generation completed for student {$studentId}. Generated " . count($generatedPeriods) . " periods.");
    return $generatedPeriods;
}

/**
 * 次のかけはし期間を生成（6ヶ月サイクル）
 *
 * 【重要】日付計算ルール（変更禁止）
 * - 対象期間 = 前回の終了日の翌日から6ヶ月間
 * - 提出期限 = 対象期間開始日の1ヶ月前（個別支援計画の1ヶ月前に提出）
 * - 個別支援計画 = 対象期間の開始月
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $studentName 生徒名
 * @return array|null 生成されたかけはし期間の情報、または既に存在する場合はnull
 */
function generateNextKakehashiPeriod($pdo, $studentId, $studentName) {
    // support_plan_start_type カラムの存在チェック
    $hasSupportPlanStartType = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'support_plan_start_type'");
        $hasSupportPlanStartType = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasSupportPlanStartType = false;
    }

    // 生徒の退所日を確認
    $selectCols = $hasSupportPlanStartType
        ? "withdrawal_date, support_start_date, support_plan_start_type"
        : "withdrawal_date, support_start_date";
    $stmt = $pdo->prepare("SELECT {$selectCols} FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    $withdrawalDate = $student['withdrawal_date'] ? new DateTime($student['withdrawal_date']) : null;
    $supportStartDate = $student['support_start_date'] ? new DateTime($student['support_start_date']) : null;
    $supportPlanStartType = $hasSupportPlanStartType ? ($student['support_plan_start_type'] ?? 'current') : 'current';

    // 最新のかけはし期間を取得（end_date順）
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
    // 注: 2回目以降なので、$supportStartDateは使用しないが、関数の互換性のため渡す
    $dates = calculateKakehashiDates($supportStartDate, $nextPeriodNumber, $lastEndDate);

    $nextStartDate = $dates['start_date'];
    $nextEndDate = $dates['end_date'];
    $nextDeadline = $dates['submission_deadline'];

    // 既にこの対象期間開始日のかけはしが存在するかチェック（重複防止）
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
        error_log("Next kakehashi start_date {$nextStartDate->format('Y-m-d')} is after withdrawal date {$withdrawalDate->format('Y-m-d')} for student {$studentId}. Skipping generation.");
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
