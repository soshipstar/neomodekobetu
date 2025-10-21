<?php
/**
 * 保護者用トップページ
 * 送信された活動記録を表示
 */

// ファイル読み込み（出力前に実行）
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// 保護者でない場合は適切なページへリダイレクト
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// カレンダー用の年月を取得（デフォルトは今月）
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// 月の初日と最終日
$firstDay = strtotime("$year-$month-1");
$lastDay = strtotime(date('Y-m-t', $firstDay));

// 前月・次月の計算
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

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

// この保護者に紐づく生徒を取得（在籍中のみ）
try {
    $stmt = $pdo->prepare("
        SELECT id, student_name, grade_level, status,
               scheduled_sunday, scheduled_monday, scheduled_tuesday, scheduled_wednesday,
               scheduled_thursday, scheduled_friday, scheduled_saturday
        FROM students
        WHERE guardian_id = ? AND is_active = 1 AND status = 'active'
        ORDER BY student_name
    ");
    $stmt->execute([$guardianId]);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching students: " . $e->getMessage());
    $students = [];
}

// integrated_notesテーブルが存在するかチェック
$hasIntegratedNotesTable = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'integrated_notes'");
    $hasIntegratedNotesTable = ($stmt->rowCount() > 0);
} catch (Exception $e) {
    error_log("Error checking tables: " . $e->getMessage());
}

// 未提出かけはしを取得
$pendingKakehashi = [];
$urgentKakehashi = [];
$overdueKakehashi = [];
$today = date('Y-m-d');
$oneWeekLater = date('Y-m-d', strtotime('+7 days'));

