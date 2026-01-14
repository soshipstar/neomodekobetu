<?php
/**
 * 通知ヘルパー関数
 *
 * スタッフ・保護者向けの通知データを取得する共通関数
 */

/**
 * スタッフ向け通知データを取得
 *
 * @param PDO $pdo データベース接続
 * @param int $staffId スタッフID
 * @param int|null $classroomId 教室ID
 * @return array 通知データ
 */
function getStaffNotifications(PDO $pdo, int $staffId, ?int $classroomId): array
{
    $notifications = [];
    $totalCount = 0;

    // 1. 未読チャットメッセージ（保護者からの）
    $unreadChatMessages = [];
    try {
        // chat_message_staff_reads テーブルの存在確認
        $hasStaffReadsTable = false;
        try {
            $pdo->query("SELECT 1 FROM chat_message_staff_reads LIMIT 1");
            $hasStaffReadsTable = true;
        } catch (PDOException $e) {
            $hasStaffReadsTable = false;
        }

        if ($classroomId) {
            if ($hasStaffReadsTable) {
                $stmt = $pdo->prepare("
                    SELECT
                        cr.id as room_id,
                        s.student_name,
                        u.full_name as guardian_name,
                        COUNT(cm.id) as unread_count,
                        MAX(cm.created_at) as last_message_at
                    FROM chat_rooms cr
                    INNER JOIN students s ON cr.student_id = s.id
                    INNER JOIN users u ON cr.guardian_id = u.id
                    INNER JOIN chat_messages cm ON cr.id = cm.room_id
                    WHERE s.classroom_id = ?
                    AND cm.sender_type = 'guardian'
                    AND NOT EXISTS (
                        SELECT 1 FROM chat_message_staff_reads csr
                        WHERE csr.message_id = cm.id AND csr.staff_id = ?
                    )
                    GROUP BY cr.id, s.student_name, u.full_name
                    ORDER BY last_message_at DESC
                ");
                $stmt->execute([$classroomId, $staffId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT
                        cr.id as room_id,
                        s.student_name,
                        u.full_name as guardian_name,
                        COUNT(cm.id) as unread_count,
                        MAX(cm.created_at) as last_message_at
                    FROM chat_rooms cr
                    INNER JOIN students s ON cr.student_id = s.id
                    INNER JOIN users u ON cr.guardian_id = u.id
                    INNER JOIN chat_messages cm ON cr.id = cm.room_id
                    WHERE s.classroom_id = ?
                    AND cm.sender_type = 'guardian'
                    AND cm.is_read = 0
                    GROUP BY cr.id, s.student_name, u.full_name
                    ORDER BY last_message_at DESC
                ");
                $stmt->execute([$classroomId]);
            }
            $unreadChatMessages = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching staff unread chat messages: " . $e->getMessage());
    }

    $unreadChatCount = array_sum(array_column($unreadChatMessages, 'unread_count'));
    if ($unreadChatCount > 0) {
        $notifications['chat'] = [
            'type' => 'chat',
            'icon' => 'chat',
            'color' => 'blue',
            'title' => '未読メッセージ',
            'count' => $unreadChatCount,
            'items' => $unreadChatMessages,
            'url' => '/staff/chat.php'
        ];
        $totalCount += $unreadChatCount;
    }

    // 2. 振替希望（承認待ち）
    $pendingMakeupRequests = [];
    try {
        if ($classroomId) {
            $stmt = $pdo->prepare("
                SELECT
                    an.id,
                    an.student_id,
                    an.absence_date,
                    an.makeup_request_date,
                    an.reason,
                    s.student_name
                FROM absence_notifications an
                INNER JOIN students s ON an.student_id = s.id
                INNER JOIN users u ON s.guardian_id = u.id
                WHERE an.makeup_status = 'pending'
                AND an.makeup_request_date IS NOT NULL
                AND u.classroom_id = ?
                ORDER BY an.makeup_request_date ASC
            ");
            $stmt->execute([$classroomId]);
            $pendingMakeupRequests = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching pending makeup requests: " . $e->getMessage());
    }

    if (count($pendingMakeupRequests) > 0) {
        $notifications['makeup'] = [
            'type' => 'makeup',
            'icon' => 'sync',
            'color' => 'orange',
            'title' => '承認待ちの振替希望',
            'count' => count($pendingMakeupRequests),
            'items' => $pendingMakeupRequests,
            'url' => '/staff/makeup_requests.php?status=pending'
        ];
        $totalCount += count($pendingMakeupRequests);
    }

    // 3. 面談予約対応（保護者からの対案待ち）
    $pendingMeetingResponses = [];
    try {
        if ($classroomId) {
            $stmt = $pdo->prepare("
                SELECT
                    mr.id,
                    mr.purpose,
                    mr.status,
                    s.student_name,
                    u.full_name as guardian_name
                FROM meeting_requests mr
                INNER JOIN students s ON mr.student_id = s.id
                LEFT JOIN users u ON mr.guardian_id = u.id
                WHERE mr.classroom_id = ?
                AND mr.status = 'guardian_counter'
                ORDER BY mr.updated_at DESC
            ");
            $stmt->execute([$classroomId]);
            $pendingMeetingResponses = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // meeting_requests テーブルが存在しない場合は無視
    }

    if (count($pendingMeetingResponses) > 0) {
        $notifications['meeting'] = [
            'type' => 'meeting',
            'icon' => 'calendar_month',
            'color' => 'purple',
            'title' => '対応待ちの面談予約',
            'count' => count($pendingMeetingResponses),
            'items' => $pendingMeetingResponses,
            'url' => '/staff/meeting_response.php'
        ];
        $totalCount += count($pendingMeetingResponses);
    }

    return [
        'notifications' => $notifications,
        'totalCount' => $totalCount
    ];
}

/**
 * 保護者向け通知データを取得
 *
 * @param PDO $pdo データベース接続
 * @param int $guardianId 保護者ID
 * @return array 通知データ
 */
function getGuardianNotifications(PDO $pdo, int $guardianId): array
{
    $notifications = [];
    $totalCount = 0;

    // 保護者に紐づく生徒IDを取得
    $studentIds = [];
    try {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE guardian_id = ? AND is_active = 1 AND status = 'active'");
        $stmt->execute([$guardianId]);
        $studentIds = array_column($stmt->fetchAll(), 'id');
    } catch (Exception $e) {
        error_log("Error fetching student IDs: " . $e->getMessage());
    }

    // 1. 未読チャットメッセージ（スタッフからの）
    $unreadChatMessages = [];
    try {
        $stmt = $pdo->prepare("
            SELECT
                cr.id as room_id,
                s.student_name,
                COUNT(cm.id) as unread_count,
                MAX(cm.created_at) as last_message_at
            FROM chat_rooms cr
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN chat_messages cm ON cr.id = cm.room_id
            WHERE cr.guardian_id = ?
            AND cm.sender_type = 'staff'
            AND cm.is_read = 0
            GROUP BY cr.id, s.student_name
            ORDER BY last_message_at DESC
        ");
        $stmt->execute([$guardianId]);
        $unreadChatMessages = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching guardian unread chat messages: " . $e->getMessage());
    }

    $unreadChatCount = array_sum(array_column($unreadChatMessages, 'unread_count'));
    if ($unreadChatCount > 0) {
        $notifications['chat'] = [
            'type' => 'chat',
            'icon' => 'chat',
            'color' => 'blue',
            'title' => '未読メッセージ',
            'count' => $unreadChatCount,
            'items' => $unreadChatMessages,
            'url' => '/guardian/chat.php'
        ];
        $totalCount += $unreadChatCount;
    }

    // 2. 回答待ちの面談予約
    $pendingMeetingRequests = [];
    try {
        $stmt = $pdo->prepare("
            SELECT
                mr.id,
                mr.purpose,
                mr.status,
                s.student_name
            FROM meeting_requests mr
            INNER JOIN students s ON mr.student_id = s.id
            WHERE mr.guardian_id = ?
            AND mr.status IN ('pending', 'staff_counter')
            AND mr.is_completed = 0
            ORDER BY mr.updated_at DESC
        ");
        $stmt->execute([$guardianId]);
        $pendingMeetingRequests = $stmt->fetchAll();
    } catch (PDOException $e) {
        // テーブルが存在しない場合は無視
    }

    if (count($pendingMeetingRequests) > 0) {
        $notifications['meeting'] = [
            'type' => 'meeting',
            'icon' => 'calendar_month',
            'color' => 'purple',
            'title' => '回答待ちの面談予約',
            'count' => count($pendingMeetingRequests),
            'items' => $pendingMeetingRequests,
            'url' => '/guardian/meeting_response.php'
        ];
        $totalCount += count($pendingMeetingRequests);
    }

    // 3. 確認待ちの個別支援計画書
    $pendingSupportPlans = [];
    try {
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = $pdo->prepare("
                SELECT isp.id, isp.student_id, s.student_name, isp.created_date
                FROM individual_support_plans isp
                INNER JOIN students s ON isp.student_id = s.id
                WHERE isp.student_id IN ($placeholders)
                AND isp.guardian_confirmed = 0
                AND isp.is_hidden = 0
                AND isp.is_formal = 0
            ");
            $stmt->execute($studentIds);
            $pendingSupportPlans = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching pending support plans: " . $e->getMessage());
    }

    if (count($pendingSupportPlans) > 0) {
        $notifications['support_plan'] = [
            'type' => 'support_plan',
            'icon' => 'assignment',
            'color' => 'purple',
            'title' => '個別支援計画書確認',
            'count' => count($pendingSupportPlans),
            'items' => $pendingSupportPlans,
            'url' => '/guardian/support_plans.php'
        ];
        $totalCount += count($pendingSupportPlans);
    }

    // 4. 署名待ちの個別支援計画書
    $signaturePendingPlans = [];
    try {
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = $pdo->prepare("
                SELECT isp.id, isp.student_id, s.student_name, isp.created_date
                FROM individual_support_plans isp
                INNER JOIN students s ON isp.student_id = s.id
                WHERE isp.student_id IN ($placeholders)
                AND isp.is_formal = 1
                AND (isp.guardian_signature IS NULL OR isp.guardian_signature = '')
                AND isp.is_hidden = 0
            ");
            $stmt->execute($studentIds);
            $signaturePendingPlans = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching signature pending plans: " . $e->getMessage());
    }

    if (count($signaturePendingPlans) > 0) {
        $notifications['support_plan_sign'] = [
            'type' => 'support_plan_sign',
            'icon' => 'draw',
            'color' => 'green',
            'title' => '計画書署名待ち',
            'count' => count($signaturePendingPlans),
            'items' => $signaturePendingPlans,
            'url' => '/guardian/support_plans.php'
        ];
        $totalCount += count($signaturePendingPlans);
    }

    // 5. 確認待ちのモニタリング表
    $pendingMonitoringRecords = [];
    try {
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = $pdo->prepare("
                SELECT mr.id, mr.student_id, s.student_name, mr.monitoring_date
                FROM monitoring_records mr
                INNER JOIN students s ON mr.student_id = s.id
                WHERE mr.student_id IN ($placeholders)
                AND mr.guardian_confirmed = 0
                AND mr.is_hidden = 0
                AND mr.is_formal = 0
            ");
            $stmt->execute($studentIds);
            $pendingMonitoringRecords = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching pending monitoring records: " . $e->getMessage());
    }

    if (count($pendingMonitoringRecords) > 0) {
        $notifications['monitoring'] = [
            'type' => 'monitoring',
            'icon' => 'monitoring',
            'color' => 'teal',
            'title' => 'モニタリング確認',
            'count' => count($pendingMonitoringRecords),
            'items' => $pendingMonitoringRecords,
            'url' => '/guardian/monitoring.php'
        ];
        $totalCount += count($pendingMonitoringRecords);
    }

    // 6. 未提出かけはし
    $pendingKakehashi = [];
    $today = date('Y-m-d');
    $oneMonthLater = date('Y-m-d', strtotime('+1 month'));
    try {
        if (!empty($studentIds)) {
            foreach ($studentIds as $studentId) {
                $stmt = $pdo->prepare("
                    SELECT
                        kp.id as period_id,
                        kp.period_name,
                        kp.submission_deadline,
                        s.student_name,
                        s.id as student_id,
                        DATEDIFF(kp.submission_deadline, ?) as days_left
                    FROM kakehashi_periods kp
                    INNER JOIN students s ON kp.student_id = s.id
                    LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = ?
                    WHERE kp.student_id = ?
                    AND kp.is_active = 1
                    AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
                    AND (kg.is_hidden = 0 OR kg.is_hidden IS NULL)
                    AND kp.submission_deadline <= ?
                    ORDER BY kp.submission_deadline ASC
                ");
                $stmt->execute([$today, $studentId, $studentId, $oneMonthLater]);
                $periods = $stmt->fetchAll();
                $pendingKakehashi = array_merge($pendingKakehashi, $periods);
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching pending kakehashi: " . $e->getMessage());
    }

    if (count($pendingKakehashi) > 0) {
        $notifications['kakehashi'] = [
            'type' => 'kakehashi',
            'icon' => 'handshake',
            'color' => 'orange',
            'title' => '未提出のかけはし',
            'count' => count($pendingKakehashi),
            'items' => $pendingKakehashi,
            'url' => '/guardian/kakehashi.php'
        ];
        $totalCount += count($pendingKakehashi);
    }

    // 7. 未提出の提出物
    $pendingSubmissions = [];
    try {
        $stmt = $pdo->prepare("
            SELECT
                sr.id,
                sr.title,
                sr.due_date,
                s.student_name,
                DATEDIFF(sr.due_date, ?) as days_left
            FROM submission_requests sr
            INNER JOIN students s ON sr.student_id = s.id
            WHERE sr.guardian_id = ?
            AND sr.is_completed = 0
            ORDER BY sr.due_date ASC
        ");
        $stmt->execute([$today, $guardianId]);
        $pendingSubmissions = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching pending submissions: " . $e->getMessage());
    }

    if (count($pendingSubmissions) > 0) {
        $notifications['submission'] = [
            'type' => 'submission',
            'icon' => 'upload_file',
            'color' => 'red',
            'title' => '未提出の提出物',
            'count' => count($pendingSubmissions),
            'items' => $pendingSubmissions,
            'url' => '/guardian/submissions.php'
        ];
        $totalCount += count($pendingSubmissions);
    }

    return [
        'notifications' => $notifications,
        'totalCount' => $totalCount
    ];
}
