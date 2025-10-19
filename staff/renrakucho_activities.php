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

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

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

// この月の活動がある日付を取得
$stmt = $pdo->prepare("
    SELECT DISTINCT DATE(record_date) as date
    FROM daily_records
    WHERE staff_id = ?
    AND YEAR(record_date) = ?
    AND MONTH(record_date) = ?
    ORDER BY record_date
");
$stmt->execute([$currentUser['id'], $year, $month]);
$activeDates = array_column($stmt->fetchAll(), 'date');

// この月の休日を取得
$stmt = $pdo->prepare("
    SELECT holiday_date, holiday_name, holiday_type
    FROM holidays
    WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?
");
$stmt->execute([$year, $month]);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
$holidayDates = [];
foreach ($holidays as $holiday) {
    $holidayDates[$holiday['holiday_date']] = [
        'name' => $holiday['holiday_name'],
        'type' => $holiday['holiday_type']
    ];
}

// この月のイベントを取得
$stmt = $pdo->prepare("
    SELECT event_date, event_name, event_description, event_color
    FROM events
    WHERE YEAR(event_date) = ? AND MONTH(event_date) = ?
");
$stmt->execute([$year, $month]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
$eventDates = [];
foreach ($events as $event) {
    if (!isset($eventDates[$event['event_date']])) {
        $eventDates[$event['event_date']] = [];
    }
    $eventDates[$event['event_date']][] = [
        'name' => $event['event_name'],
        'description' => $event['event_description'],
        'color' => $event['event_color']
    ];
}

// 選択された日付の活動一覧を取得
$stmt = $pdo->prepare("
    SELECT dr.id, dr.activity_name, dr.common_activity,
           COUNT(DISTINCT sr.id) as participant_count,
           COUNT(DISTINCT inote.id) as integrated_count
    FROM daily_records dr
    LEFT JOIN student_records sr ON dr.id = sr.daily_record_id
    LEFT JOIN integrated_notes inote ON dr.id = inote.daily_record_id AND inote.is_sent = 0
    WHERE dr.record_date = ? AND dr.staff_id = ?
    GROUP BY dr.id
    ORDER BY dr.created_at
");
$stmt->execute([$selectedDate, $currentUser['id']]);
$activities = $stmt->fetchAll();

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

// 休日チェック
$stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
$stmt->execute([$selectedDate]);
$isHoliday = $stmt->fetchColumn() > 0;

$scheduledStudents = [];
$eventParticipants = [];

if (!$isHoliday) {
    // 通常の参加予定者を取得
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            s.grade_level,
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
    $scheduledStudents = $stmt->fetchAll();

    // イベント参加者を取得
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            s.grade_level,
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
    $eventParticipants = $stmt->fetchAll();
}

// 個別支援計画書が未作成または古い生徒の数を取得
$planNeedingCount = 0;

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

// モニタリングが未作成または古い生徒の数を取得
$monitoringNeedingCount = 0;

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

// かけはし通知データを取得
$today = date('Y-m-d');

// 1. 未提出の保護者かけはし（期限切れも含む、非表示を除外）の件数を取得
$guardianKakehashiCount = 0;
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

// 2. 未作成のスタッフかけはし（期限切れも含む、非表示を除外）の件数を取得
$staffKakehashiCount = 0;
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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
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
            background: #f5f5f5;
            padding: 20px;
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
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .calendar-container {
            background: white;
            padding: 12px;
            border-radius: 8px;
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
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }

        .calendar-nav {
            display: flex;
            gap: 4px;
        }

        .calendar-nav a {
            padding: 4px 8px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 11px;
        }

        .calendar-nav a:hover {
            background: #5568d3;
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
            color: #666;
            font-size: 10px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
            padding: 3px;
            cursor: pointer;
            background: white;
            position: relative;
            transition: all 0.15s;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 50px;
        }

        .calendar-day:hover {
            background: #f8f9fa;
            transform: scale(1.05);
        }

        .calendar-day.empty {
            background: #fafafa;
            cursor: default;
        }

        .calendar-day.empty:hover {
            transform: none;
        }

        .calendar-day.today {
            border: 2px solid #667eea;
            background: #e8eaf6;
        }

        .calendar-day.selected {
            background: #667eea;
            color: white;
        }

        .calendar-day.has-activity {
            background: #fff3cd;
        }

        .calendar-day.has-activity.selected {
            background: #667eea;
        }

        .calendar-day.holiday {
            background: #ffe0e0;
        }

        .calendar-day-number {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .calendar-day-content {
            font-size: 8px;
            line-height: 1.2;
            width: 100%;
        }

        .holiday-label {
            color: #dc3545;
            font-weight: bold;
            margin-bottom: 1px;
        }

        .event-label {
            color: #333;
            margin-bottom: 1px;
            display: flex;
            align-items: center;
            gap: 2px;
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
            background: #ff9800;
            border-radius: 50%;
        }

        .date-info {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 18px;
            color: #333;
        }

        .activity-list {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .activity-list h2 {
            color: #333;
            margin-bottom: 15px;
        }

        .activity-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: border-color 0.3s;
        }

        .activity-card:hover {
            border-color: #667eea;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .activity-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .participant-count {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 14px;
        }

        .activity-content {
            color: #666;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .activity-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: transform 0.2s;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-integrate {
            background: #ff9800;
            color: white;
        }

        .btn-view {
            background: #28a745;
            color: white;
        }

        .add-activity-btn {
            padding: 15px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }

        .add-activity-btn:hover {
            background: #218838;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .sunday {
            color: #dc3545;
        }

        .saturday {
            color: #007bff;
        }

        .scheduled-students-box {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
        }

        .scheduled-students-box h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
        }

        .student-item {
            padding: 10px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }

        .student-item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .student-item-meta {
            font-size: 12px;
            color: #666;
        }

        .holiday-notice {
            text-align: center;
            padding: 30px 20px;
            color: #dc3545;
            font-weight: bold;
        }

        .no-students {
            text-align: center;
            padding: 30px 20px;
            color: #999;
        }

        .notification-banner {
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .notification-banner.urgent {
            border-left: 5px solid #dc3545;
            background: #fff5f5;
        }

        .notification-banner.warning {
            border-left: 5px solid #ffc107;
            background: #fffbf0;
        }

        .notification-banner.info {
            border-left: 5px solid #17a2b8;
            background: #f0f9fc;
        }

        .notification-banner.overdue {
            border-left: 5px solid #6c757d;
            background: #f8f9fa;
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
            color: #dc3545;
        }

        .notification-header.warning {
            color: #ff9800;
        }

        .notification-header.overdue {
            color: #6c757d;
        }

        .notification-header.info {
            color: #17a2b8;
        }

        .notification-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-info {
            flex: 1;
        }

        .notification-student {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .notification-period {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
        }

        .notification-deadline {
            font-size: 14px;
            font-weight: bold;
        }

        .notification-deadline.urgent {
            color: #dc3545;
        }

        .notification-deadline.warning {
            color: #ff9800;
        }

        .notification-deadline.overdue {
            color: #6c757d;
        }

        .notification-deadline.info {
            color: #17a2b8;
        }

        .task-summary-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .task-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .task-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-card.has-tasks {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
        }

        .task-card.has-warnings {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fffbf0 0%, #fff4d4 100%);
        }

        .task-card-title {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .task-card-count {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .task-card-count.urgent {
            color: #dc3545;
        }

        .task-card-count.warning {
            color: #ff9800;
        }

        .task-card-count.success {
            color: #28a745;
        }

        .task-card-link {
            margin-top: auto;
        }

        .btn-task-detail {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-task-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .notification-action {
            margin-left: 15px;
        }

        .notification-btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
            transition: background 0.3s;
        }

        .notification-btn:hover {
            background: #5568d3;
        }

        .notification-btn.staff {
            background: #764ba2;
        }

        .notification-btn.staff:hover {
            background: #5d3a7f;
        }

        .notifications-container {
            margin-bottom: 20px;
        }

        /* ドロップダウンメニュー */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            font-family: inherit;
        }

        .dropdown-toggle:hover {
            background: #5568d3;
        }

        .dropdown-toggle.master {
            background: #28a745;
        }

        .dropdown-toggle.master:hover {
            background: #218838;
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
            background: white;
            border-radius: 5px;
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
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
        }

        .dropdown-menu a .menu-icon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php if ($classroom && !empty($classroom['logo_path']) && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
                    <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ" style="height: 50px; width: auto;">
                <?php else: ?>
                    <div style="font-size: 40px;">📋</div>
                <?php endif; ?>
                <div>
                    <h1>活動管理</h1>
                    <?php if ($classroom): ?>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">
                            <?= htmlspecialchars($classroom['classroom_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); ?>さん</span>

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
                    </div>
                </div>

                <!-- チャットボタン -->
                <a href="chat.php" class="chat-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s;">
                    💬 チャット
                </a>

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

                <a href="/logout.php" class="logout-btn">ログアウト</a>
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

        <!-- 未作成タスクサマリー -->
        <?php if ($planNeedingCount > 0 || $monitoringNeedingCount > 0 || $guardianKakehashiCount > 0 || $staffKakehashiCount > 0): ?>
            <div class="task-summary-box">
                <h2 style="margin-bottom: 15px; color: #333; font-size: 20px;">📋 未作成・未提出タスク</h2>
                <div class="task-summary-grid">
                    <!-- 個別支援計画書 -->
                    <div class="task-card <?php echo $planNeedingCount > 0 ? 'has-tasks' : ''; ?>">
                        <div class="task-card-title">個別支援計画書</div>
                        <div class="task-card-count <?php echo $planNeedingCount > 0 ? 'urgent' : 'success'; ?>">
                            <?php echo $planNeedingCount; ?>件
                        </div>
                        <?php if ($planNeedingCount > 0): ?>
                            <div class="task-card-link">
                                <a href="pending_tasks.php" class="btn-task-detail">詳細を確認</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- モニタリング -->
                    <div class="task-card <?php echo $monitoringNeedingCount > 0 ? 'has-warnings' : ''; ?>">
                        <div class="task-card-title">モニタリング</div>
                        <div class="task-card-count <?php echo $monitoringNeedingCount > 0 ? 'warning' : 'success'; ?>">
                            <?php echo $monitoringNeedingCount; ?>件
                        </div>
                        <?php if ($monitoringNeedingCount > 0): ?>
                            <div class="task-card-link">
                                <a href="pending_tasks.php" class="btn-task-detail">詳細を確認</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 保護者かけはし -->
                    <div class="task-card <?php echo $guardianKakehashiCount > 0 ? 'has-warnings' : ''; ?>">
                        <div class="task-card-title">保護者かけはし</div>
                        <div class="task-card-count <?php echo $guardianKakehashiCount > 0 ? 'warning' : 'success'; ?>">
                            <?php echo $guardianKakehashiCount; ?>件
                        </div>
                        <?php if ($guardianKakehashiCount > 0): ?>
                            <div class="task-card-link">
                                <a href="pending_tasks.php" class="btn-task-detail">詳細を確認</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- スタッフかけはし -->
                    <div class="task-card <?php echo $staffKakehashiCount > 0 ? 'has-warnings' : ''; ?>">
                        <div class="task-card-title">スタッフかけはし</div>
                        <div class="task-card-count <?php echo $staffKakehashiCount > 0 ? 'warning' : 'success'; ?>">
                            <?php echo $staffKakehashiCount; ?>件
                        </div>
                        <?php if ($staffKakehashiCount > 0): ?>
                            <div class="task-card-link">
                                <a href="pending_tasks.php" class="btn-task-detail">詳細を確認</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

        <!-- かけはし通知セクション（コメントアウト） -->
        <?php if (false): ?>
        <div class="notifications-container">
            <!-- 期限切れ: 保護者かけはし -->
            <?php if (!empty($overdueGuardianKakehashi)): ?>
                <div class="notification-banner overdue">
                    <div class="notification-header overdue">
                        ⏰ 【期限切れ】保護者かけはし未提出
                    </div>
                    <?php foreach ($overdueGuardianKakehashi as $kakehashi): ?>
                        <div class="notification-item">
                            <div class="notification-info">
                                <div class="notification-student">
                                    <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                                </div>
                                <div class="notification-period">
                                    対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                                </div>
                                <div class="notification-deadline overdue">
                                    提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                    （<?php echo abs($kakehashi['days_left']); ?>日経過）
                                </div>
                            </div>
                            <div class="notification-action">
                                <a href="kakehashi_guardian_view.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                    確認・催促
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 期限切れ: スタッフかけはし -->
            <?php if (!empty($overdueStaffKakehashi)): ?>
                <div class="notification-banner overdue">
                    <div class="notification-header overdue">
                        ⏰ 【期限切れ】スタッフかけはし未作成
                    </div>
                    <?php foreach ($overdueStaffKakehashi as $kakehashi): ?>
                        <div class="notification-item">
                            <div class="notification-info">
                                <div class="notification-student">
                                    <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                                </div>
                                <div class="notification-period">
                                    対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                                </div>
                                <div class="notification-deadline overdue">
                                    提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                    （<?php echo abs($kakehashi['days_left']); ?>日経過）
                                </div>
                            </div>
                            <div class="notification-action">
                                <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                    作成する
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 緊急: 未提出保護者かけはし (7日以内) -->
            <?php if (!empty($urgentGuardianKakehashi)): ?>
                <div class="notification-banner urgent">
                    <div class="notification-header urgent">
                        ⚠️ 【緊急】保護者かけはし未提出（提出期限7日以内）
                    </div>
                    <?php foreach ($urgentGuardianKakehashi as $kakehashi): ?>
                        <div class="notification-item">
                            <div class="notification-info">
                                <div class="notification-student">
                                    <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                                </div>
                                <div class="notification-period">
                                    対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                                </div>
                                <div class="notification-deadline urgent">
                                    提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                    （残り<?php echo $kakehashi['days_left']; ?>日）
                                </div>
                            </div>
                            <div class="notification-action">
                                <a href="kakehashi_guardian_view.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                    確認・催促
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 緊急: 未作成スタッフかけはし (7日以内) -->
            <?php if (!empty($urgentStaffKakehashi)): ?>
                <div class="notification-banner urgent">
                    <div class="notification-header urgent">
                        ⚠️ 【緊急】スタッフかけはし未作成（提出期限7日以内）
                    </div>
                    <?php foreach ($urgentStaffKakehashi as $kakehashi): ?>
                        <div class="notification-item">
                            <div class="notification-info">
                                <div class="notification-student">
                                    <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                                </div>
                                <div class="notification-period">
                                    対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                                </div>
                                <div class="notification-deadline urgent">
                                    提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                    （残り<?php echo $kakehashi['days_left']; ?>日）
                                </div>
                            </div>
                            <div class="notification-action">
                                <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn staff">
                                    作成する
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 警告: 未提出保護者かけはし (8日以上) -->
            <?php if (!empty($pendingGuardianKakehashi)): ?>
                <div class="notification-banner warning">
                    <div class="notification-header warning">
                        ⏰ 保護者かけはし未提出（提出期限内）
                    </div>
                    <?php foreach (array_slice($pendingGuardianKakehashi, 0, 5) as $kakehashi): ?>
                        <div class="notification-item">
                            <div class="notification-info">
                                <div class="notification-student">
                                    <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                                </div>
                                <div class="notification-period">
                                    対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                                </div>
                                <div class="notification-deadline warning">
                                    提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                    （残り<?php echo $kakehashi['days_left']; ?>日）
                                </div>
                            </div>
                            <div class="notification-action">
                                <a href="kakehashi_guardian_view.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                    確認
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($pendingGuardianKakehashi) > 5): ?>
                        <div style="text-align: center; margin-top: 10px; color: #666; font-size: 14px;">
                            他 <?php echo count($pendingGuardianKakehashi) - 5; ?>件の未提出があります
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 警告: 未作成スタッフかけはし (8日以上) -->
            <?php if (!empty($pendingStaffKakehashi)): ?>
                <div class="notification-banner warning">
                    <div class="notification-header warning">
                        ⏰ スタッフかけはし未作成（提出期限内）
                    </div>
                    <?php foreach (array_slice($pendingStaffKakehashi, 0, 5) as $kakehashi): ?>
                        <div class="notification-item">
                            <div class="notification-info">
                                <div class="notification-student">
                                    <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                                </div>
                                <div class="notification-period">
                                    対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                                </div>
                                <div class="notification-deadline warning">
                                    提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                    （残り<?php echo $kakehashi['days_left']; ?>日）
                                </div>
                            </div>
                            <div class="notification-action">
                                <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn staff">
                                    作成する
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($pendingStaffKakehashi) > 5): ?>
                        <div style="text-align: center; margin-top: 10px; color: #666; font-size: 14px;">
                            他 <?php echo count($pendingStaffKakehashi) - 5; ?>件の未作成があります
                        </div>
                    <?php endif; ?>
                </div>
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
                                    echo "<div class='event-label'>";
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
                        <?php foreach ($scheduledStudents as $student): ?>
                            <div class="student-item">
                                <div class="student-item-name">
                                    <?php echo htmlspecialchars($student['student_name']); ?>
                                    <span class="grade-badge" style="font-size: 10px; padding: 2px 8px; margin-left: 5px;">
                                        <?php
                                        $gradeLabels = [
                                            'elementary' => '小',
                                            'junior_high' => '中',
                                            'high_school' => '高'
                                        ];
                                        echo $gradeLabels[$student['grade_level']] ?? '';
                                        ?>
                                    </span>
                                    <?php if ($student['absence_id']): ?>
                                        <span style="color: #dc3545; font-weight: bold; margin-left: 8px;">🚫 欠席連絡あり</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($student['guardian_name']): ?>
                                    <div class="student-item-meta">
                                        保護者: <?php echo htmlspecialchars($student['guardian_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($student['absence_id'] && $student['absence_reason']): ?>
                                    <div class="student-item-meta" style="color: #dc3545;">
                                        理由: <?php echo htmlspecialchars($student['absence_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- イベント参加者を表示 -->
                        <?php if (!empty($eventParticipants)): ?>
                            <div style="margin-top: 20px; padding-top: 15px; border-top: 2px dashed #2563eb;">
                                <div style="font-weight: 600; color: #2563eb; margin-bottom: 10px;">🎉 イベント参加者</div>
                                <?php foreach ($eventParticipants as $participant): ?>
                                    <div class="student-item" style="border-left: 4px solid #2563eb;">
                                        <div class="student-item-name">
                                            <?php echo htmlspecialchars($participant['student_name']); ?>
                                            <span class="grade-badge" style="font-size: 10px; padding: 2px 8px; margin-left: 5px;">
                                                <?php
                                                $gradeLabels = [
                                                    'elementary' => '小',
                                                    'junior_high' => '中',
                                                    'high_school' => '高'
                                                ];
                                                echo $gradeLabels[$participant['grade_level']] ?? '';
                                                ?>
                                            </span>
                                            <span style="color: #2563eb; font-weight: bold; margin-left: 8px;">
                                                <?= htmlspecialchars($participant['event_name']) ?>
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
                            </div>
                        <?php endif; ?>

                        <div style="text-align: center; margin-top: 12px; font-size: 13px; color: #666;">
                            通常予定: <?php echo count($scheduledStudents); ?>名
                            <?php if (!empty($eventParticipants)): ?>
                                / イベント: <?php echo count($eventParticipants); ?>名
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

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

                        <?php if ($activity['common_activity']): ?>
                            <div class="activity-content">
                                <?php echo nl2br(htmlspecialchars($activity['common_activity'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <div class="activity-actions">
                            <a href="renrakucho_form.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-edit">編集</a>
                            <a href="integrate_activity.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-integrate">活動内容の統合</a>
                            <?php if ($activity['integrated_count'] > 0): ?>
                                <a href="view_integrated.php?activity_id=<?php echo $activity['id']; ?>" class="btn btn-view">統合内容を閲覧</a>
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
                <button type="button" class="add-activity-btn" onclick="location.href='renrakucho.php?date=<?php echo urlencode($selectedDate); ?>'">
                    新しい活動を追加
                </button>
            </div>
        </div>
    </div>
</body>
</html>
