<?php
/**
 * 活動管理ページ（カレンダー表示対応）
 */

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/student_helper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
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
        ORDER BY dr.record_date
    ");
    $stmt->execute([$classroomId, $year, $month]);
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(record_date) as date
        FROM daily_records
        WHERE YEAR(record_date) = ?
        AND MONTH(record_date) = ?
        ORDER BY record_date
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

// 1. 保護者からの新しいメッセージ（未読）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.created_at, cm.message, s.student_name, u.full_name as guardian_name, cr.id as room_id
        FROM chat_messages cm
        INNER JOIN chat_rooms cr ON cm.room_id = cr.id
        INNER JOIN students s ON cr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE cm.sender_type = 'guardian'
        AND cm.is_read = 0
        AND cm.created_at >= ?
        AND u.classroom_id = ?
        ORDER BY cm.created_at DESC
    ");
    $stmt->execute([$threeDaysAgo, $classroomId]);
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

    // 学部別に分類
    $studentsByGrade = [
        'elementary' => [],
        'junior_high' => [],
        'high_school' => []
    ];

    foreach ($scheduledStudents as $student) {
        // 学年を再計算（学年調整を考慮）
        $gradeLevel = $student['birth_date']
            ? calculateGradeLevel($student['birth_date'], null, $student['grade_adjustment'] ?? 0)
            : ($student['grade_level'] ?? 'elementary');
        if (isset($studentsByGrade[$gradeLevel])) {
            $studentsByGrade[$gradeLevel][] = $student;
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
        'elementary' => [],
        'junior_high' => [],
        'high_school' => []
    ];

    foreach ($eventParticipants as $participant) {
        // 学年を再計算（学年調整を考慮）
        $gradeLevel = $participant['birth_date']
            ? calculateGradeLevel($participant['birth_date'], null, $participant['grade_adjustment'] ?? 0)
            : ($participant['grade_level'] ?? 'elementary');
        if (isset($eventsByGrade[$gradeLevel])) {
            $eventsByGrade[$gradeLevel][] = $participant;
        }
    }
}

// 個別支援計画書が未作成または古い生徒の数を取得（自分の教室のみ）
$planNeedingCount = 0;

if ($classroomId) {
    // 個別支援計画書が1つも作成されていない生徒
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM individual_support_plans isp
            WHERE isp.student_id = s.id
        )
    ");
    $stmt->execute([$classroomId]);
    $planNeedingCount += (int)$stmt->fetchColumn();

    // 最新の個別支援計画書から6ヶ月以上経過している生徒
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(isp.created_date)) >= 180
    ");
    $stmt->execute([$classroomId]);
    $result = $stmt->fetchAll();
    $planNeedingCount += count($result);
} else {
    // 個別支援計画書が1つも作成されていない生徒
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM students s
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM individual_support_plans isp
            WHERE isp.student_id = s.id
        )
    ");
    $planNeedingCount += (int)$stmt->fetchColumn();

    // 最新の個別支援計画書から6ヶ月以上経過している生徒
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(isp.created_date)) >= 180
    ");
    $result = $stmt->fetchAll();
    $planNeedingCount += count($result);
}

// モニタリングが未作成または古い生徒の数を取得（自分の教室のみ）
$monitoringNeedingCount = 0;

if ($classroomId) {
    // モニタリングが1つも作成されていない生徒（個別支援計画書がある生徒のみ）
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id
        )
    ");
    $stmt->execute([$classroomId]);
    $monitoringNeedingCount += (int)$stmt->fetchColumn();

    // 最新のモニタリングから3ヶ月以上経過している生徒
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) >= 90
    ");
    $stmt->execute([$classroomId]);
    $result = $stmt->fetchAll();
    $monitoringNeedingCount += count($result);
} else {
    // モニタリングが1つも作成されていない生徒（個別支援計画書がある生徒のみ）
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id
        )
    ");
    $monitoringNeedingCount += (int)$stmt->fetchColumn();

    // 最新のモニタリングから3ヶ月以上経過している生徒
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        INNER JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) >= 90
    ");
    $result = $stmt->fetchAll();
    $monitoringNeedingCount += count($result);
}

// かけはし通知データを取得
$today = date('Y-m-d');

// 1. 未提出の保護者かけはし（期限切れも含む、非表示を除外）の件数を取得（自分の教室のみ）
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
        ");
        $stmt->execute([$classroomId]);
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    }
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
        ");
        $stmt->execute();
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしでカウント
        error_log("Guardian kakehashi count error: " . $e->getMessage());
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
        ");
        $stmt->execute();
        $guardianKakehashiCount = (int)$stmt->fetchColumn();
    }
}

