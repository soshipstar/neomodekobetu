<?php
/**
 * 活動管理ページ（カレンダー表示対応）
 */

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/student_helper.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
require_once __DIR__ . '/../../includes/pending_tasks_helper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();

// かけはし期間の自動生成（期限1ヶ月前に次の期間を生成）
try {
    autoGenerateNextKakehashiPeriods($pdo);
} catch (Exception $e) {
    error_log("Auto-generate kakehashi periods error: " . $e->getMessage());
}
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$currentUser['id']]);
$classroom = $stmt->fetch();

// 未読チャットメッセージを取得（スタッフ用：保護者からの未読メッセージ）
// スタッフごとの既読管理テーブル（chat_message_staff_reads）を使用
$unreadChatMessages = [];
$staffId = $currentUser['id'];

// chat_message_staff_reads テーブルの存在確認（グローバルスコープで定義）
$hasStaffReadsTable = false;
try {
    $pdo->query("SELECT 1 FROM chat_message_staff_reads LIMIT 1");
    $hasStaffReadsTable = true;
} catch (PDOException $e) {
    $hasStaffReadsTable = false;
}

try {
    if ($classroomId) {
        if ($hasStaffReadsTable) {
            // 新方式：スタッフごとの既読管理
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
            // 旧方式：is_readカラムで判定（後方互換）
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
    error_log("Error fetching unread chat messages: " . $e->getMessage());
}
$totalUnreadMessages = array_sum(array_column($unreadChatMessages, 'unread_count'));

// 選択された年月を取得（デフォルトは今月）
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 月の初日と最終日
$firstDay = strtotime("$year-$month-1");
$lastDay = strtotime(date('Y-m-t', $firstDay));

// 前月・次月の計算
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

// この月の活動がある日付を取得（同じ教室のスタッフの活動を全て表示）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(dr.record_date) as date
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE u.classroom_id = ?
        AND YEAR(dr.record_date) = ?
        AND MONTH(dr.record_date) = ?
        ORDER BY date
    ");
    $stmt->execute([$classroomId, $year, $month]);
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(record_date) as date
        FROM daily_records
        WHERE YEAR(record_date) = ?
        AND MONTH(record_date) = ?
        ORDER BY date
    ");
    $stmt->execute([$year, $month]);
}
$activeDates = array_column($stmt->fetchAll(), 'date');

// この月の休日を取得（自分の教室のみ）
$stmt = $pdo->prepare("
    SELECT holiday_date, holiday_name, holiday_type
    FROM holidays
    WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? AND classroom_id = ?
");
$stmt->execute([$year, $month, $classroomId]);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
$holidayDates = [];
foreach ($holidays as $holiday) {
    $holidayDates[$holiday['holiday_date']] = [
        'name' => $holiday['holiday_name'],
        'type' => $holiday['holiday_type']
    ];
}

// この月のイベントを取得（自分の教室のみ）
$stmt = $pdo->prepare("
    SELECT id, event_date, event_name, event_description, event_color, staff_comment, guardian_message, target_audience
    FROM events
    WHERE YEAR(event_date) = ? AND MONTH(event_date) = ? AND classroom_id = ?
");
$stmt->execute([$year, $month, $classroomId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
$eventDates = [];
foreach ($events as $event) {
    if (!isset($eventDates[$event['event_date']])) {
        $eventDates[$event['event_date']] = [];
    }
    $eventDates[$event['event_date']][] = [
        'id' => $event['id'],
        'name' => $event['event_name'],
        'description' => $event['event_description'],
        'color' => $event['event_color'],
        'staff_comment' => $event['staff_comment'],
        'guardian_message' => $event['guardian_message'],
        'target_audience' => $event['target_audience']
    ];
}

// この月の学校休業日活動を取得
$stmt = $pdo->prepare("
    SELECT activity_date
    FROM school_holiday_activities
    WHERE YEAR(activity_date) = ? AND MONTH(activity_date) = ? AND classroom_id = ?
");
$stmt->execute([$year, $month, $classroomId]);
$schoolHolidayDates = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schoolHolidayDates[$row['activity_date']] = true;
}

// この月の確定済み面談を取得
$calendarMeetings = [];
try {
    $firstDayStr = date('Y-m-01', strtotime("$year-$month-01"));
    $lastDayStr = date('Y-m-t', strtotime("$year-$month-01"));

    $stmt = $pdo->prepare("
        SELECT mr.*, s.student_name, u.full_name as guardian_name
        FROM meeting_requests mr
        INNER JOIN students s ON mr.student_id = s.id
        LEFT JOIN users u ON mr.guardian_id = u.id
        WHERE mr.classroom_id = ?
        AND mr.status = 'confirmed'
        AND DATE(mr.confirmed_date) BETWEEN ? AND ?
        ORDER BY mr.confirmed_date ASC
    ");
    $stmt->execute([$classroomId, $firstDayStr, $lastDayStr]);
    $meetings = $stmt->fetchAll();

    foreach ($meetings as $meeting) {
        $date = date('Y-m-d', strtotime($meeting['confirmed_date']));
        if (!isset($calendarMeetings[$date])) {
            $calendarMeetings[$date] = [];
        }
        $calendarMeetings[$date][] = [
            'id' => $meeting['id'],
            'student_name' => $meeting['student_name'],
            'guardian_name' => $meeting['guardian_name'],
            'purpose' => $meeting['purpose'],
            'time' => date('H:i', strtotime($meeting['confirmed_date']))
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching calendar meetings: " . $e->getMessage());
}

// 選択された日付の活動一覧を取得（同じ教室のスタッフの活動を全て表示）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.staff_id,
               u.full_name as staff_name,
               sp.activity_name as support_plan_name,
               COUNT(DISTINCT sr.id) as participant_count,
               (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = dr.id AND is_sent = 0) as unsent_count,
               (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = dr.id AND is_sent = 1) as sent_count
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        LEFT JOIN support_plans sp ON dr.support_plan_id = sp.id
        LEFT JOIN student_records sr ON dr.id = sr.daily_record_id
        WHERE dr.record_date = ? AND u.classroom_id = ?
        GROUP BY dr.id, dr.activity_name, dr.common_activity, dr.staff_id, u.full_name, sp.activity_name
        ORDER BY dr.created_at
    ");
    $stmt->execute([$selectedDate, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.staff_id,
               u.full_name as staff_name,
               sp.activity_name as support_plan_name,
               COUNT(DISTINCT sr.id) as participant_count,
               (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = dr.id AND is_sent = 0) as unsent_count,
               (SELECT COUNT(*) FROM integrated_notes WHERE daily_record_id = dr.id AND is_sent = 1) as sent_count
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        LEFT JOIN support_plans sp ON dr.support_plan_id = sp.id
        LEFT JOIN student_records sr ON dr.id = sr.daily_record_id
        WHERE dr.record_date = ?
        GROUP BY dr.id, dr.activity_name, dr.common_activity, dr.staff_id, u.full_name, sp.activity_name
        ORDER BY dr.created_at
    ");
    $stmt->execute([$selectedDate]);
}
$activities = $stmt->fetchAll();

// 通知情報を取得（過去3日以内、教室に関係なく全スタッフに表示）
$threeDaysAgo = date('Y-m-d H:i:s', strtotime('-3 days'));

// 0. 振替希望（承認待ち）
$pendingMakeupRequests = [];
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT
            an.id,
            an.student_id,
            an.absence_date,
            an.makeup_request_date,
            an.reason,
            s.student_name,
            cm.created_at as requested_at
        FROM absence_notifications an
        INNER JOIN students s ON an.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        LEFT JOIN chat_messages cm ON an.message_id = cm.id
        WHERE an.makeup_status = 'pending'
        AND an.makeup_request_date IS NOT NULL
        AND u.classroom_id = ?
        ORDER BY an.makeup_request_date ASC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT
            an.id,
            an.student_id,
            an.absence_date,
            an.makeup_request_date,
            an.reason,
            s.student_name,
            cm.created_at as requested_at
        FROM absence_notifications an
        INNER JOIN students s ON an.student_id = s.id
        LEFT JOIN chat_messages cm ON an.message_id = cm.id
        WHERE an.makeup_status = 'pending'
        AND an.makeup_request_date IS NOT NULL
        ORDER BY an.makeup_request_date ASC
    ");
    $stmt->execute();
}
$pendingMakeupRequests = $stmt->fetchAll();
$pendingMakeupCount = count($pendingMakeupRequests);

// 0.5. 面談予約（保護者からの対案待ち・新規申込）
$pendingMeetingResponses = [];
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                mr.id,
                mr.purpose,
                mr.status,
                mr.staff_id,
                mr.updated_at,
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
    } catch (PDOException $e) {
        // meeting_requests テーブルが存在しない場合は無視
        error_log("Meeting requests query error (table may not exist): " . $e->getMessage());
    }
}
$pendingMeetingResponseCount = count($pendingMeetingResponses);

// 1. 保護者からの新しいメッセージ（未読）- スタッフごとの既読管理対応
if ($classroomId) {
    if ($hasStaffReadsTable) {
        // 新方式：スタッフごとの既読管理
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.created_at, cm.message, s.student_name, u.full_name as guardian_name, cr.id as room_id
            FROM chat_messages cm
            INNER JOIN chat_rooms cr ON cm.room_id = cr.id
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE cm.sender_type = 'guardian'
            AND NOT EXISTS (
                SELECT 1 FROM chat_message_staff_reads csr
                WHERE csr.message_id = cm.id AND csr.staff_id = ?
            )
            AND cm.created_at >= ?
            AND s.classroom_id = ?
            ORDER BY cm.created_at DESC
        ");
        $stmt->execute([$staffId, $threeDaysAgo, $classroomId]);
    } else {
        // 旧方式：is_readカラムで判定
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.created_at, cm.message, s.student_name, u.full_name as guardian_name, cr.id as room_id
            FROM chat_messages cm
            INNER JOIN chat_rooms cr ON cm.room_id = cr.id
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE cm.sender_type = 'guardian'
            AND cm.is_read = 0
            AND cm.created_at >= ?
            AND s.classroom_id = ?
            ORDER BY cm.created_at DESC
        ");
        $stmt->execute([$threeDaysAgo, $classroomId]);
    }
} else {
    if ($hasStaffReadsTable) {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.created_at, cm.message, s.student_name, u.full_name as guardian_name, cr.id as room_id
            FROM chat_messages cm
            INNER JOIN chat_rooms cr ON cm.room_id = cr.id
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE cm.sender_type = 'guardian'
            AND NOT EXISTS (
                SELECT 1 FROM chat_message_staff_reads csr
                WHERE csr.message_id = cm.id AND csr.staff_id = ?
            )
            AND cm.created_at >= ?
            ORDER BY cm.created_at DESC
        ");
        $stmt->execute([$staffId, $threeDaysAgo]);
    } else {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.created_at, cm.message, s.student_name, u.full_name as guardian_name, cr.id as room_id
            FROM chat_messages cm
            INNER JOIN chat_rooms cr ON cm.room_id = cr.id
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE cm.sender_type = 'guardian'
            AND cm.is_read = 0
            AND cm.created_at >= ?
            ORDER BY cm.created_at DESC
        ");
        $stmt->execute([$threeDaysAgo]);
    }
}
$newMessages = $stmt->fetchAll();

// 2. 新しいかけはし（スタッフが作成）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT ks.id, ks.created_at, ks.updated_at, s.student_name, u.full_name as staff_name, kp.period_name
        FROM kakehashi_staff ks
        INNER JOIN students s ON ks.student_id = s.id
        INNER JOIN users su ON s.guardian_id = su.id
        INNER JOIN users u ON ks.staff_id = u.id
        INNER JOIN kakehashi_periods kp ON ks.period_id = kp.id
        WHERE ks.created_at >= ?
        AND su.classroom_id = ?
        ORDER BY ks.created_at DESC
    ");
    $stmt->execute([$threeDaysAgo, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT ks.id, ks.created_at, ks.updated_at, s.student_name, u.full_name as staff_name, kp.period_name
        FROM kakehashi_staff ks
        INNER JOIN students s ON ks.student_id = s.id
        INNER JOIN users u ON ks.staff_id = u.id
        INNER JOIN kakehashi_periods kp ON ks.period_id = kp.id
        WHERE ks.created_at >= ?
        ORDER BY ks.created_at DESC
    ");
    $stmt->execute([$threeDaysAgo]);
}
$newKakehashi = $stmt->fetchAll();

// 3. モニタリング表の保護者確認（3日以内に確認されたもの）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT mr.id, mr.guardian_confirmed_at, mr.student_name, mr.monitoring_date
        FROM monitoring_records mr
        INNER JOIN students s ON mr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE mr.guardian_confirmed = 1
        AND mr.guardian_confirmed_at >= ?
        AND u.classroom_id = ?
        ORDER BY mr.guardian_confirmed_at DESC
    ");
    $stmt->execute([$threeDaysAgo, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT mr.id, mr.guardian_confirmed_at, mr.student_name, mr.monitoring_date
        FROM monitoring_records mr
        WHERE mr.guardian_confirmed = 1
        AND mr.guardian_confirmed_at >= ?
        ORDER BY mr.guardian_confirmed_at DESC
    ");
    $stmt->execute([$threeDaysAgo]);
}
$confirmedMonitoring = $stmt->fetchAll();

// 4. 個別支援計画の保護者確認（3日以内に確認されたもの）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT isp.id, isp.guardian_confirmed_at, isp.student_name, isp.created_date
        FROM individual_support_plans isp
        INNER JOIN students s ON isp.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE isp.guardian_confirmed = 1
        AND isp.guardian_confirmed_at >= ?
        AND u.classroom_id = ?
        ORDER BY isp.guardian_confirmed_at DESC
    ");
    $stmt->execute([$threeDaysAgo, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT isp.id, isp.guardian_confirmed_at, isp.student_name, isp.created_date
        FROM individual_support_plans isp
        WHERE isp.guardian_confirmed = 1
        AND isp.guardian_confirmed_at >= ?
        ORDER BY isp.guardian_confirmed_at DESC
    ");
    $stmt->execute([$threeDaysAgo]);
}
$confirmedPlans = $stmt->fetchAll();

// 5. 連絡帳の保護者確認（3日以内に確認されたもの）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT inote.id, inote.guardian_confirmed_at, s.student_name, dr.record_date, dr.activity_name
        FROM integrated_notes inote
        INNER JOIN students s ON inote.student_id = s.id
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        INNER JOIN users staff ON dr.staff_id = staff.id
        WHERE inote.guardian_confirmed = 1
        AND inote.guardian_confirmed_at >= ?
        AND staff.classroom_id = ?
        ORDER BY inote.guardian_confirmed_at DESC
    ");
    $stmt->execute([$threeDaysAgo, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT inote.id, inote.guardian_confirmed_at, s.student_name, dr.record_date, dr.activity_name
        FROM integrated_notes inote
        INNER JOIN students s ON inote.student_id = s.id
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        WHERE inote.guardian_confirmed = 1
        AND inote.guardian_confirmed_at >= ?
        ORDER BY inote.guardian_confirmed_at DESC
    ");
    $stmt->execute([$threeDaysAgo]);
}
$confirmedNotes = $stmt->fetchAll();

// 6. 未確認の連絡帳カウント（過去7日間で送信済み・未確認）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unconfirmed_count,
               SUM(CASE WHEN DATEDIFF(NOW(), inote.sent_at) >= 3 THEN 1 ELSE 0 END) as urgent_count
        FROM integrated_notes inote
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        INNER JOIN users staff ON dr.staff_id = staff.id
        WHERE inote.is_sent = 1
        AND inote.guardian_confirmed = 0
        AND inote.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND staff.classroom_id = ?
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unconfirmed_count,
               SUM(CASE WHEN DATEDIFF(NOW(), inote.sent_at) >= 3 THEN 1 ELSE 0 END) as urgent_count
        FROM integrated_notes inote
        WHERE inote.is_sent = 1
        AND inote.guardian_confirmed = 0
        AND inote.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
}
$unconfirmedStats = $stmt->fetch();
$unconfirmedCount = (int)($unconfirmedStats['unconfirmed_count'] ?? 0);
$urgentUnconfirmedCount = (int)($unconfirmedStats['urgent_count'] ?? 0);

// 本日の参加予定者を取得（休日を除外）
$todayDayOfWeek = date('w', strtotime($selectedDate)); // 0=日曜, 1=月曜, ...
$dayColumns = [
    0 => 'scheduled_sunday',
    1 => 'scheduled_monday',
    2 => 'scheduled_tuesday',
    3 => 'scheduled_wednesday',
    4 => 'scheduled_thursday',
    5 => 'scheduled_friday',
    6 => 'scheduled_saturday'
];
$todayColumn = $dayColumns[$todayDayOfWeek];

// 休日チェック（自分の教室の休日のみ）
$stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ? AND classroom_id = ?");
$stmt->execute([$selectedDate, $classroomId]);
$isHoliday = $stmt->fetchColumn() > 0;

$scheduledStudents = [];
$eventParticipants = [];

if (!$isHoliday) {
    // 通常の参加予定者を取得（自分の教室のみ）
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                an.id as absence_id,
                an.reason as absence_reason,
                'regular' as participant_type
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            LEFT JOIN absence_notifications an ON s.id = an.student_id AND an.absence_date = ?
            WHERE s.is_active = 1 AND s.$todayColumn = 1 AND u.classroom_id = ?
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                an.id as absence_id,
                an.reason as absence_reason,
                'regular' as participant_type
            FROM students s
            LEFT JOIN users u ON s.guardian_id = u.id
            LEFT JOIN absence_notifications an ON s.id = an.student_id AND an.absence_date = ?
            WHERE s.is_active = 1 AND s.$todayColumn = 1
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate]);
    }
    $scheduledStudents = $stmt->fetchAll();

    // 承認済み振替依頼の生徒を追加（振替希望日が本日の場合）
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                NULL as absence_id,
                '振替' as absence_reason,
                'makeup' as participant_type
            FROM absence_notifications an
            INNER JOIN students s ON an.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE an.makeup_request_date = ?
              AND an.makeup_status = 'approved'
              AND u.classroom_id = ?
              AND s.is_active = 1
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                NULL as absence_id,
                '振替' as absence_reason,
                'makeup' as participant_type
            FROM absence_notifications an
            INNER JOIN students s ON an.student_id = s.id
            LEFT JOIN users u ON s.guardian_id = u.id
            WHERE an.makeup_request_date = ?
              AND an.makeup_status = 'approved'
              AND s.is_active = 1
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate]);
    }
    $makeupStudents = $stmt->fetchAll();

    // 振替参加者を通常参加者リストに追加（重複チェック）
    $existingIds = array_column($scheduledStudents, 'id');
    foreach ($makeupStudents as $makeupStudent) {
        if (!in_array($makeupStudent['id'], $existingIds)) {
            $scheduledStudents[] = $makeupStudent;
            $existingIds[] = $makeupStudent['id'];
        }
    }

    // 追加利用者を取得
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                NULL as absence_id,
                '追加利用' as absence_reason,
                'additional' as participant_type
            FROM additional_usages au
            INNER JOIN students s ON au.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE au.usage_date = ?
              AND u.classroom_id = ?
              AND s.is_active = 1
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                NULL as absence_id,
                '追加利用' as absence_reason,
                'additional' as participant_type
            FROM additional_usages au
            INNER JOIN students s ON au.student_id = s.id
            LEFT JOIN users u ON s.guardian_id = u.id
            WHERE au.usage_date = ?
              AND s.is_active = 1
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate]);
    }
    $additionalStudents = $stmt->fetchAll();

    // 追加利用者を参加者リストに追加（重複チェック）
    foreach ($additionalStudents as $additionalStudent) {
        if (!in_array($additionalStudent['id'], $existingIds)) {
            $scheduledStudents[] = $additionalStudent;
            $existingIds[] = $additionalStudent['id'];
        }
    }

    // 学部別に分類
    $studentsByGrade = [
        'preschool' => [],
        'elementary' => [],
        'junior_high' => [],
        'high_school' => []
    ];

    foreach ($scheduledStudents as $student) {
        // 学年を再計算（学年調整を考慮）
        $gradeLevel = $student['birth_date']
            ? calculateGradeLevel($student['birth_date'], null, $student['grade_adjustment'] ?? 0)
            : ($student['grade_level'] ?? 'elementary');
        // 詳細学年からカテゴリを取得
        $gradeCategory = getGradeCategory($gradeLevel);
        if (isset($studentsByGrade[$gradeCategory])) {
            $studentsByGrade[$gradeCategory][] = $student;
        }
    }

    // イベント参加者を取得（自分の教室のみ）
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                e.event_name,
                er.notes,
                'event' as participant_type
            FROM event_registrations er
            INNER JOIN events e ON er.event_id = e.id
            INNER JOIN students s ON er.student_id = s.id
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE e.event_date = ? AND s.is_active = 1 AND u.classroom_id = ?
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_name,
                s.birth_date,
                s.grade_level,
                s.grade_adjustment,
                u.full_name as guardian_name,
                e.event_name,
                er.notes,
                'event' as participant_type
            FROM event_registrations er
            INNER JOIN events e ON er.event_id = e.id
            INNER JOIN students s ON er.student_id = s.id
            LEFT JOIN users u ON s.guardian_id = u.id
            WHERE e.event_date = ? AND s.is_active = 1
            ORDER BY s.student_name
        ");
        $stmt->execute([$selectedDate]);
    }
    $eventParticipants = $stmt->fetchAll();

    // イベント参加者も学部別に分類
    $eventsByGrade = [
        'preschool' => [],
        'elementary' => [],
        'junior_high' => [],
        'high_school' => []
    ];

    foreach ($eventParticipants as $participant) {
        // 学年を再計算（学年調整を考慮）
        $gradeLevel = $participant['birth_date']
            ? calculateGradeLevel($participant['birth_date'], null, $participant['grade_adjustment'] ?? 0)
            : ($participant['grade_level'] ?? 'elementary');
        // 詳細学年からカテゴリを取得
        $gradeCategory = getGradeCategory($gradeLevel);
        if (isset($eventsByGrade[$gradeCategory])) {
            $eventsByGrade[$gradeCategory][] = $participant;
        }
    }
}

