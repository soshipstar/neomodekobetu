<?php
/**
 * 未作成タスクカウント用ヘルパー関数
 * pending_tasks.php と renrakucho_activities.php で共通利用
 */

/**
 * 次の個別支援計画書期限が1ヶ月以内かチェック
 */
function isNextPlanDeadlineWithinOneMonth($supportStartDate, $latestPlanDate) {
    if (!$supportStartDate) return false;

    $oneMonthLater = new DateTime();
    $oneMonthLater->modify('+1 month');

    if (!$latestPlanDate) {
        // 計画書がない場合、初回期限は支援開始日の前日
        $firstDeadline = new DateTime($supportStartDate);
        $firstDeadline->modify('-1 day');
        return $firstDeadline <= $oneMonthLater;
    }

    // 次の計画書期限は最新計画書から180日後
    $nextDeadline = new DateTime($latestPlanDate);
    $nextDeadline->modify('+180 days');
    return $nextDeadline <= $oneMonthLater;
}

/**
 * 次のモニタリング期限が1ヶ月以内かチェック
 */
function isNextMonitoringDeadlineWithinOneMonth($supportStartDate, $latestMonitoringDate) {
    if (!$supportStartDate) return false;

    $oneMonthLater = new DateTime();
    $oneMonthLater->modify('+1 month');

    if (!$latestMonitoringDate) {
        // モニタリングがない場合、初回期限は支援開始日から5ヶ月後
        $firstDeadline = new DateTime($supportStartDate);
        $firstDeadline->modify('+5 months');
        $firstDeadline->modify('-1 day');
        return $firstDeadline <= $oneMonthLater;
    }

    // 次のモニタリング期限は最新モニタリングから180日後
    $nextDeadline = new DateTime($latestMonitoringDate);
    $nextDeadline->modify('+180 days');
    return $nextDeadline <= $oneMonthLater;
}

/**
 * 個別支援計画書のタスクカウントを取得
 * @param PDO $pdo データベース接続
 * @param int|null $classroomId 教室ID（nullの場合は全教室）
 * @return array ['total' => int, 'none' => int, 'draft' => int, 'needs_confirm' => int, 'outdated' => int, 'urgent' => int, 'items' => array]
 */