// 2. 未作成のスタッフかけはし（期限切れも含む、非表示を除外）の件数を取得（自分の教室のみ）
$staffKakehashiCount = 0;
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            AND COALESCE(ks.is_hidden, 0) = 0
        ");
        $stmt->execute([$classroomId]);
        $staffKakehashiCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしでカウント
        error_log("Staff kakehashi count error: " . $e->getMessage());
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
        ");
        $stmt->execute([$classroomId]);
        $staffKakehashiCount = (int)$stmt->fetchColumn();
    }
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            AND COALESCE(ks.is_hidden, 0) = 0
        ");
        $stmt->execute();
        $staffKakehashiCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしでカウント
        error_log("Staff kakehashi count error: " . $e->getMessage());
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
        ");
        $stmt->execute();
        $staffKakehashiCount = (int)$stmt->fetchColumn();
    }
}

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

// 未作成・未提出タスクの詳細データを取得
// 個別支援計画書の期限別分類
$overduePlans = [];
$urgentPlans = [];
$pendingPlans = [];
$notCreatedPlans = [];

// モニタリング表の期限別分類
$overdueMonitoring = [];
$urgentMonitoring = [];
$pendingMonitoring = [];
$notCreatedMonitoring = [];

// 期限切れ（期限が過ぎているもの）
$overdueGuardianKakehashi = [];
$overdueStaffKakehashi = [];
$overdueSubmissionRequests = [];

// 期限内1か月以内（残り30日以内）
$urgentGuardianKakehashi = [];
$urgentStaffKakehashi = [];
$urgentSubmissionRequests = [];

// 期限内1か月以上（残り31日以上）
$pendingGuardianKakehashi = [];
$pendingStaffKakehashi = [];
$pendingSubmissionRequests = [];