// 個別支援計画書のタスクカウント（共通ヘルパーを使用）
$planTaskCounts = getPlanTaskCounts($pdo, $classroomId);
$planNeedingCount = $planTaskCounts['total'];
$planNoneCount = $planTaskCounts['none'];
$planDraftCount = $planTaskCounts['draft'];
$planNeedsConfirmCount = $planTaskCounts['needs_confirm'];
$planOverdueCount = $planTaskCounts['outdated'];
$planUrgentCount = $planTaskCounts['urgent'];

// モニタリングのタスクカウント（共通ヘルパーを使用）
$monitoringTaskCounts = getMonitoringTaskCounts($pdo, $classroomId);
$monitoringNeedingCount = $monitoringTaskCounts['total'];
$monitoringNoneCount = $monitoringTaskCounts['none'];
$monitoringDraftCount = $monitoringTaskCounts['draft'];
$monitoringNeedsConfirmCount = $monitoringTaskCounts['needs_confirm'];
$monitoringOverdueCount = $monitoringTaskCounts['outdated'];
$monitoringUrgentCount = $monitoringTaskCounts['urgent'];

// かけはし通知データを取得
$today = date('Y-m-d');

// 1. 未提出の保護者かけはし（非表示を除外、1ヶ月以内のみ）の件数を取得（自分の教室のみ、各生徒の最新期間のみ）
$guardianKakehashiCount = 0;
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            AND kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = 1
                AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            )
        ");
        $stmt->execute([$classroomId]);
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしでカウント
        error_log("Guardian kakehashi count error: " . $e->getMessage());
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            AND kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = 1
                AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            )
        ");
        $stmt->execute([$classroomId]);
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    }
} else {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            AND kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = 1
                AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            )
        ");
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしでカウント
        error_log("Guardian kakehashi count error: " . $e->getMessage());
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            AND kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = 1
                AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            )
        ");
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    }
}