foreach ($students as $student) {
    try {
        // 未提出のかけはしを取得（期限切れも含む、スタッフが非表示にしたものは除外）
        $stmt = $pdo->prepare("
            SELECT
                kp.id as period_id,
                kp.period_name,
                kp.submission_deadline,
                kp.start_date,
                kp.end_date,
                DATEDIFF(kp.submission_deadline, ?) as days_left,
                kg.id as kakehashi_id,
                kg.is_submitted,
                kg.is_hidden
            FROM kakehashi_periods kp
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = ?
            WHERE kp.student_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND (kg.is_hidden = 0 OR kg.is_hidden IS NULL)
            ORDER BY kp.submission_deadline ASC
        ");
        $stmt->execute([$today, $student['id'], $student['id']]);
        $periods = $stmt->fetchAll();

        foreach ($periods as $period) {
            $daysLeft = $period['days_left'];
            $period['student_name'] = $student['student_name'];
            $period['student_id'] = $student['id'];

            // 期限切れ
            if ($daysLeft < 0) {
                $overdueKakehashi[] = $period;
            }
            // 7日以内は緊急
            elseif ($daysLeft <= 7) {
                $urgentKakehashi[] = $period;
            }
            // それ以外は通常
            else {
                $pendingKakehashi[] = $period;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching kakehashi for student " . $student['id'] . ": " . $e->getMessage());
    }
}

// 未提出の提出期限を取得
$pendingSubmissions = [];
$overdueSubmissions = [];
$urgentSubmissions = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.title,
            sr.description,
            sr.due_date,
            sr.created_at,
            sr.attachment_path,
            sr.attachment_original_name,
            sr.attachment_size,
            s.student_name,
            DATEDIFF(sr.due_date, ?) as days_left
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        WHERE sr.guardian_id = ? AND sr.is_completed = 0
        ORDER BY sr.due_date ASC
    ");
    $stmt->execute([$today, $guardianId]);
    $submissions = $stmt->fetchAll();

    foreach ($submissions as $submission) {
        $daysLeft = $submission['days_left'];

        // 期限切れ
        if ($daysLeft < 0) {
            $overdueSubmissions[] = $submission;
        }
        // 3日以内は緊急
        elseif ($daysLeft <= 3) {
            $urgentSubmissions[] = $submission;
        }
        // それ以外は通常
        else {
            $pendingSubmissions[] = $submission;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching submission requests: " . $e->getMessage());
}

// 各生徒の最新の連絡帳を取得
$notesData = [];
if ($hasIntegratedNotesTable) {
    foreach ($students as $student) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    inote.id,
                    inote.integrated_content,
                    inote.sent_at,
                    inote.guardian_confirmed,
                    inote.guardian_confirmed_at,
                    dr.activity_name,
                    dr.record_date
                FROM integrated_notes inote
                INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
                WHERE inote.student_id = ? AND inote.is_sent = 1
                ORDER BY dr.record_date DESC, inote.sent_at DESC
                LIMIT 10
            ");
            $stmt->execute([$student['id']]);
            $notesData[$student['id']] = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching notes for student " . $student['id'] . ": " . $e->getMessage());
            $notesData[$student['id']] = [];
        }
    }
} else {
    // テーブルが存在しない場合は空配列
    foreach ($students as $student) {
        $notesData[$student['id']] = [];
    }
}

// 各生徒の活動予定日を取得（カレンダー表示用）
$studentSchedules = [];
foreach ($students as $student) {
    $studentSchedules[$student['id']] = [
        'name' => $student['student_name'],
        'scheduled_days' => []
    ];

    // 曜日ごとの予定を取得
    $dayColumns = [
        0 => 'scheduled_sunday',
        1 => 'scheduled_monday',
        2 => 'scheduled_tuesday',
        3 => 'scheduled_wednesday',
        4 => 'scheduled_thursday',
        5 => 'scheduled_friday',
        6 => 'scheduled_saturday'
    ];

    foreach ($dayColumns as $dayNum => $columnName) {
        if (!empty($student[$columnName])) {
            $studentSchedules[$student['id']]['scheduled_days'][] = $dayNum;
        }
    }
}

// カレンダー表示月の全日付について、各生徒の予定を格納
$calendarSchedules = [];
for ($day = 1; $day <= date('t', $firstDay); $day++) {
    $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
    $dayOfWeek = date('w', strtotime($currentDate));

    // 休日チェック
    $isDateHoliday = isset($holidayDates[$currentDate]);

    // この日に予定がある生徒をリストアップ
    $calendarSchedules[$currentDate] = [];
    foreach ($studentSchedules as $studentId => $schedule) {
        if (!$isDateHoliday && in_array($dayOfWeek, $schedule['scheduled_days'])) {
            $calendarSchedules[$currentDate][] = [
                'student_id' => $studentId,
                'student_name' => $schedule['name']
            ];
        }
    }
}

// カレンダー表示月の連絡帳情報を取得
$calendarNotes = [];
if ($hasIntegratedNotesTable && !empty($students)) {
    try {
        $studentIds = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        $firstDayStr = date('Y-m-d', $firstDay);
        $lastDayStr = date('Y-m-d', $lastDay);

        $stmt = $pdo->prepare("
            SELECT
                inote.student_id,
                inote.guardian_confirmed,
                dr.record_date,
                s.student_name
            FROM integrated_notes inote
            INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
            INNER JOIN students s ON inote.student_id = s.id
            WHERE inote.student_id IN ($placeholders)
            AND inote.is_sent = 1
            AND dr.record_date BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($studentIds, [$firstDayStr, $lastDayStr]));
        $notes = $stmt->fetchAll();

        foreach ($notes as $note) {
            $date = $note['record_date'];
            if (!isset($calendarNotes[$date])) {
                $calendarNotes[$date] = [];
            }
            $calendarNotes[$date][] = [
                'student_id' => $note['student_id'],
                'student_name' => $note['student_name'],
                'guardian_confirmed' => $note['guardian_confirmed']
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching calendar notes: " . $e->getMessage());
    }
}

// 学年表示用のラベル
function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学部',
        'junior_high' => '中学部',
        'high_school' => '高等部'
    ];
    return $labels[$gradeLevel] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>連絡帳 - 保護者ページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }
        .logout-btn:hover {
            background: #c82333;
        }

        .menu-dropdown {
            position: relative;
            display: inline-block;
        }

        .menu-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .menu-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 220px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            z-index: 1000;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 8px;
            right: 0;
        }

        .menu-content.show {
            display: block;
        }

        .menu-content a {
            color: #333;
            padding: 14px 20px;
            text-decoration: none;
            display: block;
            transition: all 0.2s;
            font-size: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .menu-content a:last-child {
            border-bottom: none;
        }

        .menu-content a:hover {
            background: linear-gradient(135deg, #f0f4ff 0%, #faf0ff 100%);
            color: #667eea;
            padding-left: 25px;
        }
        .student-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .student-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .student-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-right: 10px;
        }
        .grade-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
        }
        .note-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .activity-name {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }
        .note-date {
            color: #666;
            font-size: 14px;
        }
        .note-content {
            color: #333;
            line-height: 1.8;
            white-space: pre-wrap;
            font-size: 15px;
        }
        .confirmation-box {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .confirmation-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .confirmation-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .confirmation-checkbox label {
            cursor: pointer;
            font-weight: 500;
            color: #333;
            user-select: none;
        }
        .confirmation-checkbox.confirmed label {
            color: #28a745;
        }
        .confirmation-date {
            font-size: 13px;
            color: #28a745;
            font-weight: 500;
        }
        .no-notes {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h2 {
            margin-bottom: 10px;
            color: #333;
        }
        .calendar-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .calendar-header h2 {
            color: #333;
            font-size: 20px;
            font-weight: bold;
        }
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        .calendar-nav a {
            padding: 6px 12px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
        }
        .calendar-nav a:hover {
            background: #5568d3;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-day-header {
            text-align: center;
            padding: 8px 4px;
            font-weight: bold;
            color: #666;
            font-size: 13px;
        }
        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 5px;
            background: white;
            position: relative;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .calendar-day.empty {
            background: #fafafa;
        }
        .calendar-day.today {
            border: 2px solid #667eea;
            background: #e8eaf6;
        }
        .calendar-day.holiday {
            background: #ffe0e0;
        }
        .calendar-day-number {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
            align-self: flex-start;
        }
        .calendar-day-content {
            font-size: 10px;
            line-height: 1.3;
            width: 100%;
        }
        .event-marker {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 3px;
        }
        .holiday-label {
            color: #dc3545;
            font-weight: bold;
            font-size: 9px;
        }
        .event-label {
            color: #333;
            font-size: 9px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
        }
        .schedule-label {
            color: #667eea;
            font-size: 9px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        .schedule-label.no-note {
            color: #999;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .schedule-label.no-note:hover {
            opacity: 0.7;
        }
        .schedule-marker {
            margin-right: 2px;
            font-size: 8px;
        }
        .note-label {
            font-size: 9px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .note-label:hover {
            opacity: 0.7;
        }
        /* 今日以降の連絡帳 */
        .note-label.confirmed {
            color: #28a745;
        }
        .note-label.unconfirmed {
            color: #dc3545;
        }
        /* 過去日の連絡帳 */
        .note-label.confirmed-past {
            color: #20c997;
        }
        .note-label.unconfirmed-past {
            color: #fd7e14;
        }
        .note-marker {
            margin-right: 2px;
            font-size: 8px;
        }

        /* モーダルスタイル */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        .modal-close:hover {
            color: #333;
        }
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .modal-header h2 {
            color: #333;
            font-size: 22px;
            margin: 0;
        }
        .modal-date {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .sunday {
            color: #dc3545;
        }
        .saturday {
            color: #007bff;
        }
        .legend {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 3px;
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
        .notification-banner.pending {
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
        .notification-header.pending {
            color: #17a2b8;
        }
        .notification-header.overdue {
            color: #6c757d;
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
        .notification-deadline.pending {
            color: #17a2b8;
        }
        .notification-deadline.overdue {
            color: #6c757d;
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
        .progress-bar-container {
            margin-top: 15px;
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
        }
        .progress-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            text-align: right;
        }

        /* ボタングループスタイル */
        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-btn {
            padding: 8px 16px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-btn.kakehashi {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .nav-btn.kakehashi:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .nav-btn.logs {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .nav-btn.logs:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
        }

        .user-info-box {
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 5px;
            color: #667eea;
            font-weight: 500;
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
                    <div style="font-size: 40px;">📖</div>
                <?php endif; ?>
                <div>
                    <h1>連絡帳ダッシュボード</h1>
                    <?php if ($classroom): ?>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">
                            <?= htmlspecialchars($classroom['classroom_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="menu-dropdown">
                    <button class="menu-btn" onclick="toggleMenu()">
                        📑 メニュー ▼
                    </button>
                    <div class="menu-content" id="menuDropdown">
                        <a href="communication_logs.php">📚 連絡帳一覧</a>
                        <a href="chat.php">💬 チャット</a>
                        <a href="kakehashi.php">🌉 かけはし入力</a>
                        <a href="support_plans.php">📋 個別支援計画書</a>
                        <a href="monitoring.php">📊 モニタリング表</a>
                    </div>
                </div>
                <span class="user-info-box">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>さん
                </span>
                <a href="/logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>

        <!-- 期限切れかけはし通知 -->
        <?php if (!empty($overdueKakehashi)): ?>
            <div class="notification-banner overdue">
                <div class="notification-header overdue">
                    ⏰ 提出期限が過ぎたかけはしがあります
                </div>
                <?php foreach ($overdueKakehashi as $kakehashi): ?>
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
                            <a href="kakehashi.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                かけはしを入力
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 緊急かけはし通知 -->
        <?php if (!empty($urgentKakehashi)): ?>
            <div class="notification-banner urgent">
                <div class="notification-header urgent">
                    ⚠️ 提出期限が近いかけはしがあります
                </div>
                <?php foreach ($urgentKakehashi as $kakehashi): ?>
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
                            <a href="kakehashi.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                かけはしを入力
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 未提出かけはし通知 -->
        <?php if (!empty($pendingKakehashi)): ?>
            <div class="notification-banner pending">
                <div class="notification-header pending">
                    📝 未提出のかけはしがあります
                </div>
                <?php foreach ($pendingKakehashi as $kakehashi): ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?php echo htmlspecialchars($kakehashi['student_name']); ?>さん
                            </div>
                            <div class="notification-period">
                                対象期間: <?php echo date('Y年n月j日', strtotime($kakehashi['start_date'])); ?> ～ <?php echo date('Y年n月j日', strtotime($kakehashi['end_date'])); ?>
                            </div>
                            <div class="notification-deadline pending">
                                提出期限: <?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?>
                                （残り<?php echo $kakehashi['days_left']; ?>日）
                            </div>
                        </div>
                        <div class="notification-action">
                            <a href="kakehashi.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="notification-btn">
                                かけはしを入力
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 期限切れ提出物通知 -->
        <?php if (!empty($overdueSubmissions)): ?>
            <div class="notification-banner overdue">
                <div class="notification-header overdue">
                    ⚠️ 提出期限が過ぎた提出物があります
                </div>
                <?php foreach ($overdueSubmissions as $submission): ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?php echo htmlspecialchars($submission['student_name']); ?>さん
                            </div>
                            <div class="notification-period">
                                件名: <?php echo htmlspecialchars($submission['title']); ?>
                            </div>
                            <?php if ($submission['description']): ?>
                                <div class="notification-period" style="font-size: 13px; color: #999;">
                                    <?php echo nl2br(htmlspecialchars($submission['description'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="notification-deadline overdue">
                                提出期限: <?php echo date('Y年n月j日', strtotime($submission['due_date'])); ?>
                                （<?php echo abs($submission['days_left']); ?>日経過）
                            </div>
                            <?php if ($submission['attachment_path']): ?>
                                <div style="margin-top: 10px;">
                                    <a href="../<?= htmlspecialchars($submission['attachment_path']) ?>"
                                       style="color: #667eea; text-decoration: underline; font-size: 13px;"
                                       download="<?= htmlspecialchars($submission['attachment_original_name']) ?>">
                                        📎 <?= htmlspecialchars($submission['attachment_original_name']) ?>
                                        (<?= number_format($submission['attachment_size'] / 1024, 1) ?> KB)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 緊急提出物通知 -->
        <?php if (!empty($urgentSubmissions)): ?>
            <div class="notification-banner urgent">
                <div class="notification-header urgent">
                    🔔 提出期限が近い提出物があります
                </div>
                <?php foreach ($urgentSubmissions as $submission): ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?php echo htmlspecialchars($submission['student_name']); ?>さん
                            </div>
                            <div class="notification-period">
                                件名: <?php echo htmlspecialchars($submission['title']); ?>
                            </div>
                            <?php if ($submission['description']): ?>
                                <div class="notification-period" style="font-size: 13px; color: #999;">
                                    <?php echo nl2br(htmlspecialchars($submission['description'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="notification-deadline urgent">
                                提出期限: <?php echo date('Y年n月j日', strtotime($submission['due_date'])); ?>
                                （残り<?php echo $submission['days_left']; ?>日）
                            </div>
                            <?php if ($submission['attachment_path']): ?>
                                <div style="margin-top: 10px;">
                                    <a href="../<?= htmlspecialchars($submission['attachment_path']) ?>"
                                       style="color: #667eea; text-decoration: underline; font-size: 13px;"
                                       download="<?= htmlspecialchars($submission['attachment_original_name']) ?>">
                                        📎 <?= htmlspecialchars($submission['attachment_original_name']) ?>
                                        (<?= number_format($submission['attachment_size'] / 1024, 1) ?> KB)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 未提出提出物通知 -->
        <?php if (!empty($pendingSubmissions)): ?>
            <div class="notification-banner pending">
                <div class="notification-header pending">
                    📋 提出が必要な提出物があります
                </div>
                <?php foreach ($pendingSubmissions as $submission): ?>
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-student">
                                <?php echo htmlspecialchars($submission['student_name']); ?>さん
                            </div>
                            <div class="notification-period">
                                件名: <?php echo htmlspecialchars($submission['title']); ?>
                            </div>
                            <?php if ($submission['description']): ?>
                                <div class="notification-period" style="font-size: 13px; color: #999;">
                                    <?php echo nl2br(htmlspecialchars($submission['description'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="notification-deadline pending">
                                提出期限: <?php echo date('Y年n月j日', strtotime($submission['due_date'])); ?>
                                （残り<?php echo $submission['days_left']; ?>日）
                            </div>
                            <?php if ($submission['attachment_path']): ?>
                                <div style="margin-top: 10px;">
                                    <a href="../<?= htmlspecialchars($submission['attachment_path']) ?>"
                                       style="color: #667eea; text-decoration: underline; font-size: 13px;"
                                       download="<?= htmlspecialchars($submission['attachment_original_name']) ?>">
                                        📎 <?= htmlspecialchars($submission['attachment_original_name']) ?>
                                        (<?= number_format($submission['attachment_size'] / 1024, 1) ?> KB)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- カレンダーセクション -->
        <div class="calendar-section">
            <div class="calendar-header">
                <h2>📅 <?php echo $year; ?>年 <?php echo $month; ?>月のカレンダー</h2>
                <div class="calendar-nav">
                    <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>">← 前月</a>
                    <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('n'); ?>">今月</a>
                    <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>">次月 →</a>
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
                    if (isset($holidayDates[$currentDate])) {
                        $classes[] = 'holiday';
                    }

                    $dayClass = '';
                    if ($dayOfWeek === 0) $dayClass = 'sunday';
                    if ($dayOfWeek === 6) $dayClass = 'saturday';

                    echo "<div class='" . implode(' ', $classes) . "'>";
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

                    // 生徒の活動予定を表示
                    if (isset($calendarSchedules[$currentDate]) && !empty($calendarSchedules[$currentDate])) {
                        foreach ($calendarSchedules[$currentDate] as $schedule) {
                            $isPast = strtotime($currentDate) < strtotime(date('Y-m-d'));
                            $hasNote = isset($calendarNotes[$currentDate]) && !empty($calendarNotes[$currentDate]);

                            // 過去日で連絡帳がない場合
                            if ($isPast && !$hasNote) {
                                echo "<div class='schedule-label no-note' onclick='showNoteModal(\"$currentDate\")'>";
                                echo "<span class='schedule-marker'>👤</span>";
                                echo htmlspecialchars($schedule['student_name']) . "さん活動日（連絡帳なし）";
                                echo "</div>";
                            }
                            // 過去日で連絡帳がある場合（連絡帳側で表示）
                            elseif ($isPast) {
                                // 何も表示しない（連絡帳情報で表示される）
                            }
                            // 未来または今日の場合
                            else {
                                echo "<div class='schedule-label'>";
                                echo "<span class='schedule-marker'>👤</span>";
                                echo htmlspecialchars($schedule['student_name']) . "さん活動予定日";
                                echo "</div>";
                            }
                        }
                    }

                    // 連絡帳情報を表示
                    if (isset($calendarNotes[$currentDate]) && !empty($calendarNotes[$currentDate])) {
                        foreach ($calendarNotes[$currentDate] as $noteInfo) {
                            $isPast = strtotime($currentDate) < strtotime(date('Y-m-d'));
                            $isConfirmed = $noteInfo['guardian_confirmed'];

                            if ($isPast) {
                                // 過去日の場合
                                $class = $isConfirmed ? 'note-label confirmed-past' : 'note-label unconfirmed-past';
                                $text = $isConfirmed ? '活動日（確認済み）' : '活動日（要確認）';
                            } else {
                                // 今日または未来の場合
                                $class = $isConfirmed ? 'note-label confirmed' : 'note-label unconfirmed';
                                $text = $isConfirmed ? '連絡帳あり' : '連絡帳あり（確認してください）';
                            }

                            echo "<div class='$class' onclick='showNoteModal(\"$currentDate\")'>";
                            echo "<span class='note-marker'>📝</span>";
                            echo htmlspecialchars($noteInfo['student_name']) . "さん" . htmlspecialchars($text);
                            echo "</div>";
                        }
                    }

                    echo "</div>";
                    echo "</div>";
                }
                ?>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-box" style="background: #ffe0e0; border: 1px solid #e0e0e0;"></div>
                    <span>休日</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background: #e8eaf6; border: 2px solid #667eea;"></div>
                    <span>今日</span>
                </div>
                <div class="legend-item">
                    <span class="event-marker" style="background: #28a745;"></span>
                    <span>イベント</span>
                </div>
                <div class="legend-item">
                    <span style="color: #667eea; font-weight: 600;">👤</span>
                    <span>活動予定日（未来）</span>
                </div>
                <div class="legend-item">
                    <span style="color: #28a745; font-weight: 600;">📝</span>
                    <span>連絡帳あり（確認済み）</span>
                </div>
                <div class="legend-item">
                    <span style="color: #dc3545; font-weight: 600;">📝</span>
                    <span>連絡帳あり（未確認）</span>
                </div>
                <div class="legend-item">
                    <span style="color: #20c997; font-weight: 600;">📝</span>
                    <span>過去活動日（確認済み）</span>
                </div>
                <div class="legend-item">
                    <span style="color: #fd7e14; font-weight: 600;">📝</span>
                    <span>過去活動日（要確認）</span>
                </div>
                <div class="legend-item">
                    <span style="color: #999; font-weight: 600;">👤</span>
                    <span>過去活動日（連絡帳なし）</span>
                </div>
            </div>
        </div>

        <?php if (empty($students)): ?>
            <div class="student-section">
                <div class="empty-state">
                    <h2>お子様の情報が登録されていません</h2>
                    <p>管理者にお問い合わせください。</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <div class="student-section">
                    <div class="student-header">
                        <span class="student-name"><?php echo htmlspecialchars($student['student_name']); ?></span>
                        <span class="grade-badge"><?php echo getGradeLabel($student['grade_level']); ?></span>
                    </div>

                    <?php if (empty($notesData[$student['id']])): ?>
                        <div class="no-notes">
                            まだ連絡帳が送信されていません
                        </div>
                    <?php else: ?>
                        <?php foreach ($notesData[$student['id']] as $note): ?>
                            <div class="note-item">
                                <div class="note-header">
                                    <span class="activity-name"><?php echo htmlspecialchars($note['activity_name']); ?></span>
                                    <span class="note-date">
                                        <?php echo date('Y年n月j日', strtotime($note['record_date'])); ?>
                                        （送信: <?php echo date('H:i', strtotime($note['sent_at'])); ?>）
                                    </span>
                                </div>
                                <div class="note-content"><?php echo htmlspecialchars($note['integrated_content']); ?></div>

                                <!-- 保護者確認チェックボックス -->
                                <div class="confirmation-box">
                                    <div class="confirmation-checkbox <?php echo $note['guardian_confirmed'] ? 'confirmed' : ''; ?>">
                                        <input
                                            type="checkbox"
                                            id="confirm_<?php echo $note['id']; ?>"
                                            <?php echo $note['guardian_confirmed'] ? 'checked disabled' : ''; ?>
                                            onchange="confirmNote(<?php echo $note['id']; ?>)"
                                        >
                                        <label for="confirm_<?php echo $note['id']; ?>">確認しました</label>
                                    </div>
                                    <?php if ($note['guardian_confirmed'] && $note['guardian_confirmed_at']): ?>
                                        <span class="confirmation-date">
                                            確認日時: <?php echo date('Y年n月j日 H:i', strtotime($note['guardian_confirmed_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 連絡帳詳細モーダル -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeNoteModal()">&times;</button>
            <div class="modal-header">
                <h2>📝 連絡帳</h2>
                <div class="modal-date" id="modalDate"></div>
            </div>
            <div id="modalNoteContent">
                <!-- 連絡帳の内容がここに表示されます -->
            </div>
        </div>
    </div>

    <script>
    function confirmNote(noteId) {
        if (!confirm('この連絡帳を「確認しました」にしてよろしいですか?')) {
            // キャンセルされた場合、チェックボックスを元に戻す
            document.getElementById('confirm_' + noteId).checked = false;
            return;
        }

        // サーバーに確認リクエストを送信
        fetch('confirm_note.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'note_id=' + noteId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 成功したらページをリロード
                location.reload();
            } else {
                alert('エラーが発生しました: ' + (data.error || '不明なエラー'));
                // エラーの場合、チェックボックスを元に戻す
                document.getElementById('confirm_' + noteId).checked = false;
            }
        })
        .catch(error => {
            alert('通信エラーが発生しました');
            console.error('Error:', error);
            // エラーの場合、チェックボックスを元に戻す
            document.getElementById('confirm_' + noteId).checked = false;
        });
    }

    // モーダルを表示
    function showNoteModal(date) {
        // サーバーから指定日の連絡帳を取得
        fetch('get_notes_by_date.php?date=' + date)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notes && data.notes.length > 0) {
                    // 日付を表示
                    const dateObj = new Date(date + 'T00:00:00');
                    const dateStr = dateObj.getFullYear() + '年' +
                                   (dateObj.getMonth() + 1) + '月' +
                                   dateObj.getDate() + '日';
                    document.getElementById('modalDate').textContent = dateStr;

                    // 連絡帳の内容を表示
                    let html = '';
                    data.notes.forEach((note, index) => {
                        html += '<div class="note-item" style="margin-bottom: ' + (index < data.notes.length - 1 ? '20px' : '0') + ';">';
                        html += '<div class="note-header">';
                        html += '<span class="activity-name">' + escapeHtml(note.activity_name) + '</span>';
                        html += '<span class="note-date">送信: ' + note.sent_time + '</span>';
                        html += '</div>';
                        html += '<div class="note-content">' + escapeHtml(note.integrated_content) + '</div>';

                        // 確認チェックボックス
                        html += '<div class="confirmation-box">';
                        html += '<div class="confirmation-checkbox' + (note.guardian_confirmed ? ' confirmed' : '') + '">';
                        html += '<input type="checkbox" id="modal_confirm_' + note.id + '" ';
                        html += note.guardian_confirmed ? 'checked disabled' : '';
                        html += ' onchange="confirmNote(' + note.id + ')">';
                        html += '<label for="modal_confirm_' + note.id + '">確認しました</label>';
                        html += '</div>';
                        if (note.guardian_confirmed && note.guardian_confirmed_at) {
                            html += '<span class="confirmation-date">確認日時: ' + note.confirmed_time + '</span>';
                        }
                        html += '</div>';
                        html += '</div>';
                    });
                    document.getElementById('modalNoteContent').innerHTML = html;

                    // モーダルを表示
                    document.getElementById('noteModal').classList.add('show');
                } else {
                    alert('連絡帳が見つかりませんでした');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('連絡帳の取得に失敗しました');
            });
    }

    // モーダルを閉じる
    function closeNoteModal() {
        document.getElementById('noteModal').classList.remove('show');
    }

    // モーダル外をクリックしたら閉じる
    document.getElementById('noteModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeNoteModal();
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

    // メニュードロップダウンの開閉
    function toggleMenu() {
        document.getElementById('menuDropdown').classList.toggle('show');
    }

    // メニュー外をクリックしたら閉じる
    window.onclick = function(event) {
        if (!event.target.matches('.menu-btn')) {
            const dropdowns = document.getElementsByClassName('menu-content');
            for (let i = 0; i < dropdowns.length; i++) {
                const openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }
    </script>
</body>
</html>