function getPlanTaskCounts($pdo, $classroomId = null) {
    $studentCondition = $classroomId ? "AND u.classroom_id = ?" : "";
    $studentParams = $classroomId ? [$classroomId] : [];

    $result = [
        'total' => 0,
        'none' => 0,
        'draft' => 0,
        'needs_confirm' => 0,
        'outdated' => 0,
        'urgent' => 0,
        'items' => []
    ];

    $sql = "
        SELECT
            s.id,
            s.student_name,
            s.support_start_date,
            isp.id as plan_id,
            isp.created_date,
            isp.is_draft,
            COALESCE(isp.is_hidden, 0) as is_hidden,
            COALESCE(isp.guardian_confirmed, 0) as guardian_confirmed,
            DATEDIFF(CURDATE(), isp.created_date) as days_since_plan,
            (
                SELECT MAX(isp2.id)
                FROM individual_support_plans isp2
                WHERE isp2.student_id = s.id AND isp2.is_draft = 0
            ) as latest_submitted_plan_id
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        LEFT JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        {$studentCondition}
        ORDER BY s.student_name, isp.created_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($studentParams);
    $allPlanData = $stmt->fetchAll();

    // 生徒ごとにグループ化
    $studentPlans = [];
    foreach ($allPlanData as $row) {
        $studentId = $row['id'];
        if (!isset($studentPlans[$studentId])) {
            $studentPlans[$studentId] = [
                'student_name' => $row['student_name'],
                'support_start_date' => $row['support_start_date'],
                'plans' => [],
                'latest_submitted_plan_id' => $row['latest_submitted_plan_id']
            ];
        }
        if ($row['plan_id']) {
            $row['guardian_confirmed'] = (int)$row['guardian_confirmed'];
            $studentPlans[$studentId]['plans'][] = $row;
        }
    }

    // カウント
    foreach ($studentPlans as $studentId => $data) {
        $latestSubmittedId = $data['latest_submitted_plan_id'];
        $supportStartDate = $data['support_start_date'];

        // 最新の提出済み計画書の日付を取得
        $latestSubmittedPlanDate = null;
        foreach ($data['plans'] as $plan) {
            if ($plan['plan_id'] == $latestSubmittedId) {
                $latestSubmittedPlanDate = $plan['created_date'];
                break;
            }
        }

        // 計画書がない場合
        if (empty($data['plans'])) {
            if (isNextPlanDeadlineWithinOneMonth($supportStartDate, null)) {
                $result['total']++;
                $result['none']++;
                $result['items'][] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'plan_id' => null,
                    'latest_plan_date' => null,
                    'days_since_plan' => null,
                    'status_code' => 'none',
                    'has_newer' => false,
                    'is_hidden' => false,
                    'guardian_confirmed' => false
                ];
            }
            continue;
        }

        // 下書きがあるかチェック（非表示を除外）
        $hasDraft = false;
        $draftPlan = null;
        foreach ($data['plans'] as $plan) {
            if ($plan['is_draft'] && !$plan['is_hidden']) {
                $hasDraft = true;
                $draftPlan = $plan;
                break;
            }
        }

        // 下書きがある場合（次の期限が1ヶ月以内の場合のみ）
        if ($hasDraft && $draftPlan) {
            if (isNextPlanDeadlineWithinOneMonth($supportStartDate, $latestSubmittedPlanDate)) {
                $hasNewer = $latestSubmittedId && $draftPlan['plan_id'] != $latestSubmittedId;
                $result['total']++;
                $result['draft']++;
                $result['items'][] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'plan_id' => $draftPlan['plan_id'],
                    'latest_plan_date' => $draftPlan['created_date'],
                    'days_since_plan' => $draftPlan['days_since_plan'],
                    'status_code' => 'draft',
                    'has_newer' => $hasNewer,
                    'is_hidden' => false,
                    'guardian_confirmed' => false
                ];
            }
            continue;
        }

        // 下書きがない場合、提出済みで保護者確認が必要かチェック
        $needsGuardianConfirm = false;
        foreach ($data['plans'] as $plan) {
            if ($plan['is_hidden']) continue;

            // 提出済みで保護者未確認かつ最新の提出済み
            if (!$plan['is_draft'] && !$plan['guardian_confirmed'] && $plan['plan_id'] == $latestSubmittedId) {
                $result['total']++;
                $result['needs_confirm']++;
                $result['items'][] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'plan_id' => $plan['plan_id'],
                    'latest_plan_date' => $plan['created_date'],
                    'days_since_plan' => $plan['days_since_plan'],
                    'status_code' => 'needs_confirm',
                    'has_newer' => false,
                    'is_hidden' => false,
                    'guardian_confirmed' => false
                ];
                $needsGuardianConfirm = true;
                break;
            }
        }

        // 保護者確認が必要でない場合、期限切れかチェック
        if (!$needsGuardianConfirm) {
            foreach ($data['plans'] as $plan) {
                if ($plan['is_hidden']) continue;

                // 提出済みで150日以上経過（残り1ヶ月以内）かつ最新の提出済み
                if (!$plan['is_draft'] && $plan['days_since_plan'] >= 150 && $plan['plan_id'] == $latestSubmittedId) {
                    $result['total']++;
                    if ($plan['days_since_plan'] >= 180) {
                        $result['outdated']++;
                    } else {
                        $result['urgent']++;
                    }
                    $result['items'][] = [
                        'id' => $studentId,
                        'student_name' => $data['student_name'],
                        'support_start_date' => $supportStartDate,
                        'plan_id' => $plan['plan_id'],
                        'latest_plan_date' => $plan['created_date'],
                        'days_since_plan' => $plan['days_since_plan'],
                        'status_code' => 'outdated',
                        'has_newer' => false,
                        'is_hidden' => false,
                        'guardian_confirmed' => $plan['guardian_confirmed']
                    ];
                    break;
                }
            }
        }
    }

    return $result;
}