// 2. スタッフかけはしのタスクカウント（共通ヘルパーを使用）
$staffKakehashiTaskCounts = getStaffKakehashiTaskCounts($pdo, $classroomId);
$staffKakehashiCount = $staffKakehashiTaskCounts['total'];
$staffKakehashiNoneCount = $staffKakehashiTaskCounts['none'];
$staffKakehashiDraftCount = $staffKakehashiTaskCounts['draft'];
$staffKakehashiNeedsConfirmCount = $staffKakehashiTaskCounts['needs_confirm'];
$staffKakehashiOverdueCount = $staffKakehashiTaskCounts['overdue'];
$staffKakehashiUrgentCount = $staffKakehashiTaskCounts['urgent'];
$staffKakehashiWarningCount = $staffKakehashiTaskCounts['warning'];

// 3. 未提出の提出期限の件数を取得（自分の教室のみ）
$submissionRequestCount = 0;
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE u.classroom_id = ?
        AND sr.is_completed = 0
    ");
    $stmt->execute([$classroomId]);
    $submissionRequestCount = (int)$stmt->fetchColumn();
} else {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM submission_requests sr
        WHERE sr.is_completed = 0
    ");
    $submissionRequestCount = (int)$stmt->fetchColumn();
}

// 4. 承認待ちの振替依頼の件数を取得（自分の教室のみ）
$makeupRequestCount = 0;
try {
    // makeup_statusカラムが存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM absence_notifications LIKE 'makeup_status'");
    $hasMakeupColumn = ($stmt->rowCount() > 0);

    if ($hasMakeupColumn) {
        if ($classroomId) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM absence_notifications an
                INNER JOIN students s ON an.student_id = s.id
                INNER JOIN users u ON s.guardian_id = u.id
                WHERE u.classroom_id = ?
                AND an.makeup_status = 'pending'
            ");
            $stmt->execute([$classroomId]);
            $makeupRequestCount = (int)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM absence_notifications an
                WHERE an.makeup_status = 'pending'
            ");
            $makeupRequestCount = (int)$stmt->fetchColumn();
        }
    }
} catch (PDOException $e) {
    // カラムが存在しない場合はスキップ
    $makeupRequestCount = 0;
}

// 未作成・未提出タスクの詳細データを取得（残り1ヶ月以内のみ表示）
// 個別支援計画書の期限別分類
$overduePlans = [];
$urgentPlans = [];
$notCreatedPlans = [];

// モニタリング表の期限別分類
$overdueMonitoring = [];
$urgentMonitoring = [];
$notCreatedMonitoring = [];

// 期限切れ（期限が過ぎているもの）
$overdueGuardianKakehashi = [];
$overdueStaffKakehashi = [];
$overdueSubmissionRequests = [];

// 期限内1か月以内（残り30日以内）
$urgentGuardianKakehashi = [];
$urgentStaffKakehashi = [];
$urgentSubmissionRequests = [];

// 1か月以上先（HTML表示用に空配列で初期化 - 表示はしないが変数参照エラー防止）
$pendingPlans = [];
$pendingMonitoring = [];
$pendingGuardianKakehashi = [];
$pendingStaffKakehashi = [];
$pendingSubmissionRequests = [];
$pendingUncreated = [];