if ($classroomId) {
    // 個別支援計画書の期限別取得
    // 1. 未作成の生徒（studentsテーブルのclassroom_idを直接使用）
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        WHERE s.is_active = 1 AND s.classroom_id = ?
        AND s.id NOT IN (SELECT student_id FROM individual_support_plans)
    ");
    $stmt->execute([$classroomId]);
    $notCreatedPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 作成済みで期限別分類（最新の計画から6ヶ月以上経過を基準）
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            MAX(isp.created_date) as latest_plan_date,
            DATEDIFF(CURDATE(), MAX(isp.created_date)) as days_since_created
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND s.classroom_id = ?
        GROUP BY s.id, s.student_name
        HAVING days_since_created >= 180
    ");
    $stmt->execute([$classroomId]);
    $oldPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // デバッグ
    error_log("Old plans count (>=180 days): " . count($oldPlans));

    foreach ($oldPlans as $plan) {
        $daysSince = $plan['days_since_created'];
        if ($daysSince >= 365) { // 1年以上（期限切れ）
            $overduePlans[] = $plan;
        } elseif ($daysSince >= 330) { // 11ヶ月以上（1か月以内）
            $urgentPlans[] = $plan;
        } else { // 6ヶ月～11ヶ月（1か月以上）
            $pendingPlans[] = $plan;
        }
    }

    // デバッグ：各カテゴリの件数
    error_log("Plan counts - Not created: " . count($notCreatedPlans) . ", Overdue: " . count($overduePlans) . ", Urgent: " . count($urgentPlans) . ", Pending: " . count($pendingPlans));

    // モニタリング表の期限別取得
    // 1. モニタリングが1つも作成されていない生徒（個別支援計画書がある生徒のみ）
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND s.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id
        )
    ");
    $stmt->execute([$classroomId]);
    $notCreatedMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 最新のモニタリングから期限別分類（3ヶ月以上経過を基準）
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            MAX(mr.monitoring_date) as latest_monitoring_date,
            DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) as days_since_monitoring
        FROM students s
        INNER JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1 AND s.classroom_id = ?
        GROUP BY s.id, s.student_name
        HAVING days_since_monitoring >= 90
    ");
    $stmt->execute([$classroomId]);
    $oldMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldMonitoring as $monitoring) {
        $daysSince = $monitoring['days_since_monitoring'];
        if ($daysSince >= 180) { // 6ヶ月以上（期限切れ）
            $overdueMonitoring[] = $monitoring;
        } elseif ($daysSince >= 150) { // 5ヶ月以上（1か月以内）
            $urgentMonitoring[] = $monitoring;
        } else { // 3ヶ月～5ヶ月（1か月以上）
            $pendingMonitoring[] = $monitoring;
        }
    }

    // 保護者かけはし未提出
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
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1 AND s.classroom_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC
        ");
        $stmt->execute([$classroomId]);
        $allGuardianKakehashi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allGuardianKakehashi as $item) {
            if ($item['days_left'] < 0) {
                $overdueGuardianKakehashi[] = $item;
            } elseif ($item['days_left'] <= 30) {
                $urgentGuardianKakehashi[] = $item;
            } else {
                $pendingGuardianKakehashi[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
    }

    // スタッフかけはし未作成
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
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1 AND s.classroom_id = ?
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            AND COALESCE(ks.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC
        ");
        $stmt->execute([$classroomId]);
        $allStaffKakehashi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allStaffKakehashi as $item) {
            if ($item['days_left'] < 0) {
                $overdueStaffKakehashi[] = $item;
            } elseif ($item['days_left'] <= 30) {
                $urgentStaffKakehashi[] = $item;
            } else {
                $pendingStaffKakehashi[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Staff kakehashi fetch error: " . $e->getMessage());
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
        WHERE s.classroom_id = ?
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
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        WHERE s.is_active = 1
        AND s.id NOT IN (SELECT student_id FROM individual_support_plans)
    ");
    $notCreatedPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            s.id,
            s.student_name,
            MAX(isp.created_date) as latest_plan_date,
            DATEDIFF(CURDATE(), MAX(isp.created_date)) as days_since_created
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        GROUP BY s.id, s.student_name
        HAVING days_since_created >= 180
    ");
    $oldPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldPlans as $plan) {
        $daysSince = $plan['days_since_created'];
        if ($daysSince >= 365) {
            $overduePlans[] = $plan;
        } elseif ($daysSince >= 330) {
            $urgentPlans[] = $plan;
        } else {
            $pendingPlans[] = $plan;
        }
    }

    // モニタリング表の期限別取得
    $stmt = $pdo->query("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id
        )
    ");
    $notCreatedMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            s.id,
            s.student_name,
            MAX(mr.monitoring_date) as latest_monitoring_date,
            DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) as days_since_monitoring
        FROM students s
        INNER JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1
        GROUP BY s.id, s.student_name
        HAVING days_since_monitoring >= 90
    ");
    $oldMonitoring = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldMonitoring as $monitoring) {
        $daysSince = $monitoring['days_since_monitoring'];
        if ($daysSince >= 180) {
            $overdueMonitoring[] = $monitoring;
        } elseif ($daysSince >= 150) {
            $urgentMonitoring[] = $monitoring;
        } else {
            $pendingMonitoring[] = $monitoring;
        }
    }

    // 保護者かけはし未提出
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
            ORDER BY kp.submission_deadline ASC
        ");
        $allGuardianKakehashi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allGuardianKakehashi as $item) {
            if ($item['days_left'] < 0) {
                $overdueGuardianKakehashi[] = $item;
            } elseif ($item['days_left'] <= 30) {
                $urgentGuardianKakehashi[] = $item;
            } else {
                $pendingGuardianKakehashi[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
    }

    // スタッフかけはし未作成
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
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            AND COALESCE(ks.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC
        ");
        $allStaffKakehashi = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allStaffKakehashi as $item) {
            if ($item['days_left'] < 0) {
                $overdueStaffKakehashi[] = $item;
            } elseif ($item['days_left'] <= 30) {
                $urgentStaffKakehashi[] = $item;
            } else {
                $pendingStaffKakehashi[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Staff kakehashi fetch error: " . $e->getMessage());
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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>活動管理 - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: var(--apple-bg-secondary);
            padding: var(--spacing-lg);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

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

        .header {
            background: var(--apple-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            display: block;
            width: 100%;
        }

        .header h1 {
            color: var(--text-primary);
            font-size: var(--text-title-2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            padding: var(--spacing-sm) 16px;
            background: var(--apple-red);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
        }

        .notifications-container {
            background: var(--apple-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .notification-item {
            padding: 15px;
            border-left: 4px solid var(--primary-purple);
            background: var(--apple-bg-secondary);
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
            border-left-color: var(--apple-green);
        }

        .notification-item.kakehashi {
            border-left-color: var(--apple-orange);
        }

        .notification-item.monitoring {
            border-left-color: var(--apple-teal);
        }

        .notification-item.plan {
            border-left-color: #6f42c1;
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

        /* タスクサマリー用スタイル */
        .task-summary-item {
            background: var(--apple-bg-primary);
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
            color: var(--apple-blue);
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
            color: var(--apple-red);
        }

        .task-count.urgent {
            background: rgba(255, 149, 0, 0.15);
            color: var(--apple-orange);
        }

        .task-count.warning {
            background: rgba(255, 204, 0, 0.15);
            color: var(--apple-yellow);
        }

        .task-summary-link {
            margin-left: auto;
            padding: 6px 16px;
            background: var(--apple-blue);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-weight: 600;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .task-summary-link:hover {
            background: var(--apple-blue);
            opacity: 0.8;
            transform: translateY(-1px);
        }

        .calendar-container {
            background: var(--apple-bg-secondary);
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
            border: 1px solid var(--apple-gray-5);
            border-radius: 3px;
            padding: 3px;
            cursor: pointer;
            background: var(--apple-bg-secondary);
            position: relative;
            transition: all 0.15s;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 50px;
        }

        .calendar-day:hover {
            background: var(--apple-bg-tertiary);
            transform: scale(1.05);
        }

        .calendar-day.empty {
            background: var(--apple-gray-6); opacity: 0.5;
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
            color: #ff3b30;
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
            background: var(--apple-bg-secondary);
            border-radius: 50%;
        }

        .date-info {
            background: var(--apple-bg-secondary);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 18px;
            color: var(--text-primary);
        }

        .activity-list {
            background: var(--apple-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
            background: var(--apple-bg-secondary);
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
            color: #1d1d1f;
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
            color: #1d1d1f;
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--apple-bg-secondary);
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

        .btn-edit {
            background: var(--apple-blue);
            color: var(--text-primary);
        }

        .btn-delete {
            background: var(--apple-red);
            color: var(--text-primary);
        }

        .btn-integrate {
            background: var(--apple-bg-secondary);
            color: var(--text-primary);
        }

        .btn-view {
            background: var(--apple-green);
            color: var(--text-primary);
        }

        .add-activity-btn {
            padding: 15px 30px;
            background: var(--apple-green);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }

        .add-activity-btn:hover {
            background: var(--apple-green);
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
            border-left: 4px solid var(--apple-green);
        }

        .error-message {
            background: var(--apple-bg-secondary);
            color: #721c24;
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--apple-red);
        }

        .sunday {
            color: var(--apple-red);
        }

        .saturday {
            color: var(--apple-blue);
        }

        .scheduled-students-box {
            background: var(--apple-bg-secondary);
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
            background: var(--apple-gray-6);
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
            color: var(--apple-red);
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
            background: var(--apple-bg-secondary);
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
            background: var(--apple-gray-5);
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
            background: var(--apple-gray-6);
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
            background: var(--apple-bg-secondary);
            padding: var(--spacing-lg) 25px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }

        .notification-banner.urgent {
            border-left: 5px solid var(--apple-red);
            background: #3a2020;
        }

        .notification-banner.warning {
            border-left: 5px solid var(--apple-orange);
            background: #3a3820;
        }

        .notification-banner.info {
            border-left: 5px solid #17a2b8;
            background: var(--apple-bg-secondary);
        }

        .notification-banner.overdue {
            border-left: 5px solid var(--apple-gray);
            background: var(--apple-gray-6);
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
            color: var(--apple-red);
        }

        .notification-header.warning {
            color: #ff9800;
        }

        .notification-header.overdue {
            color: var(--apple-gray);
        }

        .notification-header.info {
            color: var(--apple-teal);
        }

        .notification-item {
            background: var(--apple-bg-secondary);
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--apple-gray-5);
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
            color: var(--apple-red);
        }

        .notification-deadline.warning {
            color: #ff9800;
        }

        .notification-deadline.overdue {
            color: var(--apple-gray);
        }

        .notification-deadline.info {
            color: var(--apple-teal);
        }

        .task-summary-box {
            background: var(--apple-bg-secondary);
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
            background: var(--apple-gray-6);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-purple);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-card.has-tasks {
            border-left-color: var(--apple-red);
            background: rgba(255, 69, 58, 0.1);
        }

        .task-card.has-warnings {
            border-left-color: var(--apple-orange);
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
            color: var(--apple-red);
        }

        .task-card-count.warning {
            color: #ff9800;
        }

        .task-card-count.success {
            color: var(--apple-green);
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

        /* ドロップダウンメニュー */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: var(--spacing-sm) 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .dropdown-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .dropdown-toggle.master {
            background: linear-gradient(135deg, #34c759 0%, #20c997 100%);
        }

        .dropdown-toggle.master:hover {
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.4);
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .dropdown.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--apple-bg-secondary);
            border-radius: var(--radius-sm);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 200px;
            margin-top: 5px;
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown.open .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: var(--spacing-md) 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: var(--apple-gray-6);
        }

        .dropdown-menu a .menu-icon {
            margin-right: 8px;
        }

        /* ハンバーガーメニュー */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: var(--spacing-sm);
            background: var(--primary-purple);
            border-radius: var(--radius-sm);
            border: none;
        }

        .hamburger span {
            width: 24px;
            height: 3px;
            background: var(--apple-bg-secondary);
            border-radius: 2px;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* デスクトップ用レイアウト（デフォルト） */
        @media (min-width: 769px) {
            .hamburger {
                display: none !important;
            }

            .header {
                width: 100% !important;
            }

            .header-content {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
            }

            .user-info {
                display: flex !important;
                position: static !important;
                flex-direction: row !important;
            }

            .two-column-layout {
                grid-template-columns: 600px 1fr !important;
            }
        }

        /* レスポンシブデザイン */
        @media (max-width: 768px) {
            .two-column-layout {
                grid-template-columns: 1fr;
            }
            body {
                padding: var(--spacing-md);
            }

            .header {
                padding: 15px;
            }

            .header h1 {
                font-size: 18px;
            }

            .hamburger {
                display: flex;
            }

            .user-info {
                display: none;
                position: fixed;
                top: 60px;
                right: 10px;
                flex-direction: column;
                background: var(--apple-bg-secondary);
                padding: 15px;
                border-radius: var(--radius-sm);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                gap: 10px;
            }

            .user-info.show {
                display: flex;
            }

            .dropdown-toggle {
                width: 100%;
                justify-content: center;
            }

            .logout-btn {
                width: 100%;
                text-align: center;
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
            .header h1 {
                font-size: var(--text-callout);
            }

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
            background: var(--apple-bg-secondary);
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

        .no-data {
            text-align: center;
            padding: var(--spacing-lg);
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <button class="hamburger" id="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <?php if ($classroom && !empty($classroom['logo_path']) && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
                            <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ" style="height: 50px; width: auto;">
                        <?php else: ?>
                            <div style="font-size: 40px;">📋</div>
                        <?php endif; ?>
                        <div>
                            <h1>活動管理</h1>
                            <?php if ($classroom): ?>
                                <div style="font-size: var(--text-subhead); color: var(--text-secondary); margin-top: 5px;">
                                    <?= htmlspecialchars($classroom['classroom_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="user-info" id="userInfo">
                <span><?php echo htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); ?>さん</span>

                <!-- 保護者ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        👨‍👩‍👧 保護者
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="chat.php">
                            <span class="menu-icon">💬</span>保護者チャット
                        </a>
                        <a href="makeup_requests.php">
                            <span class="menu-icon">🔄</span>振替依頼管理
                        </a>
                        <a href="submission_management.php">
                            <span class="menu-icon">📮</span>提出期限管理
                        </a>
                    </div>
                </div>

                <!-- 生徒ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🎓 生徒
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="student_chats.php">
                            <span class="menu-icon">💬</span>生徒チャット
                        </a>
                        <a href="student_weekly_plans.php">
                            <span class="menu-icon">📝</span>週間計画表
                        </a>
                        <a href="student_submissions.php">
                            <span class="menu-icon">📋</span>提出物一覧
                        </a>
                    </div>
                </div>

                <!-- かけはし管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🌉 かけはし管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="kakehashi_staff.php">
                            <span class="menu-icon">✏️</span>スタッフかけはし入力
                        </a>
                        <a href="kakehashi_guardian_view.php">
                            <span class="menu-icon">📋</span>保護者かけはし確認
                        </a>
                        <a href="kobetsu_plan.php">
                            <span class="menu-icon">📄</span>個別支援計画書作成
                        </a>
                        <a href="kobetsu_monitoring.php">
                            <span class="menu-icon">📊</span>モニタリング表作成
                        </a>
                        <a href="newsletter_create.php">
                            <span class="menu-icon">📰</span>施設通信を作成
                        </a>
                    </div>
                </div>

                <!-- マスタ管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle master" onclick="toggleDropdown(event, this)">
                        ⚙️ マスタ管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="students.php">
                            <span class="menu-icon">👥</span>生徒管理
                        </a>
                        <a href="guardians.php">
                            <span class="menu-icon">👨‍👩‍👧</span>保護者管理
                        </a>
                        <a href="holidays.php">
                            <span class="menu-icon">🗓️</span>休日管理
                        </a>
                        <a href="events.php">
                            <span class="menu-icon">🎉</span>イベント管理
                        </a>
                    </div>
                </div>

                <!-- アカウント設定ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        👤 アカウント
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php">
                            <span class="menu-icon">👤</span>プロフィール編集
                        </a>
                        <a href="/logout.php">
                            <span class="menu-icon">🚪</span>ログアウト
                        </a>
                    </div>
                </div>

                <!-- マニュアルボタン -->
                <a href="manual.php" class="manual-btn" style="background: linear-gradient(135deg, var(--apple-green) 0%, #20c997 100%); color: var(--text-primary); padding: var(--spacing-md) 20px; border-radius: var(--radius-sm); text-decoration: none; font-weight: 600; transition: all var(--duration-normal) var(--ease-out);">
                    📖 マニュアル
                </a>
            </div>
        </div>

        <script>
        function toggleDropdown(event, button) {
            event.stopPropagation();
            const dropdown = button.closest('.dropdown');
            const isOpen = dropdown.classList.contains('open');

            // 他のドロップダウンを閉じる
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });

            // このドロップダウンをトグル
            if (!isOpen) {
                dropdown.classList.add('open');
            }
        }

        // ドロップダウン外をクリックしたら閉じる
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });
        });

        // ドロップダウン内のクリックで伝播を止める
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });

        // ハンバーガーメニューの開閉
        const hamburger = document.getElementById('hamburger');
        const userInfo = document.getElementById('userInfo');

        function toggleMenu() {
            hamburger.classList.toggle('active');
            userInfo.classList.toggle('show');
        }

        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });

        // メニュー外をクリックしたら閉じる
        document.addEventListener('click', function(e) {
            if (!userInfo.contains(e.target) && !hamburger.contains(e.target)) {
                hamburger.classList.remove('active');
                userInfo.classList.remove('show');
            }
        });

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

        <!-- 旧バージョンのコメントアウト -->
        <?php if (false && !empty($studentsWithoutPlan)): ?>
            <div class="notification-banner urgent">
                <div class="notification-header urgent">
                    ⚠️ 【重要】個別支援計画書が未作成の生徒がいます
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
        // 各タスクの合計件数を計算
        $totalPlanNeeding = count($notCreatedPlans) + count($overduePlans) + count($urgentPlans) + count($pendingPlans);
        $totalMonitoringNeeding = count($notCreatedMonitoring) + count($overdueMonitoring) + count($urgentMonitoring) + count($pendingMonitoring);
        $totalGuardianKakehashi = count($overdueGuardianKakehashi) + count($urgentGuardianKakehashi) + count($pendingGuardianKakehashi);
        $totalStaffKakehashi = count($overdueStaffKakehashi) + count($urgentStaffKakehashi) + count($pendingStaffKakehashi);
        $totalSubmissionRequests = count($overdueSubmissionRequests) + count($urgentSubmissionRequests) + count($pendingSubmissionRequests);

        // いずれかのタスクが存在する場合のみセクションを表示
        if ($totalPlanNeeding > 0 || $totalMonitoringNeeding > 0 || $totalGuardianKakehashi > 0 || $totalStaffKakehashi > 0 || $totalSubmissionRequests > 0):
        ?>
        <div class="notifications-container">
            <h2 style="margin-bottom: 20px; color: var(--text-primary); font-size: var(--text-title-3); font-weight: 600;">📋 未作成・未提出タスク</h2>

            <!-- 個別支援計画書 -->
            <?php if ($totalPlanNeeding > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title">📄 個別支援計画書</span>
                        <span class="task-summary-total"><?php echo $totalPlanNeeding; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if (count($notCreatedPlans) > 0): ?>
                            <span class="task-count overdue">未作成 <?php echo count($notCreatedPlans); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($overduePlans) > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo count($overduePlans); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($urgentPlans) > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo count($urgentPlans); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($pendingPlans) > 0): ?>
                            <span class="task-count warning">1か月以上 <?php echo count($pendingPlans); ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- モニタリング表 -->
            <?php if ($totalMonitoringNeeding > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title">📊 モニタリング表</span>
                        <span class="task-summary-total"><?php echo $totalMonitoringNeeding; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if (count($notCreatedMonitoring) > 0): ?>
                            <span class="task-count overdue">未作成 <?php echo count($notCreatedMonitoring); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($overdueMonitoring) > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo count($overdueMonitoring); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($urgentMonitoring) > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo count($urgentMonitoring); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($pendingMonitoring) > 0): ?>
                            <span class="task-count warning">1か月以上 <?php echo count($pendingMonitoring); ?>件</span>
                        <?php endif; ?>
                        <a href="pending_tasks.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 保護者かけはし -->
            <?php if ($totalGuardianKakehashi > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title">📝 保護者かけはし未提出</span>
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
                        <a href="kakehashi_periods.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- スタッフかけはし -->
            <?php if ($totalStaffKakehashi > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title">📝 スタッフかけはし未作成</span>
                        <span class="task-summary-total"><?php echo $totalStaffKakehashi; ?>件</span>
                    </div>
                    <div class="task-summary-details">
                        <?php if (count($overdueStaffKakehashi) > 0): ?>
                            <span class="task-count overdue">期限切れ <?php echo count($overdueStaffKakehashi); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($urgentStaffKakehashi) > 0): ?>
                            <span class="task-count urgent">1か月以内 <?php echo count($urgentStaffKakehashi); ?>件</span>
                        <?php endif; ?>
                        <?php if (count($pendingStaffKakehashi) > 0): ?>
                            <span class="task-count warning">1か月以上 <?php echo count($pendingStaffKakehashi); ?>件</span>
                        <?php endif; ?>
                        <a href="kakehashi_periods.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 提出期限 -->
            <?php if ($totalSubmissionRequests > 0): ?>
                <div class="task-summary-item">
                    <div class="task-summary-header">
                        <span class="task-summary-title">📤 提出期限未提出</span>
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
                        <a href="submission_management.php" class="task-summary-link">詳細を確認</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 通知セクション -->
        <?php if (!empty($newMessages) || !empty($newKakehashi) || !empty($confirmedMonitoring) || !empty($confirmedPlans)): ?>
        <div class="notifications-container">
            <h2 style="margin-bottom: 15px; color: var(--text-primary); font-size: 18px;">📢 お知らせ（過去3日以内）</h2>

            <!-- 保護者からの新しいメッセージ -->
            <?php if (!empty($newMessages)): ?>
                <?php foreach ($newMessages as $msg): ?>
                <div class="notification-item message">
                    <div class="notification-content">
                        <div class="notification-title">💬 保護者からの新しいメッセージ</div>
                        <div class="notification-meta">
                            <?php echo htmlspecialchars($msg['student_name'], ENT_QUOTES, 'UTF-8'); ?>の保護者（<?php echo htmlspecialchars($msg['guardian_name'], ENT_QUOTES, 'UTF-8'); ?>）
                            - <?php echo date('Y年m月d日 H:i', strtotime($msg['created_at'])); ?>
                        </div>
                    </div>
                    <a href="chat.php?room_id=<?php echo $msg['room_id']; ?>" class="notification-link">チャットを開く</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 新しいかけはし -->
            <?php if (!empty($newKakehashi)): ?>
                <?php foreach ($newKakehashi as $kakehashi): ?>
                <div class="notification-item kakehashi">
                    <div class="notification-content">
                        <div class="notification-title">📋 新しいかけはしが作成されました</div>
                        <div class="notification-meta">
                            <?php echo htmlspecialchars($kakehashi['student_name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($kakehashi['period_name'], ENT_QUOTES, 'UTF-8'); ?>
                            （作成: <?php echo htmlspecialchars($kakehashi['staff_name'], ENT_QUOTES, 'UTF-8'); ?>）
                            - <?php echo date('Y年m月d日 H:i', strtotime($kakehashi['created_at'])); ?>
                        </div>
                    </div>
                    <a href="kakehashi_staff_generate.php" class="notification-link">かけはしを確認</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- モニタリング表の保護者確認 -->
            <?php if (!empty($confirmedMonitoring)): ?>
                <?php foreach ($confirmedMonitoring as $monitoring): ?>
                <div class="notification-item monitoring">
                    <div class="notification-content">
                        <div class="notification-title">✅ モニタリング表が保護者に確認されました</div>
                        <div class="notification-meta">
                            <?php echo htmlspecialchars($monitoring['student_name'], ENT_QUOTES, 'UTF-8'); ?> - モニタリング実施日: <?php echo date('Y年m月d日', strtotime($monitoring['monitoring_date'])); ?>
                            - 確認日時: <?php echo date('Y年m月d日 H:i', strtotime($monitoring['guardian_confirmed_at'])); ?>
                        </div>
                    </div>
                    <a href="students.php" class="notification-link">生徒一覧を確認</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 個別支援計画の保護者確認 -->
            <?php if (!empty($confirmedPlans)): ?>
                <?php foreach ($confirmedPlans as $plan): ?>
                <div class="notification-item plan">
                    <div class="notification-content">
                        <div class="notification-title">✅ 個別支援計画が保護者に確認されました</div>
                        <div class="notification-meta">
                            <?php echo htmlspecialchars($plan['student_name'], ENT_QUOTES, 'UTF-8'); ?> - 作成日: <?php echo date('Y年m月d日', strtotime($plan['created_date'])); ?>
                            - 確認日時: <?php echo date('Y年m月d日 H:i', strtotime($plan['guardian_confirmed_at'])); ?>
                        </div>
                    </div>
                    <a href="students.php" class="notification-link">生徒一覧を確認</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

                            echo "<div class='" . implode(' ', $classes) . "' onclick=\"location.href='?year=$year&month=$month&date=$currentDate'\">";
                            echo "<div class='calendar-day-number $dayClass'>$day</div>";
                            echo "<div class='calendar-day-content'>";

                            // 休日を表示
                            if (isset($holidayDates[$currentDate])) {
                                echo "<div class='holiday-label'>" . htmlspecialchars($holidayDates[$currentDate]['name']) . "</div>";
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
                <div class="scheduled-students-box">
                    <h3>📋 本日の参加予定者</h3>
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
                            'elementary' => ['label' => '小学生', 'icon' => '🎒'],
                            'junior_high' => ['label' => '中学生', 'icon' => '📚'],
                            'high_school' => ['label' => '高校生', 'icon' => '🎓']
                        ];

                        foreach ($gradeInfo as $gradeKey => $info):
                            $students = $studentsByGrade[$gradeKey];
                            $events = $eventsByGrade[$gradeKey];
                            $totalCount = count($students) + count($events);

                            if ($totalCount === 0) continue;
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
                                                        <span style="color: var(--apple-red); font-weight: bold; margin-left: 8px;">🚫 欠席</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($student['guardian_name']): ?>
                                                    <div class="student-item-meta">
                                                        保護者: <?php echo htmlspecialchars($student['guardian_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($student['absence_id'] && $student['absence_reason']): ?>
                                                    <div class="student-item-meta" style="color: var(--apple-red);">
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
                                                            🎉 <?= htmlspecialchars($participant['event_name']) ?>
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
                        <h3 style="color: #856404; margin-bottom: 10px;">⚠️ データベースマイグレーションが必要です</h3>
                        <p style="color: #856404; margin-bottom: 15px;">
                            振替依頼機能を使用するには、データベースのマイグレーションが必要です。
                        </p>
                        <a href="/admin/run_migration_v44.php" style="display: inline-block; background: #007aff; color: var(--text-primary); padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                            マイグレーションを実行する →
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 選択された日付の情報 -->
            <div class="date-info main-content">
                記録日: <?php echo date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($selectedDate))] . '）', strtotime($selectedDate)); ?>
            </div>

            <!-- 活動一覧 -->
            <div class="activity-list main-content">
            <h2>この日の活動一覧</h2>

            <?php if (empty($activities)): ?>
                <div class="empty-message">
                    この日の活動は登録されていません。<br>
                    <?php if ($selectedDate === date('Y-m-d')): ?>
                    下のボタンから新しい活動を追加してください。
                    <?php endif; ?>
                </div>
            <?php else: ?>
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
                                <span style="color: var(--primary-purple);">📝 支援案: <?php echo htmlspecialchars($activity['support_plan_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($activity['common_activity']): ?>
                            <div class="activity-content">
                                <?php echo nl2br(htmlspecialchars($activity['common_activity'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <div class="activity-actions">
                            <a href="renrakucho_form.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-edit">編集</a>
                            <a href="regenerate_integration.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-integrate" onclick="return confirm('既存の統合内容（未送信）を削除して、1から統合し直しますか？');">🔄 統合する</a>
                            <a href="integrate_activity.php?activity_id=<?php echo $activity['id']; ?>" class="btn" style="background: var(--primary-purple); color: white;">✏️ 統合内容を編集</a>
                            <?php if ((int)$activity['sent_count'] > 0): ?>
                                <a href="view_integrated.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-view">📤 送信済み内容を閲覧</a>
                            <?php endif; ?>
                            <form method="POST" action="delete_activity.php" style="display: inline;" onsubmit="return confirm('この活動を削除しますか？');">
                                <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                <button type="submit" class="btn btn-delete">削除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <div class="main-content">
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <button type="button" class="add-activity-btn" onclick="location.href='renrakucho.php?date=<?php echo urlencode($selectedDate); ?>'">
                        新しい活動を追加
                    </button>
                    <button type="button" class="add-activity-btn" style="background: var(--primary-purple);" onclick="location.href='support_plans.php'">
                        📝 支援案を管理
                    </button>
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

        // ハンバーガーメニュー
        document.getElementById('hamburger').addEventListener('click', function() {
            this.classList.toggle('active');
            document.getElementById('userInfo').classList.toggle('mobile-active');
        });

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
                html += '<h4>📝 スタッフ向けコメント</h4>';
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
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEventModal();
            }
        });

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
</body>
</html>