/**
 * モニタリングのタスクカウントを取得
 * @param PDO $pdo データベース接続
 * @param int|null $classroomId 教室ID（nullの場合は全教室）
 * @return array ['total' => int, 'none' => int, 'draft' => int, 'needs_confirm' => int, 'outdated' => int, 'urgent' => int, 'items' => array]
 */
function getMonitoringTaskCounts($pdo, $classroomId = null) {
    $studentCondition = $classroomId ? "AND u.classroom_id = ?" : "";
    $studentParams = $classroomId ? [$classroomId] : [];

    $result = [
        'total' => 0,
        'none' => 0,
        'draft' => 0,
        'needs_confirm' => 0,
        'outdated' => 0,
        'urgent' => 0,
        'items' => []
    ];

    $sql = "
        SELECT
            s.id,
            s.student_name,
            s.support_start_date,
            COALESCE(s.hide_initial_monitoring, 0) as hide_initial_monitoring,
            mr.id as monitoring_id,
            mr.plan_id,
            mr.monitoring_date,
            mr.is_draft,
            COALESCE(mr.is_hidden, 0) as is_hidden,
            COALESCE(mr.guardian_confirmed, 0) as guardian_confirmed,
            DATEDIFF(CURDATE(), mr.monitoring_date) as days_since_monitoring,
            (
                SELECT MAX(mr2.id)
                FROM monitoring_records mr2
                WHERE mr2.student_id = s.id AND mr2.is_draft = 0
            ) as latest_submitted_monitoring_id
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        LEFT JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1
        AND EXISTS (SELECT 1 FROM individual_support_plans isp WHERE isp.student_id = s.id)
        {$studentCondition}
        ORDER BY s.student_name, mr.monitoring_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($studentParams);
    $allMonitoringData = $stmt->fetchAll();

    // 生徒ごとにグループ化
    $studentMonitorings = [];
    foreach ($allMonitoringData as $row) {
        $studentId = $row['id'];
        if (!isset($studentMonitorings[$studentId])) {
            $studentMonitorings[$studentId] = [
                'student_name' => $row['student_name'],
                'support_start_date' => $row['support_start_date'],
                'hide_initial_monitoring' => $row['hide_initial_monitoring'],
                'monitorings' => [],
                'latest_submitted_monitoring_id' => $row['latest_submitted_monitoring_id']
            ];
        }
        if ($row['monitoring_id']) {
            $row['guardian_confirmed'] = (int)$row['guardian_confirmed'];
            $studentMonitorings[$studentId]['monitorings'][] = $row;
        }
    }

    // モニタリング期限を計算する関数
    $calcMonitoringDeadline = function($supportStartDate, $latestMonitoringDate) {
        if (!$supportStartDate) return null;

        if (!$latestMonitoringDate) {
            // 初回期限は支援開始日から5ヶ月後の前日
            $deadline = new DateTime($supportStartDate);
            $deadline->modify('+5 months');
            $deadline->modify('-1 day');
            return $deadline->format('Y-m-d');
        }

        // 次の期限は最新モニタリングから180日後
        $deadline = new DateTime($latestMonitoringDate);
        $deadline->modify('+180 days');
        return $deadline->format('Y-m-d');
    };

    // カウント
    foreach ($studentMonitorings as $studentId => $data) {
        $latestSubmittedId = $data['latest_submitted_monitoring_id'];
        $supportStartDate = $data['support_start_date'];

        // 最新の提出済みモニタリングの日付を取得
        $latestSubmittedMonitoringDate = null;
        foreach ($data['monitorings'] as $monitoring) {
            if ($monitoring['monitoring_id'] == $latestSubmittedId) {
                $latestSubmittedMonitoringDate = $monitoring['monitoring_date'];
                break;
            }
        }

        // モニタリングがない場合
        if (empty($data['monitorings'])) {
            // 非表示フラグがセットされている場合は除外
            if (isNextMonitoringDeadlineWithinOneMonth($supportStartDate, null) && !$data['hide_initial_monitoring']) {
                $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, null);
                $result['total']++;
                $result['none']++;
                $result['items'][] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'monitoring_id' => null,
                    'monitoring_deadline' => $monitoringDeadline,
                    'days_since_monitoring' => null,
                    'status_code' => 'none',
                    'has_newer' => false,
                    'is_hidden' => false,
                    'guardian_confirmed' => false
                ];
            }
            continue;
        }

        // 下書きがあるかチェック（非表示を除外）
        $hasDraft = false;
        $draftMonitoring = null;
        foreach ($data['monitorings'] as $monitoring) {
            if ($monitoring['is_draft'] && !$monitoring['is_hidden']) {
                $hasDraft = true;
                $draftMonitoring = $monitoring;
                break;
            }
        }

        // 下書きがある場合（次の期限が1ヶ月以内の場合のみ）
        if ($hasDraft && $draftMonitoring) {
            if (isNextMonitoringDeadlineWithinOneMonth($supportStartDate, $latestSubmittedMonitoringDate)) {
                $hasNewer = $latestSubmittedId && $draftMonitoring['monitoring_id'] != $latestSubmittedId;
                $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, $latestSubmittedMonitoringDate);
                $result['total']++;
                $result['draft']++;
                $result['items'][] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'monitoring_id' => $draftMonitoring['monitoring_id'],
                    'plan_id' => $draftMonitoring['plan_id'],
                    'monitoring_deadline' => $monitoringDeadline,
                    'days_since_monitoring' => $draftMonitoring['days_since_monitoring'],
                    'status_code' => 'draft',
                    'has_newer' => $hasNewer,
                    'is_hidden' => false,
                    'guardian_confirmed' => false
                ];
            }
            continue;
        }

        // 下書きがない場合、提出済みで保護者確認が必要かチェック
        $needsGuardianConfirm = false;
        foreach ($data['monitorings'] as $monitoring) {
            if ($monitoring['is_hidden']) continue;

            // 提出済みで保護者未確認かつ最新の提出済み
            if (!$monitoring['is_draft'] && !$monitoring['guardian_confirmed'] && $monitoring['monitoring_id'] == $latestSubmittedId) {
                $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, $monitoring['monitoring_date']);
                $result['total']++;
                $result['needs_confirm']++;
                $result['items'][] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'monitoring_id' => $monitoring['monitoring_id'],
                    'plan_id' => $monitoring['plan_id'],
                    'monitoring_deadline' => $monitoringDeadline,
                    'days_since_monitoring' => $monitoring['days_since_monitoring'],
                    'status_code' => 'needs_confirm',
                    'has_newer' => false,
                    'is_hidden' => false,
                    'guardian_confirmed' => false
                ];
                $needsGuardianConfirm = true;
                break;
            }
        }

        // 保護者確認が必要でない場合、期限切れかチェック
        if (!$needsGuardianConfirm) {
            foreach ($data['monitorings'] as $monitoring) {
                if ($monitoring['is_hidden']) continue;

                // 提出済みで150日以上経過（残り1ヶ月以内）
                if (!$monitoring['is_draft'] && $monitoring['days_since_monitoring'] >= 150 && $monitoring['monitoring_id'] == $latestSubmittedId) {
                    $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, $monitoring['monitoring_date']);
                    $result['total']++;
                    if ($monitoring['days_since_monitoring'] >= 180) {
                        $result['outdated']++;
                    } else {
                        $result['urgent']++;
                    }
                    $result['items'][] = [
                        'id' => $studentId,
                        'student_name' => $data['student_name'],
                        'support_start_date' => $supportStartDate,
                        'monitoring_id' => $monitoring['monitoring_id'],
                        'plan_id' => $monitoring['plan_id'],
                        'monitoring_deadline' => $monitoringDeadline,
                        'days_since_monitoring' => $monitoring['days_since_monitoring'],
                        'status_code' => 'outdated',
                        'has_newer' => false,
                        'is_hidden' => false,
                        'guardian_confirmed' => $monitoring['guardian_confirmed']
                    ];
                    break;
                }
            }
        }
    }

    return $result;
}