if ($classroomId) {
    // 個別支援計画書の期限別取得
    // 1. 提出済みの計画書がない生徒（下書きのみも含む）
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM individual_support_plans isp
            WHERE isp.student_id = s.id AND isp.is_draft = 0
        )
    ");
    $stmt->execute([$classroomId]);
    $notCreatedPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 提出済みで期限別分類（最新の提出済み計画から5ヶ月以上経過＝残り1ヶ月以内のみ表示）
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            MAX(isp.created_date) as latest_plan_date,
            DATEDIFF(CURDATE(), MAX(isp.created_date)) as days_since_created
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id AND isp.is_draft = 0
        WHERE s.is_active = 1 AND u.classroom_id = ?
        GROUP BY s.id, s.student_name
        HAVING days_since_created >= 150
    ");
    $stmt->execute([$classroomId]);
    $oldPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldPlans as $plan) {
        $daysSince = $plan['days_since_created'];
        if ($daysSince >= 180) { // 6ヶ月以上（期限切れ）
            $overduePlans[] = $plan;
        } else { // 5ヶ月以上（1か月以内）
            $urgentPlans[] = $plan;
        }
    }

    // モニタリング表の期限別取得
    // 1. 提出済みモニタリングがない生徒（個別支援計画書がある生徒のみ、下書きのみも含む）
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.student_name
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id AND mr.is_draft = 0
        )
    ");
    $stmt->execute([$classroomId]);
    $notCreatedMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 最新の提出済みモニタリングから期限別分類（5ヶ月以上経過＝残り1ヶ月以内のみ表示）
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            MAX(mr.monitoring_date) as latest_monitoring_date,
            DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) as days_since_monitoring
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN monitoring_records mr ON s.id = mr.student_id AND mr.is_draft = 0
        WHERE s.is_active = 1 AND u.classroom_id = ?
        GROUP BY s.id, s.student_name
        HAVING days_since_monitoring >= 150
    ");
    $stmt->execute([$classroomId]);
    $oldMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldMonitoring as $monitoring) {
        $daysSince = $monitoring['days_since_monitoring'];
        if ($daysSince >= 180) { // 6ヶ月以上（期限切れ）
            $overdueMonitoring[] = $monitoring;
        } else { // 5ヶ月以上（1か月以内）
            $urgentMonitoring[] = $monitoring;
        }
    }

    // 保護者かけはし未提出（各生徒の最新期間のみ、1ヶ月以内のみ）
    try {
        $stmt = $pdo->prepare("
            SELECT
                s.id as student_id,
                s.student_name,
                kp.id as period_id,
                kp.start_date,
                kp.end_date,
                kp.submission_deadline,
                DATEDIFF(kp.submission_deadline, CURDATE()) as days_left
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            AND kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = 1
                AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            )
            ORDER BY kp.submission_deadline ASC
        ");
        $stmt->execute([$classroomId]);
        $allGuardianKakehashi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allGuardianKakehashi as $item) {
            if ($item['days_left'] < 0) {
                $overdueGuardianKakehashi[] = $item;
            } else {
                $urgentGuardianKakehashi[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
    }

    // スタッフかけはし（ヘルパーからのデータを使用）
    foreach ($staffKakehashiTaskCounts['items'] as $item) {
        $daysLeft = $item['days_left'] ?? 0;
        if ($daysLeft < 0) {
            $overdueStaffKakehashi[] = $item;
        } elseif ($daysLeft <= 7) {
            $urgentStaffKakehashi[] = $item;
        } else {
            $pendingStaffKakehashi[] = $item;
        }
    }

    // 提出期限未提出
    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.student_id,
            s.student_name,
            sr.title,
            sr.description,
            sr.due_date,
            DATEDIFF(sr.due_date, CURDATE()) as days_left
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE u.classroom_id = ?
        AND sr.is_completed = 0
        ORDER BY sr.due_date ASC
    ");
    $stmt->execute([$classroomId]);
    $allSubmissionRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allSubmissionRequests as $item) {
        if ($item['days_left'] < 0) {
            $overdueSubmissionRequests[] = $item;
        } elseif ($item['days_left'] <= 30) {
            $urgentSubmissionRequests[] = $item;
        } else {
            $pendingSubmissionRequests[] = $item;
        }
    }
} else {
    // 教室IDがない場合（管理者等）は全データを取得
    // 個別支援計画書の期限別取得
    // 提出済みの計画書がない生徒（下書きのみも含む）
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM individual_support_plans isp
            WHERE isp.student_id = s.id AND isp.is_draft = 0
        )
    ");
    $notCreatedPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 提出済みで期限別分類（最新の提出済み計画から5ヶ月以上経過＝残り1か月以内を表示）
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.student_name,
            MAX(isp.created_date) as latest_plan_date,
            DATEDIFF(CURDATE(), MAX(isp.created_date)) as days_since_created
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id AND isp.is_draft = 0
        WHERE s.is_active = 1
        GROUP BY s.id, s.student_name
        HAVING days_since_created >= 150
    ");
    $oldPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldPlans as $plan) {
        $daysSince = $plan['days_since_created'];
        if ($daysSince >= 180) { // 6ヶ月以上（期限切れ）
            $overduePlans[] = $plan;
        } else { // 5ヶ月以上（1か月以内）
            $urgentPlans[] = $plan;
        }
    }

    // モニタリング表の期限別取得
    // 提出済みモニタリングがない生徒（個別支援計画書がある生徒のみ、下書きのみも含む）
    $stmt = $pdo->query("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id AND mr.is_draft = 0
        )
    ");
    $notCreatedMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 最新の提出済みモニタリングから期限別分類（5ヶ月以上経過＝残り1か月以内を表示）
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.student_name,
            MAX(mr.monitoring_date) as latest_monitoring_date,
            DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) as days_since_monitoring
        FROM students s
        INNER JOIN monitoring_records mr ON s.id = mr.student_id AND mr.is_draft = 0
        WHERE s.is_active = 1
        GROUP BY s.id, s.student_name
        HAVING days_since_monitoring >= 150
    ");
    $oldMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldMonitoring as $monitoring) {
        $daysSince = $monitoring['days_since_monitoring'];
        if ($daysSince >= 180) {
            $overdueMonitoring[] = $monitoring;
        } else { // 5ヶ月以上（1か月以内）
            $urgentMonitoring[] = $monitoring;
        }
    }

    // 保護者かけはし未提出（各生徒の最新期間のみ、1ヶ月以内のみ）
    try {
        $stmt = $pdo->query("
            SELECT
                s.id as student_id,
                s.student_name,
                kp.id as period_id,
                kp.start_date,
                kp.end_date,
                kp.submission_deadline,
                DATEDIFF(kp.submission_deadline, CURDATE()) as days_left
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            AND kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = 1
                AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            )
            ORDER BY kp.submission_deadline ASC
        ");
        $allGuardianKakehashi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allGuardianKakehashi as $item) {
            if ($item['days_left'] < 0) {
                $overdueGuardianKakehashi[] = $item;
            } else {
                $urgentGuardianKakehashi[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
    }

    // スタッフかけはし（ヘルパーからのデータを使用）
    foreach ($staffKakehashiTaskCounts['items'] as $item) {
        $daysLeft = $item['days_left'] ?? 0;
        if ($daysLeft < 0) {
            $overdueStaffKakehashi[] = $item;
        } elseif ($daysLeft <= 7) {
            $urgentStaffKakehashi[] = $item;
        } else {
            $pendingStaffKakehashi[] = $item;
        }
    }

    // 提出期限未提出
    $stmt = $pdo->query("
        SELECT
            sr.id,
            sr.student_id,
            s.student_name,
            sr.title,
            sr.description,
            sr.due_date,
            DATEDIFF(sr.due_date, CURDATE()) as days_left
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        WHERE sr.is_completed = 0
        ORDER BY sr.due_date ASC
    ");
    $allSubmissionRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allSubmissionRequests as $item) {
        if ($item['days_left'] < 0) {
            $overdueSubmissionRequests[] = $item;
        } elseif ($item['days_left'] <= 30) {
            $urgentSubmissionRequests[] = $item;
        } else {
            $pendingSubmissionRequests[] = $item;
        }
    }
}

// 未作成のかけはし期間を取得（DBに登録されていないが作成すべき期間）
// pending_tasks.php と同じロジック
$uncreatedKakehashiPeriods = [];

// 対象の生徒一覧を取得
// students.classroom_id または 保護者の users.classroom_id でフィルタ
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1
        AND (s.classroom_id = ? OR u.classroom_id = ?)
        AND s.support_start_date IS NOT NULL
    ");
    $stmt->execute([$classroomId, $classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        WHERE s.is_active = 1
        AND s.support_start_date IS NOT NULL
    ");
}
$allStudentsForKakehashi = $stmt->fetchAll();

$todayDateForKakehashi = new DateTime();
$generationLimitForKakehashi = clone $todayDateForKakehashi;
$generationLimitForKakehashi->modify('+1 month');

foreach ($allStudentsForKakehashi as $studentData) {
    $studentId = $studentData['id'];
    $studentName = $studentData['student_name'];
    $supportStartDate = new DateTime($studentData['support_start_date']);

    // 既存のかけはし期間数を取得
    $stmt = $pdo->prepare("SELECT COUNT(*) as period_count FROM kakehashi_periods WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $existingCount = (int)$stmt->fetch()['period_count'];

    // 初回かけはし（支援開始日の1日前が期限）
    $firstDeadline = clone $supportStartDate;
    $firstDeadline->modify('-1 day');

    if ($existingCount === 0 && $firstDeadline <= $generationLimitForKakehashi) {
        $daysLeft = (int)$todayDateForKakehashi->diff($firstDeadline)->format('%r%a');
        $uncreatedKakehashiPeriods[] = [
            'student_id' => $studentId,
            'student_name' => $studentName,
            'period_type' => '初回',
            'deadline' => $firstDeadline->format('Y-m-d'),
            'days_left' => $daysLeft
        ];
    }

    // 2回目かけはし（初回期限の4ヶ月後が期限）
    $secondDeadline = clone $firstDeadline;
    $secondDeadline->modify('+4 months');

    if ($existingCount === 1 && $secondDeadline <= $generationLimitForKakehashi) {
        $daysLeft = (int)$todayDateForKakehashi->diff($secondDeadline)->format('%r%a');
        $uncreatedKakehashiPeriods[] = [
            'student_id' => $studentId,
            'student_name' => $studentName,
            'period_type' => '2回目',
            'deadline' => $secondDeadline->format('Y-m-d'),
            'days_left' => $daysLeft
        ];
    }

    // 3回目以降のかけはし（6ヶ月ごと）
    if ($existingCount >= 1) {
        $stmt = $pdo->prepare("
            SELECT submission_deadline
            FROM kakehashi_periods
            WHERE student_id = ?
            ORDER BY submission_deadline DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $latestPeriod = $stmt->fetch();

        if ($latestPeriod) {
            $latestDeadline = new DateTime($latestPeriod['submission_deadline']);
            $nextDeadline = clone $latestDeadline;

            $periodNum = $existingCount + 1;
            while (true) {
                $nextDeadline->modify('+6 months');

                if ($nextDeadline > $generationLimitForKakehashi) {
                    break;
                }

                $daysLeft = (int)$todayDateForKakehashi->diff($nextDeadline)->format('%r%a');
                $uncreatedKakehashiPeriods[] = [
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'period_type' => "{$periodNum}回目",
                    'deadline' => $nextDeadline->format('Y-m-d'),
                    'days_left' => $daysLeft
                ];

                $periodNum++;
            }
        }
    }
}

// 提出期限が近い順にソート
usort($uncreatedKakehashiPeriods, function($a, $b) {
    return $a['days_left'] - $b['days_left'];
});

// ページ開始
$currentPage = 'renrakucho_activities';
renderPageStart('staff', $currentPage, '活動管理');
?>

<style>
        .two-column-layout {
            display: grid;
            grid-template-columns: 600px 1fr;
            gap: 20px;
            align-items: start;
        }

        .left-column {
            /* カレンダー用 */
        }

        .right-column {
            /* 参加予定者一覧用 */
        }

        .main-content {
            grid-column: 1 / -1;
        }

        .notifications-container {
            background: var(--md-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .notification-item {
            padding: 15px;
            border-left: 4px solid var(--primary-purple);
            background: var(--md-bg-secondary);
            margin-bottom: 12px;
            border-radius: var(--radius-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-item.message {
            border-left-color: var(--md-green);
        }

        .notification-item.kakehashi {
            border-left-color: var(--md-orange);
        }

        .notification-item.monitoring {
            border-left-color: var(--md-teal);
        }

        .notification-item.plan {
            border-left-color: #6f42c1;
        }

        .notification-item.note-confirmed {
            border-left-color: var(--md-blue);
        }

        /* 未確認連絡帳アラート */
        .unconfirmed-alert {
            background: linear-gradient(135deg, rgba(255, 149, 0, 0.15) 0%, rgba(255, 204, 0, 0.1) 100%);
            border: 1px solid var(--md-orange);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .unconfirmed-alert.urgent {
            background: linear-gradient(135deg, rgba(255, 59, 48, 0.15) 0%, rgba(255, 69, 58, 0.1) 100%);
            border-color: var(--md-red);
        }
        .alert-content {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        .alert-icon {
            font-size: 32px;
        }
        .alert-title {
            font-size: 16px;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .alert-detail {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
        }
        .urgent-badge {
            display: inline-block;
            background: var(--md-red);
            color: white;
            padding: 2px 8px;
            border-radius: var(--radius-full);
            font-size: var(--text-caption-1);
            font-weight: bold;
            margin-left: 8px;
        }
        .alert-btn {
            padding: 12px 20px;
            background: var(--md-orange);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: bold;
            font-size: var(--text-subhead);
            white-space: nowrap;
        }
        .unconfirmed-alert.urgent .alert-btn {
            background: var(--md-red);
        }
        .alert-btn:hover {
            opacity: 0.9;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .notification-meta {
            font-size: var(--text-footnote);
            color: var(--text-secondary);
        }

        .notification-link {
            padding: var(--spacing-sm) 16px;
            background: var(--primary-purple);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            white-space: nowrap;
        }

        .notification-link:hover {
            background: var(--primary-purple);
        }

        /* お知らせアコーディオン */
        .notification-section {
            margin-bottom: 15px;
        }
        .notification-section:last-child {
            margin-bottom: 0;
        }
        .notification-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: var(--md-gray-6);
            border-radius: var(--radius-sm);
            cursor: pointer;
            user-select: none;
            transition: background 0.2s ease;
        }
        .notification-section-header:hover {
            background: var(--md-gray-5);
        }
        .notification-section-title {
            font-weight: bold;
            color: var(--text-primary);
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notification-section-count {
            background: var(--primary-purple);
            color: white;
            padding: 2px 8px;
            border-radius: var(--radius-full);
            font-size: var(--text-caption-1);
            font-weight: bold;
        }
        .notification-section-count.unread {
            background: var(--md-red);
        }
        .notification-section-count.confirmed {
            background: var(--md-green);
        }
        .notification-section-toggle {
            font-size: 16px;
            color: var(--text-secondary);
            transition: transform 0.3s ease;
        }
        .notification-section-toggle.collapsed {
            transform: rotate(-90deg);
        }
        .notification-section-content {
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease, padding 0.3s ease;
            max-height: 2000px;
            opacity: 1;
            padding-top: 10px;
        }
        .notification-section-content.collapsed {
            max-height: 0;
            opacity: 0;
            padding-top: 0;
        }

        /* タスクサマリー用スタイル */
        .task-summary-item {
            background: var(--md-bg-primary);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-md);
            border: 1px solid var(--border-color);
        }

        .task-summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }

        .task-summary-title {
            font-size: var(--text-body);
            font-weight: 600;
            color: var(--text-primary);
        }

        .task-summary-total {
            font-size: var(--text-title-3);
            font-weight: 700;
            color: var(--md-blue);
        }

        .task-summary-details {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

        .task-count {
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-weight: 600;
        }

        .task-count.overdue {
            background: rgba(255, 59, 48, 0.15);
            color: var(--md-red);
        }

        .task-count.urgent {
            background: rgba(255, 149, 0, 0.15);
            color: var(--md-orange);
        }

        .task-count.warning {
            background: rgba(255, 204, 0, 0.15);
            color: var(--md-yellow);
        }

        .task-count.needs-confirm {
            background: rgba(0, 150, 136, 0.15);
            color: var(--md-teal);
        }

        .task-count.draft {
            background: rgba(33, 150, 243, 0.15);
            color: var(--md-blue);
        }

        .task-summary-link {
            margin-left: auto;
            padding: 6px 16px;
            background: var(--md-blue);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-weight: 600;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .task-summary-link:hover {
            background: var(--md-blue);
            opacity: 0.8;
            transform: translateY(-1px);
        }

        /* ヘルプアイコン */
        .help-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: var(--md-gray-4);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            margin-left: 6px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .help-icon:hover {
            background: var(--md-blue);
            transform: scale(1.1);
        }
        .help-tooltip {
            display: none;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 8px;
            padding: 12px 16px;
            background: var(--md-bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            font-size: 13px;
            font-weight: normal;
            color: var(--text-secondary);
            white-space: normal;
            width: 280px;
            z-index: 1000;
            line-height: 1.5;
        }
        .help-tooltip::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 6px solid var(--border-color);
        }
        .help-tooltip::after {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-bottom: 5px solid var(--md-bg-primary);
        }
        .help-icon.active .help-tooltip {
            display: block;
        }

        .calendar-container {
            background: var(--md-bg-secondary);
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 600px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .calendar-header h2 {
            color: var(--text-primary);
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .calendar-nav {
            display: flex;
            gap: 4px;
        }

        .calendar-nav a {
            padding: 4px 8px;
            background: var(--primary-purple);
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 11px;
        }

        .calendar-nav a:hover {
            background: var(--primary-purple);
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .calendar-day-header {
            text-align: center;
            padding: 4px 2px;
            font-weight: bold;
            color: var(--text-secondary);
            font-size: 10px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid var(--md-gray-5);
            border-radius: 3px;
            padding: 3px;
            cursor: pointer;
            background: var(--md-bg-secondary);
            position: relative;
            transition: all 0.15s;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 50px;
        }

        .calendar-day:hover {
            background: var(--md-bg-tertiary);
            transform: scale(1.05);
        }

        .calendar-day.empty {
            background: var(--md-gray-6); opacity: 0.5;
            cursor: default;
        }

        .calendar-day.empty:hover {
            transform: none;
        }

        .calendar-day.today {
            border: 2px solid var(--primary-purple);
            background: rgba(107, 70, 193, 0.15);
        }

        .calendar-day.selected {
            background: var(--primary-purple);
            color: white;
        }

        .calendar-day.selected .calendar-day-number,
        .calendar-day.selected .calendar-day-content,
        .calendar-day.selected .event-label,
        .calendar-day.selected .holiday-label {
            color: white;
        }

        .calendar-day.has-activity {
            background: rgba(255, 159, 10, 0.15);
        }

        .calendar-day.has-activity.selected {
            background: var(--primary-purple);
        }

        .calendar-day.holiday {
            background: rgba(255, 59, 48, 0.15);
        }

        .calendar-day-number {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--text-primary);
        }

        .calendar-day-content {
            font-size: 8px;
            line-height: 1.2;
            width: 100%;
            color: var(--text-primary);
        }

        .holiday-label {
            color: #F44336;
            font-weight: bold;
            margin-bottom: 1px;
        }

        .event-label {
            color: var(--text-primary);
            margin-bottom: 1px;
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .event-label.clickable {
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .event-label.clickable:hover {
            opacity: 0.7;
        }

        .event-marker {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .calendar-day-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 4px;
            height: 4px;
            background: var(--md-bg-secondary);
            border-radius: 50%;
        }

        .day-type-label {
            font-size: 9px;
            padding: 1px 4px;
            border-radius: 3px;
            margin-bottom: 2px;
            display: inline-block;
        }

        .day-type-label.weekday {
            background: rgba(52, 199, 89, 0.25);
            color: #059669;
        }

        .day-type-label.school-holiday {
            background: rgba(59, 130, 246, 0.25);
            color: #2563eb;
        }

        /* 選択日のラベルを見やすく */
        .calendar-day.selected .day-type-label {
            background: rgba(255, 255, 255, 0.9);
            font-weight: 600;
        }

        .calendar-day.selected .day-type-label.weekday {
            color: #047857;
        }

        .calendar-day.selected .day-type-label.school-holiday {
            color: #1d4ed8;
        }

        .activity-section {
            grid-column: 1 / -1;
            background: var(--md-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }

        .date-info {
            font-size: var(--text-title3);
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }

        .date-info::before {
            content: 'event';
            font-family: 'Material Symbols Outlined';
            font-size: 1.1em;
        }

        .no-activity-message {
            color: var(--text-secondary);
            font-size: var(--text-body);
            margin-bottom: var(--spacing-md);
        }

        .activity-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .activity-buttons .add-activity-btn {
            flex: 1;
            min-width: 150px;
        }

        .activity-list {
            margin-bottom: var(--spacing-lg);
        }

        .activity-list h2 {
            color: #1d1d1f;
            margin-bottom: 15px;
            font-size: 20px;
            font-weight: 700;
        }

        .activity-card {
            border: 2px solid #e5e5e7;
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: var(--md-bg-secondary);
        }

        .activity-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
            gap: 10px;
        }

        .activity-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .participant-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .activity-content {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--md-bg-tertiary);
            border-radius: var(--radius-sm);
            line-height: 1.6;
        }

        .activity-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: var(--spacing-sm) 16px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            font-size: var(--text-subhead);
            transition: transform var(--duration-fast) var(--ease-out);
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-edit,
        .btn-integrate,
        .btn-integrate-edit,
        .btn-view {
            background: var(--md-bg-primary);
            color: var(--md-blue);
            border: 1px solid var(--md-blue);
        }
        .btn-edit:hover,
        .btn-integrate:hover,
        .btn-integrate-edit:hover,
        .btn-view:hover {
            background: var(--md-blue);
            color: white;
        }

        .btn-delete {
            background: var(--md-bg-primary);
            color: var(--md-red);
            border: 1px solid var(--md-red);
        }
        .btn-delete:hover {
            background: var(--md-red);
            color: white;
        }

        .add-activity-btn {
            padding: 15px 30px;
            background: var(--md-green);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }

        .add-activity-btn:hover {
            background: var(--md-green);
        }

        .empty-message {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--text-secondary);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--md-green);
        }

        .error-message {
            background: var(--md-bg-secondary);
            color: #721c24;
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--md-red);
        }

        .sunday {
            color: var(--md-red);
        }

        .saturday {
            color: var(--md-blue);
        }

        .scheduled-students-box {
            background: var(--md-bg-secondary);
            padding: 15px;
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
        }

        .scheduled-students-box h3 {
            color: var(--text-primary);
            font-size: var(--text-callout);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-purple);
        }

        .student-item {
            padding: var(--spacing-md);
            margin-bottom: 8px;
            background: var(--md-gray-6);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--primary-purple);
        }

        .student-item-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .student-item-meta {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
        }

        .holiday-notice {
            text-align: center;
            padding: var(--spacing-2xl) 20px;
            color: var(--md-red);
            font-weight: bold;
        }

        .no-students {
            text-align: center;
            padding: var(--spacing-2xl) 20px;
            color: var(--text-secondary);
        }

        /* アコーディオンスタイル */
        .accordion-section {
            margin-bottom: 8px;
        }

        .accordion-header {
            background: var(--md-bg-secondary);
            color: var(--text-primary);
            padding: var(--spacing-md) 15px;
            cursor: pointer;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: var(--text-subhead);
            transition: all var(--duration-normal) var(--ease-out);
            user-select: none;
        }

        .accordion-header:hover {
            opacity: 0.9;
        }

        .accordion-header.elementary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }

        .accordion-header.junior_high {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }

        .accordion-header.high_school {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }

        .accordion-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .accordion-count {
            background: var(--md-gray-5);
            padding: 2px 8px;
            border-radius: var(--radius-md);
            font-size: var(--text-caption-1);
            font-weight: bold;
        }

        .accordion-arrow {
            transition: transform 0.3s;
            font-size: var(--text-caption-1);
        }

        .accordion-header.active .accordion-arrow {
            transform: rotate(180deg);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: var(--md-gray-6);
            border-radius: 0 0 6px 6px;
        }

        .accordion-content.active {
            max-height: 1000px;
            transition: max-height 0.5s ease-in;
        }

        .accordion-body {
            padding: var(--spacing-md);
        }

        .notification-banner {
            background: var(--md-bg-secondary);
            padding: var(--spacing-lg) 25px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }

        .notification-banner.urgent {
            border-left: 5px solid var(--md-red);
            background: #3a2020;
        }

        .notification-banner.warning {
            border-left: 5px solid var(--md-orange);
            background: #3a3820;
        }

        .notification-banner.info {
            border-left: 5px solid #17a2b8;
            background: var(--md-bg-secondary);
        }

        .notification-banner.overdue {
            border-left: 5px solid var(--md-gray);
            background: var(--md-gray-6);
        }

        .notification-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
        }

        .notification-header.urgent {
            color: var(--md-red);
        }

        .notification-header.warning {
            color: #ff9800;
        }

        .notification-header.overdue {
            color: var(--md-gray);
        }

        .notification-header.info {
            color: var(--md-teal);
        }

        .notification-item {
            background: var(--md-bg-secondary);
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--md-gray-5);
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-info {
            flex: 1;
        }

        .notification-student {
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .notification-period {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
            margin-bottom: 3px;
        }

        .notification-deadline {
            font-size: var(--text-subhead);
            font-weight: bold;
        }

        .notification-deadline.urgent {
            color: var(--md-red);
        }

        .notification-deadline.warning {
            color: #ff9800;
        }

        .notification-deadline.overdue {
            color: var(--md-gray);
        }

        .notification-deadline.info {
            color: var(--md-teal);
        }

        .task-summary-box {
            background: var(--md-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }

        .task-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .task-card {
            background: var(--md-gray-6);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-purple);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-card.has-tasks {
            border-left-color: var(--md-red);
            background: rgba(255, 69, 58, 0.1);
        }

        .task-card.has-warnings {
            border-left-color: var(--md-orange);
            background: rgba(255, 159, 10, 0.1);
        }

        .task-card-title {
            font-size: var(--text-subhead);
            color: #6c757d;
            font-weight: 600;
        }

        .task-card-count {
            font-size: 32px;
            font-weight: 700;
            color: #1d1d1f;
        }

        .task-card-count.urgent {
            color: var(--md-red);
        }

        .task-card-count.warning {
            color: #ff9800;
        }

        .task-card-count.success {
            color: var(--md-green);
        }

        .task-card-link {
            margin-top: auto;
        }

        .btn-task-detail {
            display: inline-block;
            padding: var(--spacing-md) 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-weight: 600;
            text-align: center;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .btn-task-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .notification-action {
            margin-left: 15px;
        }

        .notification-btn {
            padding: var(--spacing-md) 20px;
            background: var(--primary-purple);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-weight: bold;
            display: inline-block;
            transition: background 0.3s;
        }

        .notification-btn:hover {
            background: var(--primary-purple);
        }

        .notification-btn.staff {
            background: var(--primary-purple-dark);
        }

        .notification-btn.staff:hover {
            background: #5d3a7f;
        }

        .notifications-container {
            margin-bottom: var(--spacing-lg);
        }

        /* デスクトップ用レイアウト（デフォルト） */
        @media (min-width: 769px) {
            .two-column-layout {
                grid-template-columns: 600px 1fr !important;
            }
        }

        /* レスポンシブデザイン */
        @media (max-width: 768px) {
            .two-column-layout {
                grid-template-columns: 1fr;
            }

            .activity-section {
                padding: var(--spacing-md);
            }

            .date-info {
                font-size: var(--text-body);
            }

            .activity-buttons {
                flex-direction: column;
            }

            .activity-buttons .add-activity-btn {
                width: 100%;
            }

            .calendar-container {
                max-width: 100%;
                padding: var(--spacing-sm);
            }

            .calendar-day {
                min-height: 40px;
                font-size: 11px;
            }

            .calendar-day-number {
                font-size: 10px;
            }

            .activity-dot {
                width: 5px;
                height: 5px;
            }

            .event-indicator, .holiday-indicator {
                font-size: 8px;
                padding: 1px 3px;
            }

            .content-box {
                padding: 15px;
            }

            .scheduled-list {
                font-size: var(--text-footnote);
            }

            .activity-card {
                padding: var(--spacing-md);
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .notification-card {
                padding: var(--spacing-md);
            }

            .notification-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-action {
                margin-left: 0;
                margin-top: 10px;
            }
        }

        @media (max-width: 480px) {
            .calendar {
                gap: 1px;
            }

            .calendar-day {
                min-height: 35px;
                padding: 2px;
            }

            .calendar-day-header {
                font-size: 9px;
            }

            .activity-card h3 {
                font-size: var(--text-callout);
            }
        }

        /* イベントモーダル */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--md-bg-secondary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
        }

        .modal-header {
            margin-bottom: var(--spacing-lg);
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-purple);
        }

        .modal-header h2 {
            color: var(--text-primary);
            font-size: 22px;
            margin: 0;
        }

        .modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: var(--text-secondary);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .event-detail-section {
            margin-bottom: var(--spacing-lg);
        }

        .event-detail-section h4 {
            color: var(--primary-purple);
            font-size: var(--text-callout);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .event-detail-section p {
            color: var(--text-primary);
            font-size: var(--text-subhead);
            line-height: 1.6;
            margin: 0;
            white-space: pre-wrap;
        }

        .event-detail-section.staff-only {
            background: #3a3420;
            padding: 15px;
            border-radius: var(--radius-sm);
            border-left: 4px solid #ff9800;
        }

        .event-detail-section.staff-only h4 {
            color: #ff9800;
        }

        .event-detail-section.staff-only p {
            color: #f5f5f5;
        }

        .no-data {
            text-align: center;
            padding: var(--spacing-lg);
            color: var(--text-secondary);
        }
    </style>

        <script>
        // アコーディオンのトグル機能
        function toggleAccordion(header) {
            const isActive = header.classList.contains('active');
            const content = header.nextElementSibling;

            if (isActive) {
                header.classList.remove('active');
                content.classList.remove('active');
            } else {
                header.classList.add('active');
                content.classList.add('active');
            }
        }
        </script>

        <!-- ページヘッダー -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">活動管理</h1>
                <p class="page-subtitle"><?= date('Y年n月') ?> - 連絡帳・かけはしの記録</p>
            </div>
        </div>

        <!-- 新着チャットメッセージ通知 -->
        <?php if ($totalUnreadMessages > 0): ?>
            <div class="unread-notification" style="background: rgba(0, 122, 255, 0.1); border-left: 5px solid var(--md-blue); border-radius: var(--radius-md); padding: 20px; margin-bottom: var(--spacing-lg);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; font-size: var(--text-headline); font-weight: bold; color: var(--md-blue);">
                    <span class="material-symbols-outlined">chat</span> 新着メッセージがあります！（<?= $totalUnreadMessages ?>件）
                </div>
                <?php foreach ($unreadChatMessages as $chatRoom): ?>
                    <div style="background: var(--md-bg-primary); padding: 15px; border-radius: var(--radius-sm); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: bold; color: var(--text-primary); margin-bottom: 5px;">
                                <?= htmlspecialchars($chatRoom['student_name']) ?>さん（<?= htmlspecialchars($chatRoom['guardian_name']) ?>様）
                            </div>
                            <div style="font-size: var(--text-subhead); color: var(--text-secondary); margin-bottom: 3px;">
                                未読メッセージ: <?= $chatRoom['unread_count'] ?>件
                            </div>
                            <div style="font-size: var(--text-subhead); font-weight: bold; color: var(--md-blue);">
                                最新: <?= date('Y年n月j日 H:i', strtotime($chatRoom['last_message_at'])) ?>
                            </div>
                        </div>
                        <a href="chat.php?room_id=<?= $chatRoom['room_id'] ?>" class="btn btn-primary btn-sm">チャットを開く</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php
                echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- 振替希望通知 -->
        <?php if ($pendingMakeupCount > 0): ?>
            <div class="notification-banner urgent" style="margin-bottom: 20px;">
                <div class="notification-header urgent">
                    <span class="material-symbols-outlined">sync</span> 振替希望があります（<?= $pendingMakeupCount ?>件）
                </div>
                <?php foreach ($pendingMakeupRequests as $request):
                    $makeupDate = new DateTime($request['makeup_request_date']);
                    $absenceDate = new DateTime($request['absence_date']);
                ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?= htmlspecialchars($request['student_name']) ?>さん
                            </div>
                            <div class="notification-period">
                                欠席日: <?= $absenceDate->format('n月j日') ?>（<?= ['日','月','火','水','木','金','土'][$absenceDate->format('w')] ?>）
                                → 振替希望日: <strong><?= $makeupDate->format('n月j日') ?>（<?= ['日','月','火','水','木','金','土'][$makeupDate->format('w')] ?>）</strong>
                            </div>
                            <?php if ($request['reason']): ?>
                                <div class="notification-period" style="font-size: var(--text-caption-1); color: var(--text-tertiary);">
                                    理由: <?= htmlspecialchars($request['reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notification-action">
                            <a href="makeup_requests.php?status=pending" class="notification-btn">
                                対応する
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 面談予約対応通知 -->
        <?php if ($pendingMeetingResponseCount > 0): ?>
            <div class="notification-banner" style="margin-bottom: 20px; background: rgba(175, 82, 222, 0.1); border-left: 4px solid var(--md-purple);">
                <div class="notification-header" style="color: var(--md-purple);">
                    <span class="material-symbols-outlined">calendar_month</span> 面談日程の対応が必要です（<?= $pendingMeetingResponseCount ?>件）
                </div>
                <?php foreach ($pendingMeetingResponses as $meetingReq): ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?= htmlspecialchars($meetingReq['student_name']) ?>さん（<?= htmlspecialchars($meetingReq['guardian_name'] ?? '') ?>）
                            </div>
                            <div class="notification-period">
                                面談目的: <?= htmlspecialchars($meetingReq['purpose']) ?>
                            </div>
                            <div class="notification-period" style="font-size: var(--text-caption-1); color: var(--md-purple);">
                                保護者から別日程の提案があります
                            </div>
                        </div>
                        <div class="notification-action">
                            <a href="meeting_response.php?request_id=<?= $meetingReq['id'] ?>" class="notification-btn" style="background: var(--md-purple);">
                                対応する
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 旧バージョンのコメントアウト -->
        <?php if (false && !empty($studentsWithoutPlan)): ?>
            <div class="notification-banner urgent">
                <div class="notification-header urgent">
                    <span class="material-symbols-outlined">warning</span> 【重要】個別支援計画書が未作成の生徒がいます
                </div>
                <?php foreach ($studentsWithoutPlan as $student): ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?php echo htmlspecialchars($student['student_name']); ?>さん
                            </div>
                            <?php if ($student['support_start_date']): ?>
                                <div class="notification-period">
                                    支援開始日: <?php echo date('Y年n月j日', strtotime($student['support_start_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notification-action">
                            <a href="kobetsu_plan.php?student_id=<?php echo $student['id']; ?>" class="notification-btn">
                                計画書を作成
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 未作成・未提出タスク通知セクション -->
        <?php
        // 各タスクの合計件数を計算（pending_tasks.phpと同じロジックで計算済みの変数を使用）
        $totalPlanNeeding = $planNeedingCount;
        $totalMonitoringNeeding = $monitoringNeedingCount;
        $totalUncreatedKakehashi = count($uncreatedKakehashiPeriods);
        $totalGuardianKakehashi = count($overdueGuardianKakehashi) + count($urgentGuardianKakehashi) + count($pendingGuardianKakehashi);
        $totalStaffKakehashi = $staffKakehashiCount;  // ヘルパー関数の統一カウントを使用
        $totalSubmissionRequests = count($overdueSubmissionRequests) + count($urgentSubmissionRequests) + count($pendingSubmissionRequests);

        // いずれかのタスクが存在する場合のみセクションを表示
        if ($totalPlanNeeding > 0 || $totalMonitoringNeeding > 0 || $totalUncreatedKakehashi > 0 || $totalGuardianKakehashi > 0 || $totalStaffKakehashi > 0 || $totalSubmissionRequests > 0):
        ?>
        <div class="notifications-container">
            <h2 style="margin-bottom: 20px; color: var(--text-primary); font-size: var(--text-title-3); font-weight: 600; display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 1.2em;">assignment</span> 未作成・未提出タスク</h2>

            <!-- 個別支援計画書 -->
            <?php if ($totalPlanNeeding > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">description</span> 個別支援計画書
                            <span class="help-icon" onclick="toggleHelp(this, event)">?
                                <div class="help-tooltip">生徒ごとに作成が必要な個別支援計画書の状況です。未作成・下書き中・更新期限が近いものが表示されます。計画書は6ヶ月ごとに更新が必要です。</div>
                            </span>
                        </span>
                        <span class="task-summary-total"><?php echo $totalPlanNeeding; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if ($planNoneCount > 0): ?>
                            <span class="task-count overdue">未作成 <?php echo $planNoneCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($planDraftCount > 0): ?>
                            <span class="task-count warning">下書き <?php echo $planDraftCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($planNeedsConfirmCount > 0): ?>
                            <span class="task-count needs-confirm">要保護者確認 <?php echo $planNeedsConfirmCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($planOverdueCount > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo $planOverdueCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($planUrgentCount > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo $planUrgentCount; ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- モニタリング表 -->
            <?php if ($totalMonitoringNeeding > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">monitoring</span> モニタリング表
                            <span class="help-icon" onclick="toggleHelp(this, event)">?
                                <div class="help-tooltip">個別支援計画書の進捗を確認するモニタリング表の状況です。次の個別支援計画書作成の1ヶ月前までに作成する必要があります。</div>
                            </span>
                        </span>
                        <span class="task-summary-total"><?php echo $totalMonitoringNeeding; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if ($monitoringNoneCount > 0): ?>
                            <span class="task-count overdue">未作成 <?php echo $monitoringNoneCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($monitoringDraftCount > 0): ?>
                            <span class="task-count warning">下書き <?php echo $monitoringDraftCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($monitoringNeedsConfirmCount > 0): ?>
                            <span class="task-count needs-confirm">要保護者確認 <?php echo $monitoringNeedsConfirmCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($monitoringOverdueCount > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo $monitoringOverdueCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($monitoringUrgentCount > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo $monitoringUrgentCount; ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

<!-- 保護者かけはし -->
            <?php if ($totalGuardianKakehashi > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">edit_note</span> 保護者かけはし未提出
                            <span class="help-icon" onclick="toggleHelp(this, event)">?
                                <div class="help-tooltip">保護者がまだ記入していない「かけはし」の件数です。期間終了前に保護者へ記入を依頼してください。</div>
                            </span>
                        </span>
                        <span class="task-summary-total"><?php echo $totalGuardianKakehashi; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if (count($overdueGuardianKakehashi) > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo count($overdueGuardianKakehashi); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($urgentGuardianKakehashi) > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo count($urgentGuardianKakehashi); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($pendingGuardianKakehashi) > 0): ?>
                            <span class="task-count warning">1か月以上 <?php echo count($pendingGuardianKakehashi); ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- スタッフかけはし -->
            <?php if ($staffKakehashiCount > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">edit_note</span> スタッフかけはし
                            <span class="help-icon" onclick="toggleHelp(this, event)">?
                                <div class="help-tooltip">スタッフかけはしの状況です。未作成・下書き・保護者確認待ちの件数を表示しています。</div>
                            </span>
                        </span>
                        <span class="task-summary-total"><?php echo $staffKakehashiCount; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if ($staffKakehashiOverdueCount > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo $staffKakehashiOverdueCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($staffKakehashiUrgentCount > 0): ?>
                            <span class="task-count urgent">緊急 <?php echo $staffKakehashiUrgentCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($staffKakehashiDraftCount > 0): ?>
                            <span class="task-count draft">下書き <?php echo $staffKakehashiDraftCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($staffKakehashiNeedsConfirmCount > 0): ?>
                            <span class="task-count needs-confirm">要保護者確認 <?php echo $staffKakehashiNeedsConfirmCount; ?>件</span>
                        <?php endif; ?>
                        <?php if ($staffKakehashiWarningCount > 0): ?>
                            <span class="task-count warning">未作成 <?php echo $staffKakehashiWarningCount; ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 提出期限 -->
            <?php if ($totalSubmissionRequests > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">upload_file</span> 提出期限未提出
                            <span class="help-icon" onclick="toggleHelp(this, event)">?
                                <div class="help-tooltip">保護者へ依頼した書類（サービス提供実績記録票など）の提出状況です。期限までに保護者から提出してもらうよう確認してください。</div>
                            </span>
                        </span>
                        <span class="task-summary-total"><?php echo $totalSubmissionRequests; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if (count($overdueSubmissionRequests) > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo count($overdueSubmissionRequests); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($urgentSubmissionRequests) > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo count($urgentSubmissionRequests); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($pendingSubmissionRequests) > 0): ?>
                            <span class="task-count warning">1か月以上 <?php echo count($pendingSubmissionRequests); ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 未確認連絡帳アラート -->
        <?php if ($unconfirmedCount > 0): ?>
        <div class="unconfirmed-alert <?php echo $urgentUnconfirmedCount > 0 ? 'urgent' : ''; ?>">
            <div class="alert-content">
                <div class="alert-icon"><span class="material-symbols-outlined"><?php echo $urgentUnconfirmedCount > 0 ? 'warning' : 'warning'; ?></span></div>
                <div class="alert-text">
                    <div class="alert-title">未確認の連絡帳があります</div>
                    <div class="alert-detail">
                        過去7日間で <strong><?php echo $unconfirmedCount; ?>件</strong> の連絡帳が保護者に確認されていません
                        <?php if ($urgentUnconfirmedCount > 0): ?>
                            <span class="urgent-badge">うち <?php echo $urgentUnconfirmedCount; ?>件が3日以上経過</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="unconfirmed_notes.php" class="alert-btn">未確認一覧を確認</a>
        </div>
        <?php endif; ?>

        <!-- 連絡帳確認済み一覧 -->
        <?php if (!empty($confirmedNotes)): ?>
        <div class="notifications-container">
            <h2 style="margin-bottom: 15px; color: var(--text-primary); font-size: 18px; display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 1.2em;">assignment</span> 連絡帳確認済み一覧（過去3日以内）</h2>

            <!-- 確認済み連絡帳（アコーディオン・初期で閉じている） -->
            <div class="notification-section">
                <div class="notification-section-header" onclick="toggleNotificationSection(this)">
                    <div class="notification-section-title">
                        <span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">check_circle</span> 保護者確認済み
                        <span class="notification-section-count confirmed"><?php echo count($confirmedNotes); ?>件</span>
                    </div>
                    <span class="notification-section-toggle collapsed">▼</span>
                </div>
                <div class="notification-section-content collapsed">
                    <?php foreach ($confirmedNotes as $note): ?>
                    <div class="notification-item note-confirmed">
                        <div class="notification-content">
                            <div class="notification-title"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">check_circle</span> 連絡帳が保護者に確認されました</div>
                            <div class="notification-meta">
                                <?php echo htmlspecialchars($note['student_name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($note['activity_name'], ENT_QUOTES, 'UTF-8'); ?>（<?php echo date('Y年m月d日', strtotime($note['record_date'])); ?>）
                                - 確認日時: <?php echo date('Y年m月d日 H:i', strtotime($note['guardian_confirmed_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 2カラムレイアウト -->
        <div class="two-column-layout">
            <!-- 左カラム: カレンダー -->
            <div class="left-column">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <h2><?php echo $year; ?>年 <?php echo $month; ?>月</h2>
                        <div class="calendar-nav">
                            <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>&date=<?php echo "$prevYear-" . str_pad($prevMonth, 2, '0', STR_PAD_LEFT) . "-01"; ?>">← 前月</a>
                            <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('n'); ?>&date=<?php echo date('Y-m-d'); ?>">今月</a>
                            <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>&date=<?php echo "$nextYear-" . str_pad($nextMonth, 2, '0', STR_PAD_LEFT) . "-01"; ?>">次月 →</a>
                        </div>
                    </div>

                    <div class="calendar">
                        <?php
                        $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
                        foreach ($weekDays as $index => $day) {
                            $class = '';
                            if ($index === 0) $class = 'sunday';
                            if ($index === 6) $class = 'saturday';
                            echo "<div class='calendar-day-header $class'>$day</div>";
                        }

                        // 月初の曜日（0=日曜日）
                        $startDayOfWeek = date('w', $firstDay);

                        // 空白セルを追加
                        for ($i = 0; $i < $startDayOfWeek; $i++) {
                            echo "<div class='calendar-day empty'></div>";
                        }

                        // 日付セルを追加
                        $daysInMonth = date('t', $firstDay);
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
                            $dayOfWeek = date('w', strtotime($currentDate));

                            $classes = ['calendar-day'];
                            if ($currentDate === date('Y-m-d')) {
                                $classes[] = 'today';
                            }
                            if ($currentDate === $selectedDate) {
                                $classes[] = 'selected';
                            }
                            if (in_array($currentDate, $activeDates)) {
                                $classes[] = 'has-activity';
                            }
                            if (isset($holidayDates[$currentDate])) {
                                $classes[] = 'holiday';
                            }

                            $dayClass = '';
                            if ($dayOfWeek === 0) $dayClass = 'sunday';
                            if ($dayOfWeek === 6) $dayClass = 'saturday';

                            echo "<div class='" . implode(' ', $classes) . "' onclick=\"navigateToDate('$year', '$month', '$currentDate')\">";
                            echo "<div class='calendar-day-number $dayClass'>$day</div>";
                            echo "<div class='calendar-day-content'>";

                            // 休日を表示
                            if (isset($holidayDates[$currentDate])) {
                                echo "<div class='holiday-label'>" . htmlspecialchars($holidayDates[$currentDate]['name']) . "</div>";
                            } else {
                                // 学校休業日/平日の表示
                                if (array_key_exists($currentDate, $schoolHolidayDates)) {
                                    echo "<div class='day-type-label school-holiday'>学休</div>";
                                } else {
                                    echo "<div class='day-type-label weekday'>平日</div>";
                                }
                            }

                            // イベントを表示
                            if (isset($eventDates[$currentDate])) {
                                foreach ($eventDates[$currentDate] as $event) {
                                    $eventJson = htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8');
                                    echo "<div class='event-label clickable' onclick='event.stopPropagation(); showEventModal(" . $eventJson . ");'>";
                                    echo "<span class='event-marker' style='background: " . htmlspecialchars($event['color']) . ";'></span>";
                                    echo htmlspecialchars($event['name']);
                                    echo "</div>";
                                }
                            }

                            // 面談予定を表示
                            if (isset($calendarMeetings[$currentDate]) && !empty($calendarMeetings[$currentDate])) {
                                foreach ($calendarMeetings[$currentDate] as $meetingInfo) {
                                    echo "<div class='meeting-label' style='background: rgba(175, 82, 222, 0.15); color: var(--md-purple); padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-top: 2px; cursor: pointer;' onclick=\"event.stopPropagation(); window.location.href='meeting_response.php?request_id=" . $meetingInfo['id'] . "';\">";
                                    echo "<span class='material-symbols-outlined' style='font-size: 14px; vertical-align: middle;'>calendar_month</span> ";
                                    echo htmlspecialchars($meetingInfo['time']) . " " . htmlspecialchars($meetingInfo['student_name']) . " 面談";
                                    echo "</div>";
                                }
                            }

                            echo "</div>";
                            if (in_array($currentDate, $activeDates)) {
                                echo "<div class='calendar-day-indicator'></div>";
                            }
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- 右カラム: 本日の参加予定者 -->
            <div class="right-column">
                <!-- 業務日誌クイックアクセス -->
                <div class="work-diary-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: var(--radius-md); margin-bottom: 15px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="color: white;">
                            <div style="font-weight: bold; font-size: var(--text-callout); margin-bottom: 4px; display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 1em;">menu_book</span> 業務日誌</div>
                            <div style="font-size: var(--text-footnote); opacity: 0.9;"><?php echo date('n月j日'); ?>の業務記録</div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="work_diary.php?date=<?php echo date('Y-m-d'); ?>" style="padding: 8px 16px; background: white; color: #667eea; text-decoration: none; border-radius: var(--radius-sm); font-weight: bold; font-size: var(--text-footnote);">作成・編集</a>
                            <a href="work_diary_calendar.php" style="padding: 8px 12px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: var(--radius-sm); font-size: var(--text-footnote);">履歴</a>
                        </div>
                    </div>
                </div>

                <div class="scheduled-students-box">
                    <h3 style="display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 1.2em;">assignment</span> 本日の参加予定者</h3>
                    <?php if ($isHoliday): ?>
                        <div class="holiday-notice">
                            本日は休日です
                        </div>
                    <?php elseif (empty($scheduledStudents)): ?>
                        <div class="no-students">
                            本日の参加予定者はいません
                        </div>
                    <?php else: ?>
                        <?php
                        $gradeInfo = [
                            'preschool' => ['label' => '未就学児', 'icon' => '<span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">child_care</span>'],
                            'elementary' => ['label' => '小学生', 'icon' => '<span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">school</span>'],
                            'junior_high' => ['label' => '中学生', 'icon' => '<span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">menu_book</span>'],
                            'high_school' => ['label' => '高校生', 'icon' => '<span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">school</span>']
                        ];

                        // 教室の対象学年設定を取得
                        $targetGrades = isset($classroom['target_grades']) && !empty($classroom['target_grades'])
                            ? explode(',', $classroom['target_grades'])
                            : ['preschool', 'elementary', 'junior_high', 'high_school'];

                        foreach ($gradeInfo as $gradeKey => $info):
                            // 教室の対象学年に含まれていない場合はスキップ
                            if (!in_array($gradeKey, $targetGrades)) continue;

                            $students = $studentsByGrade[$gradeKey];
                            $events = $eventsByGrade[$gradeKey];
                            $totalCount = count($students) + count($events);
                        ?>
                            <div class="accordion-section">
                                <div class="accordion-header <?= $gradeKey ?>" onclick="toggleAccordion(this)">
                                    <div class="accordion-title">
                                        <span><?= $info['icon'] ?> <?= $info['label'] ?></span>
                                        <span class="accordion-count"><?= $totalCount ?>名</span>
                                    </div>
                                    <span class="accordion-arrow">▼</span>
                                </div>
                                <div class="accordion-content">
                                    <div class="accordion-body">
                                        <?php foreach ($students as $student): ?>
                                            <div class="student-item">
                                                <div class="student-item-name">
                                                    <?php echo htmlspecialchars($student['student_name']); ?>
                                                    <?php if ($student['absence_id']): ?>
                                                        <span style="color: var(--md-red); font-weight: bold; margin-left: 8px;"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">close</span> 欠席</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($student['guardian_name']): ?>
                                                    <div class="student-item-meta">
                                                        保護者: <?php echo htmlspecialchars($student['guardian_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($student['absence_id'] && $student['absence_reason']): ?>
                                                    <div class="student-item-meta" style="color: var(--md-red);">
                                                        理由: <?php echo htmlspecialchars($student['absence_reason']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <!-- イベント参加者を表示 -->
                                        <?php if (!empty($events)): ?>
                                            <?php foreach ($events as $participant): ?>
                                                <div class="student-item" style="border-left: 4px solid #2563eb;">
                                                    <div class="student-item-name">
                                                        <?php echo htmlspecialchars($participant['student_name']); ?>
                                                        <span style="color: #2563eb; font-weight: bold; margin-left: 8px;">
                                                            <span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">event</span> <?= htmlspecialchars($participant['event_name']) ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($participant['guardian_name']): ?>
                                                        <div class="student-item-meta">
                                                            保護者: <?php echo htmlspecialchars($participant['guardian_name']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($participant['notes']): ?>
                                                        <div class="student-item-meta" style="color: #2563eb;">
                                                            備考: <?php echo htmlspecialchars($participant['notes']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- マイグレーション警告（管理者のみ） -->
            <?php if (!$hasMakeupColumn && $_SESSION['user_type'] === 'admin'): ?>
                <div class="main-content" style="margin-bottom: 20px;">
                    <div style="background: #4a4020; padding: 20px; border-radius: 12px; border-left: 4px solid #ffc107;">
                        <h3 style="color: #856404; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 1.2em;">warning</span> データベースマイグレーションが必要です</h3>
                        <p style="color: #856404; margin-bottom: 15px;">
                            振替依頼機能を使用するには、データベースのマイグレーションが必要です。
                        </p>
                        <a href="/admin/run_migration_v44.php" style="display: inline-block; background: #1976D2; color: var(--text-primary); padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                            マイグレーションを実行する →
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 選択された日付の活動セクション -->
            <div class="activity-section">
                <!-- 選択された日付の情報 -->
                <div class="date-info">
                    <span>記録日: <?php echo date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($selectedDate))] . '）', strtotime($selectedDate)); ?></span>
                </div>

                <?php if (!empty($activities)): ?>
                    <!-- 活動一覧（活動がある場合のみ表示） -->
                    <div class="activity-list">
                        <h2>この日の活動一覧</h2>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-card">
                                <div class="activity-header">
                                    <div class="activity-name"><?php echo htmlspecialchars($activity['activity_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="participant-count">参加者 <?php echo $activity['participant_count']; ?>名</div>
                                </div>

                                <div style="font-size: var(--text-subhead); color: var(--text-secondary); margin-bottom: var(--spacing-md);">
                                    作成者: <?php echo htmlspecialchars($activity['staff_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($activity['staff_id'] == $currentUser['id']): ?>
                                        <span style="color: var(--primary-purple); font-weight: bold;">(自分)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['support_plan_name'])): ?>
                                        <br>
                                        <span style="color: var(--primary-purple);"><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">edit_note</span> 支援案: <?php echo htmlspecialchars($activity['support_plan_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($activity['common_activity']): ?>
                                    <div class="activity-content">
                                        <?php echo nl2br(htmlspecialchars($activity['common_activity'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="activity-actions">
                                    <a href="renrakucho_form.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-edit">編集</a>
                                    <a href="regenerate_integration.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-integrate" onclick="return confirm('既存の統合内容（未送信）を削除して、1から統合し直しますか？');"><span class="material-symbols-outlined">sync</span> 統合する</a>
                                    <a href="integrate_activity.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-integrate-edit"><span class="material-symbols-outlined">edit</span> 統合内容を編集</a>
                                    <?php if ((int)$activity['sent_count'] > 0): ?>
                                        <a href="view_integrated.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-view"><span class="material-symbols-outlined">upload_file</span> 送信済み内容を閲覧</a>
                                    <?php endif; ?>
                                    <form method="POST" action="delete_activity.php" style="display: inline;" onsubmit="return confirm('この活動を削除しますか？');">
                                        <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                        <button type="submit" class="btn btn-delete">削除</button>
                                    </form>
                                    <span class="help-icon" onclick="toggleHelp(this, event)" style="flex-shrink: 0;">?
                                        <div class="help-tooltip" style="left: auto; right: 0; transform: none; width: 320px;">
                                            <strong>ボタンの説明</strong><br><br>
                                            <strong>編集</strong>：活動内容や参加者を編集します。<br><br>
                                            <strong><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">sync</span> 統合する</strong>：新しい活動を登録すると、その内容に従って、AIが参加者ごとの連絡帳を自動生成します。統合にはしばらく時間がかかりますので完了までお待ちください。<br><br>
                                            <strong><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">edit</span> 統合内容を編集</strong>：統合するボタンで生成された連絡帳の内容を確認できます。確認後、途中保存もしくは連絡帳を送信ボタンを押してください。<br><br>
                                            <strong><span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">upload_file</span> 送信済み内容を閲覧</strong>：保護者へ送信済みの内容を確認します。<br><br>
                                            <strong>削除</strong>：この活動を削除します。
                                        </div>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- 活動がない場合のコンパクトメッセージ -->
                    <p class="no-activity-message">この日の活動は登録されていません。</p>
                <?php endif; ?>

                <!-- ボタン -->
                <div class="activity-buttons">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <button type="button" class="add-activity-btn" onclick="location.href='renrakucho.php?date=<?php echo urlencode($selectedDate); ?>'">
                            新しい活動を追加
                        </button>
                        <span class="help-icon" onclick="toggleHelp(this, event)" style="flex-shrink: 0;">?
                            <div class="help-tooltip" style="left: auto; right: 0; transform: none;">選択した日付の連絡帳を新規作成します。活動内容や生徒の様子を記録できます。</div>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <button type="button" class="add-activity-btn" style="background: var(--primary-purple);" onclick="location.href='support_plans.php'">
                            <span class="material-symbols-outlined">edit_note</span> 支援案を管理
                        </button>
                        <span class="help-icon" onclick="toggleHelp(this, event)" style="flex-shrink: 0;">?
                            <div class="help-tooltip" style="left: auto; right: 0; transform: none;">よく使う活動内容のテンプレートを管理します。支援案を登録しておくと、連絡帳作成時に素早く入力できます。</div>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- イベント詳細モーダル -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeEventModal()">&times;</button>
            <div class="modal-header">
                <h2 id="eventModalTitle">イベント詳細</h2>
            </div>
            <div id="eventModalContent">
                <!-- イベントの内容がここに表示されます -->
            </div>
        </div>
    </div>

    <script>
        // ページ読み込み時にスクロール位置を復元
        (function() {
            const savedScroll = sessionStorage.getItem('calendarScrollPos');
            if (savedScroll) {
                sessionStorage.removeItem('calendarScrollPos');
                setTimeout(() => {
                    window.scrollTo(0, parseInt(savedScroll, 10));
                }, 0);
            }
        })();

        // カレンダー日付クリック時（スクロール位置を保存して遷移）
        function navigateToDate(year, month, date) {
            sessionStorage.setItem('calendarScrollPos', window.scrollY);
            location.href = '?year=' + year + '&month=' + month + '&date=' + date;
        }

        // アコーディオン切り替え
        function toggleAccordion(element) {
            const content = element.nextElementSibling;
            const arrow = element.querySelector('.accordion-arrow');

            if (content.classList.contains('active')) {
                content.classList.remove('active');
                arrow.textContent = '▼';
            } else {
                content.classList.add('active');
                arrow.textContent = '▲';
            }
        }

        // 通知セクションのアコーディオン切り替え
        function toggleNotificationSection(header) {
            const content = header.nextElementSibling;
            const toggle = header.querySelector('.notification-section-toggle');

            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                toggle.classList.remove('collapsed');
            } else {
                content.classList.add('collapsed');
                toggle.classList.add('collapsed');
            }
        }

        // ヘルプアイコンのツールチップ切り替え
        function toggleHelp(element, event) {
            event.stopPropagation();

            // 他のヘルプアイコンを閉じる
            document.querySelectorAll('.help-icon.active').forEach(icon => {
                if (icon !== element) {
                    icon.classList.remove('active');
                }
            });

            // クリックされたアイコンをトグル
            element.classList.toggle('active');
        }

        // ページ全体クリックでヘルプを閉じる
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.help-icon')) {
                document.querySelectorAll('.help-icon.active').forEach(icon => {
                    icon.classList.remove('active');
                });
            }
        });

        // ハンバーガーメニュー
        const hamburger = document.getElementById('hamburger');
        if (hamburger) {
            hamburger.addEventListener('click', function() {
                this.classList.toggle('active');
                document.getElementById('userInfo').classList.toggle('mobile-active');
            });
        }

        // イベント詳細モーダルを表示（スタッフ用）
        function showEventModal(eventData) {
            const targetAudienceLabels = {
                'all': '全体',
                'elementary': '小学生',
                'junior_high_school': '中高生',
                'guardian': '保護者',
                'other': 'その他'
            };

            document.getElementById('eventModalTitle').textContent = eventData.name || 'イベント詳細';

            let html = '';

            // 説明
            if (eventData.description) {
                html += '<div class="event-detail-section">';
                html += '<h4>説明</h4>';
                html += '<p>' + escapeHtml(eventData.description) + '</p>';
                html += '</div>';
            }

            // 保護者・生徒連絡用
            if (eventData.guardian_message) {
                html += '<div class="event-detail-section">';
                html += '<h4>保護者・生徒連絡用</h4>';
                html += '<p>' + escapeHtml(eventData.guardian_message) + '</p>';
                html += '</div>';
            }

            // 対象者
            if (eventData.target_audience) {
                html += '<div class="event-detail-section">';
                html += '<h4>対象者</h4>';
                html += '<p>' + (targetAudienceLabels[eventData.target_audience] || '全体') + '</p>';
                html += '</div>';
            }

            // スタッフ向けコメント（スタッフのみ表示）
            if (eventData.staff_comment) {
                html += '<div class="event-detail-section staff-only">';
                html += '<h4><span class=\"material-symbols-outlined\" style=\"font-size: 1em; vertical-align: middle;\">edit_note</span> スタッフ向けコメント</h4>';
                html += '<p>' + escapeHtml(eventData.staff_comment) + '</p>';
                html += '</div>';
            }

            if (html === '') {
                html = '<div class="no-data">詳細情報はありません</div>';
            }

            document.getElementById('eventModalContent').innerHTML = html;
            document.getElementById('eventModal').classList.add('active');
        }

        // イベントモーダルを閉じる
        function closeEventModal() {
            document.getElementById('eventModal').classList.remove('active');
        }

        // モーダル外クリックで閉じる
        const eventModal = document.getElementById('eventModal');
        if (eventModal) {
            eventModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEventModal();
                }
            });
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>

<?php renderPageEnd(); ?>
