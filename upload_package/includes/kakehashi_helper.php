<?php
/**
 * かけはし期間自動生成ヘルパー
 */

/**
 * 生徒のかけはし期間を自動生成する
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $initialDate 初回かけはし作成日（提出期限として扱う）
 * @return array 生成された期間のリスト
 */
function generateKakehashiPeriods($pdo, $studentId, $initialDate) {
    $generatedPeriods = [];

    // 初回作成日は提出期限として扱う
    $submissionDeadline = new DateTime($initialDate);
    $today = new DateTime();

    $periodNumber = 1;

    while ($submissionDeadline <= $today) {
        // かけはし対象期間の開始日 = 提出期限の翌日
        $startDate = clone $submissionDeadline;
        $startDate->modify('+1 day');

        // 期間の終了日（開始日から6か月後-1日）
        $endDate = clone $startDate;
        $endDate->modify('+6 months -1 day');

        // 期間名を生成
        $periodName = $startDate->format('Y') . '年度第' . (($periodNumber - 1) % 2 + 1) . '期';

        // 既に存在するかチェック
        $stmt = $pdo->prepare("
            SELECT id FROM kakehashi_periods
            WHERE student_id = ? AND period_number = ?
        ");
        $stmt->execute([$studentId, $periodNumber]);

        if (!$stmt->fetch()) {
            // 存在しない場合は作成
            $stmt = $pdo->prepare("
                INSERT INTO kakehashi_periods (
                    student_id,
                    period_number,
                    period_name,
                    start_date,
                    end_date,
                    submission_deadline,
                    is_active,
                    is_auto_generated
                ) VALUES (?, ?, ?, ?, ?, ?, 1, 1)
            ");

            $stmt->execute([
                $studentId,
                $periodNumber,
                $periodName,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                $submissionDeadline->format('Y-m-d')
            ]);

            $generatedPeriods[] = [
                'period_number' => $periodNumber,
                'period_name' => $periodName,
                'submission_deadline' => $submissionDeadline->format('Y-m-d'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ];
        }

        // 次の期間へ（6か月後の提出期限）
        $submissionDeadline->modify('+6 months');
        $periodNumber++;
    }

    return $generatedPeriods;
}

/**
 * 生徒の現在有効なかけはし期間を取得
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @return array 現在有効な期間のリスト
 */
function getCurrentKakehashiPeriods($pdo, $studentId) {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT *
        FROM kakehashi_periods
        WHERE student_id = ?
        AND start_date <= ?
        AND end_date >= ?
        AND is_active = 1
        ORDER BY period_number DESC
    ");

    $stmt->execute([$studentId, $today, $today]);
    return $stmt->fetchAll();
}

/**
 * 保護者が入力可能なかけはし期間を取得
 * （提出期限内のもの）
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @return array 入力可能な期間のリスト
 */
function getAvailableKakehashiPeriodsForGuardian($pdo, $studentId) {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT *
        FROM kakehashi_periods
        WHERE student_id = ?
        AND submission_deadline >= ?
        AND is_active = 1
        ORDER BY period_number DESC
    ");

    $stmt->execute([$studentId, $today]);
    return $stmt->fetchAll();
}

/**
 * 生徒のかけはし期間を再生成する
 * （初回作成日が変更された場合）
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @param string $newInitialDate 新しい初回作成日
 */
function regenerateKakehashiPeriods($pdo, $studentId, $newInitialDate) {
    // 既存の自動生成期間を削除
    $stmt = $pdo->prepare("
        DELETE FROM kakehashi_periods
        WHERE student_id = ? AND is_auto_generated = 1
    ");
    $stmt->execute([$studentId]);

    // 新しい期間を生成
    return generateKakehashiPeriods($pdo, $studentId, $newInitialDate);
}

/**
 * 次回のかけはし期間開始日を取得
 *
 * @param PDO $pdo データベース接続
 * @param int $studentId 生徒ID
 * @return string|null 次回開始日
 */
function getNextKakehashiPeriodDate($pdo, $studentId) {
    $stmt = $pdo->prepare("
        SELECT MAX(end_date) as last_end_date
        FROM kakehashi_periods
        WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $result = $stmt->fetch();

    if ($result && $result['last_end_date']) {
        $lastEndDate = new DateTime($result['last_end_date']);
        $lastEndDate->modify('+1 day');
        return $lastEndDate->format('Y-m-d');
    }

    return null;
}