/**
 * スタッフかけはしのタスクカウントを取得
 * @param PDO $pdo データベース接続
 * @param int|null $classroomId 教室ID（nullの場合は全教室）
 * @return array ['total' => int, 'none' => int, 'draft' => int, 'needs_confirm' => int, 'overdue' => int, 'urgent' => int, 'warning' => int, 'items' => array]
 */
function getStaffKakehashiTaskCounts($pdo, $classroomId = null) {
    $today = date('Y-m-d');

    $result = [
        'total' => 0,
        'none' => 0,
        'draft' => 0,
        'needs_confirm' => 0,
        'overdue' => 0,
        'urgent' => 0,
        'warning' => 0,
        'items' => []
    ];

    $staffSql = "
        SELECT
            s.id as student_id,
            s.student_name,
            kp.id as period_id,
            kp.period_name,
            kp.submission_deadline,
            kp.start_date,
            kp.end_date,
            DATEDIFF(kp.submission_deadline, ?) as days_left,
            ks.id as kakehashi_id,
            ks.is_submitted,
            COALESCE(ks.is_hidden, 0) as is_hidden,
            COALESCE(ks.guardian_confirmed, 0) as guardian_confirmed
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
        LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
        WHERE s.is_active = 1
        AND kp.is_active = 1
        AND (
            ks.is_submitted = 0
            OR ks.is_submitted IS NULL
            OR (ks.is_submitted = 1 AND COALESCE(ks.guardian_confirmed, 0) = 0)
        )
        AND COALESCE(ks.is_hidden, 0) = 0
        AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
        AND kp.submission_deadline = (
            SELECT MAX(kp2.submission_deadline)
            FROM kakehashi_periods kp2
            WHERE kp2.student_id = s.id AND kp2.is_active = 1
            AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
        )
        " . ($classroomId ? "AND u.classroom_id = ?" : "") . "
        ORDER BY kp.submission_deadline ASC, s.student_name
    ";

    try {
        $stmt = $pdo->prepare($staffSql);
        $params = $classroomId ? [$today, $classroomId] : [$today];
        $stmt->execute($params);
        $pendingStaffKakehashi = $stmt->fetchAll();

        foreach ($pendingStaffKakehashi as $kakehashi) {
            $isNotCreated = empty($kakehashi['kakehashi_id']);
            $isDraft = !empty($kakehashi['kakehashi_id']) && !$kakehashi['is_submitted'];
            $isNeedsGuardianConfirm = !empty($kakehashi['kakehashi_id']) && $kakehashi['is_submitted'] && !$kakehashi['guardian_confirmed'];

            $result['total']++;

            if ($isNeedsGuardianConfirm) {
                $result['needs_confirm']++;
                $statusCode = 'needs_confirm';
            } elseif ($isDraft) {
                $result['draft']++;
                $statusCode = 'draft';
            } elseif ($kakehashi['days_left'] < 0) {
                $result['overdue']++;
                $statusCode = 'overdue';
            } elseif ($kakehashi['days_left'] <= 7) {
                $result['urgent']++;
                $statusCode = 'urgent';
            } else {
                $result['warning']++;
                $statusCode = 'warning';
            }

            $result['items'][] = array_merge($kakehashi, ['status_code' => $statusCode]);
        }
    } catch (Exception $e) {
        error_log("Staff kakehashi fetch error: " . $e->getMessage());
    }

    return $result;
}
