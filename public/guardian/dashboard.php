<?php
/**
 * 保護者用トップページ
 * 送信された活動記録を表示
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

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
$classroomId = $classroom['id'] ?? null;

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
if ($classroomId === null) {
    $stmt = $pdo->prepare("
        SELECT holiday_date, holiday_name, holiday_type
        FROM holidays
        WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?
    ");
    $stmt->execute([$year, $month]);
} else {
    $stmt = $pdo->prepare("
        SELECT holiday_date, holiday_name, holiday_type
        FROM holidays
        WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? AND classroom_id = ?
    ");
    $stmt->execute([$year, $month, $classroomId]);
}
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
$holidayDates = [];
foreach ($holidays as $holiday) {
    $holidayDates[$holiday['holiday_date']] = [
        'name' => $holiday['holiday_name'],
        'type' => $holiday['holiday_type']
    ];
}

// この月のイベントを取得
if ($classroomId === null) {
    $stmt = $pdo->prepare("
        SELECT id, event_date, event_name, event_description, guardian_message, target_audience, event_color
        FROM events
        WHERE YEAR(event_date) = ? AND MONTH(event_date) = ?
    ");
    $stmt->execute([$year, $month]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, event_date, event_name, event_description, guardian_message, target_audience, event_color
        FROM events
        WHERE YEAR(event_date) = ? AND MONTH(event_date) = ? AND classroom_id = ?
    ");
    $stmt->execute([$year, $month, $classroomId]);
}
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
        'guardian_message' => $event['guardian_message'],
        'target_audience' => $event['target_audience'],
        'color' => $event['event_color']
    ];
}

// この月の学校休業日活動を取得
$schoolHolidayActivities = [];
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT activity_date
            FROM school_holiday_activities
            WHERE classroom_id = ? AND YEAR(activity_date) = ? AND MONTH(activity_date) = ?
        ");
        $stmt->execute([$classroomId, $year, $month]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $schoolHolidayActivities[$row['activity_date']] = true;
        }
    } catch (Exception $e) {
        error_log("Error fetching school holiday activities: " . $e->getMessage());
    }
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

// 未読チャットメッセージを取得
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
    error_log("Error fetching unread chat messages: " . $e->getMessage());
}
$totalUnreadMessages = array_sum(array_column($unreadChatMessages, 'unread_count'));

// 未提出かけはしを取得
$pendingKakehashi = [];
$urgentKakehashi = [];
$overdueKakehashi = [];
$today = date('Y-m-d');
$oneWeekLater = date('Y-m-d', strtotime('+7 days'));
$oneMonthLater = date('Y-m-d', strtotime('+1 month'));

foreach ($students as $student) {
    try {
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
            AND kp.submission_deadline <= ?
            ORDER BY kp.submission_deadline ASC
        ");
        $stmt->execute([$today, $student['id'], $student['id'], $oneMonthLater]);
        $periods = $stmt->fetchAll();

        foreach ($periods as $period) {
            $daysLeft = $period['days_left'];
            $period['student_name'] = $student['student_name'];
            $period['student_id'] = $student['id'];

            if ($daysLeft < 0) {
                $overdueKakehashi[] = $period;
            } elseif ($daysLeft <= 7) {
                $urgentKakehashi[] = $period;
            } else {
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

        if ($daysLeft < 0) {
            $overdueSubmissions[] = $submission;
        } elseif ($daysLeft <= 3) {
            $urgentSubmissions[] = $submission;
        } else {
            $pendingSubmissions[] = $submission;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching submission requests: " . $e->getMessage());
}

// 各生徒の未確認の連絡帳を取得（ダッシュボードでは未確認のみ表示）
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
                WHERE inote.student_id = ? AND inote.is_sent = 1 AND inote.guardian_confirmed = 0
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

    $isDateHoliday = isset($holidayDates[$currentDate]);

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

// カレンダー表示月の振替活動日と欠席日を取得
$calendarMakeupDays = []; // 振替で追加された活動日
$calendarAbsenceDays = []; // 欠席日
if (!empty($students)) {
    try {
        $studentIds = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $firstDayStr = date('Y-m-d', $firstDay);
        $lastDayStr = date('Y-m-d', $lastDay);

        // 振替活動日を取得（承認済みの振替希望日）
        $stmt = $pdo->prepare("
            SELECT
                an.student_id,
                an.makeup_request_date,
                s.student_name
            FROM absence_notifications an
            INNER JOIN students s ON an.student_id = s.id
            WHERE an.student_id IN ($placeholders)
            AND an.makeup_status = 'approved'
            AND an.makeup_request_date BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($studentIds, [$firstDayStr, $lastDayStr]));
        $makeupDays = $stmt->fetchAll();

        foreach ($makeupDays as $makeup) {
            $date = $makeup['makeup_request_date'];
            if (!isset($calendarMakeupDays[$date])) {
                $calendarMakeupDays[$date] = [];
            }
            $calendarMakeupDays[$date][] = [
                'student_id' => $makeup['student_id'],
                'student_name' => $makeup['student_name']
            ];
        }

        // 欠席日を取得（欠席連絡があり、拒否されていないもの）
        $stmt = $pdo->prepare("
            SELECT
                an.student_id,
                an.absence_date,
                an.reason,
                s.student_name
            FROM absence_notifications an
            INNER JOIN students s ON an.student_id = s.id
            WHERE an.student_id IN ($placeholders)
            AND an.absence_date BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($studentIds, [$firstDayStr, $lastDayStr]));
        $absenceDays = $stmt->fetchAll();

        foreach ($absenceDays as $absence) {
            $date = $absence['absence_date'];
            if (!isset($calendarAbsenceDays[$date])) {
                $calendarAbsenceDays[$date] = [];
            }
            $calendarAbsenceDays[$date][] = [
                'student_id' => $absence['student_id'],
                'student_name' => $absence['student_name'],
                'reason' => $absence['reason']
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching makeup/absence days: " . $e->getMessage());
    }
}

// カレンダー表示月の追加利用日を取得
$calendarAdditionalDays = [];
if (!empty($students)) {
    try {
        $studentIds = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $firstDayStr = date('Y-m-d', $firstDay);
        $lastDayStr = date('Y-m-d', $lastDay);

        $stmt = $pdo->prepare("
            SELECT
                au.student_id,
                au.usage_date,
                s.student_name
            FROM additional_usages au
            INNER JOIN students s ON au.student_id = s.id
            WHERE au.student_id IN ($placeholders)
            AND au.usage_date BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($studentIds, [$firstDayStr, $lastDayStr]));
        $additionalDays = $stmt->fetchAll();

        foreach ($additionalDays as $additional) {
            $date = $additional['usage_date'];
            if (!isset($calendarAdditionalDays[$date])) {
                $calendarAdditionalDays[$date] = [];
            }
            $calendarAdditionalDays[$date][] = [
                'student_id' => $additional['student_id'],
                'student_name' => $additional['student_name']
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching additional usage days: " . $e->getMessage());
    }
}

// 保留中の面談予約を取得
$pendingMeetingRequests = [];
$confirmedMeetings = [];
try {
    // 回答待ちの面談予約
    $stmt = $pdo->prepare("
        SELECT mr.*, s.student_name, u.full_name as staff_name
        FROM meeting_requests mr
        INNER JOIN students s ON mr.student_id = s.id
        LEFT JOIN users u ON mr.staff_id = u.id
        WHERE mr.guardian_id = ? AND mr.status IN ('pending', 'staff_counter')
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([$guardianId]);
    $pendingMeetingRequests = $stmt->fetchAll();

    // 確定済みの面談（未実施）
    $stmt = $pdo->prepare("
        SELECT mr.*, s.student_name, u.full_name as staff_name
        FROM meeting_requests mr
        INNER JOIN students s ON mr.student_id = s.id
        LEFT JOIN users u ON mr.staff_id = u.id
        WHERE mr.guardian_id = ? AND mr.status = 'confirmed' AND mr.is_completed = 0
        AND mr.confirmed_date >= CURDATE()
        ORDER BY mr.confirmed_date ASC
    ");
    $stmt->execute([$guardianId]);
    $confirmedMeetings = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching meeting requests: " . $e->getMessage());
}

// 未確認の個別支援計画書を取得
$pendingSupportPlans = [];
$signaturePendingPlans = [];
foreach ($students as $student) {
    try {
        // 確認依頼中の計画案（is_draft=0, is_official=0）
        $stmt = $pdo->prepare("
            SELECT
                isp.id,
                isp.student_name,
                isp.created_date,
                isp.is_official,
                isp.guardian_confirmed,
                isp.guardian_review_comment,
                isp.guardian_review_comment_at
            FROM individual_support_plans isp
            WHERE isp.student_id = ?
            AND isp.is_draft = 0
            AND isp.is_official = 0
            AND (isp.guardian_review_comment IS NULL OR isp.guardian_review_comment = '')
            AND isp.guardian_review_comment_at IS NULL
            ORDER BY isp.created_date DESC
        ");
        $stmt->execute([$student['id']]);
        $plans = $stmt->fetchAll();
        foreach ($plans as $plan) {
            $plan['student_id'] = $student['id'];
            $pendingSupportPlans[] = $plan;
        }

        // 署名待ちの正式版（is_official=1, guardian_confirmed=0）
        $stmt = $pdo->prepare("
            SELECT
                isp.id,
                isp.student_name,
                isp.created_date,
                isp.is_official,
                isp.guardian_confirmed
            FROM individual_support_plans isp
            WHERE isp.student_id = ?
            AND isp.is_official = 1
            AND isp.guardian_confirmed = 0
            ORDER BY isp.created_date DESC
        ");
        $stmt->execute([$student['id']]);
        $officialPlans = $stmt->fetchAll();
        foreach ($officialPlans as $plan) {
            $plan['student_id'] = $student['id'];
            $signaturePendingPlans[] = $plan;
        }
    } catch (Exception $e) {
        error_log("Error fetching support plans for student " . $student['id'] . ": " . $e->getMessage());
    }
}

// 未確認のモニタリング表を取得
$pendingMonitoringRecords = [];
$signaturePendingMonitoring = [];
foreach ($students as $student) {
    try {
        // 確認待ちのモニタリング表（is_draft=0, is_official=0 または is_official未設定）
        $stmt = $pdo->prepare("
            SELECT
                mr.id,
                mr.student_id,
                mr.monitoring_date,
                mr.is_official,
                mr.guardian_confirmed,
                s.student_name
            FROM monitoring_records mr
            INNER JOIN students s ON mr.student_id = s.id
            WHERE mr.student_id = ?
            AND mr.is_draft = 0
            AND (mr.is_official = 0 OR mr.is_official IS NULL)
            AND (mr.guardian_confirmed = 0 OR mr.guardian_confirmed IS NULL)
            ORDER BY mr.monitoring_date DESC
        ");
        $stmt->execute([$student['id']]);
        $records = $stmt->fetchAll();
        foreach ($records as $record) {
            $pendingMonitoringRecords[] = $record;
        }

        // 署名待ちのモニタリング表（is_official=1, guardian_confirmed=0）
        $stmt = $pdo->prepare("
            SELECT
                mr.id,
                mr.student_id,
                mr.monitoring_date,
                mr.is_official,
                mr.guardian_confirmed,
                s.student_name
            FROM monitoring_records mr
            INNER JOIN students s ON mr.student_id = s.id
            WHERE mr.student_id = ?
            AND mr.is_official = 1
            AND (mr.guardian_confirmed = 0 OR mr.guardian_confirmed IS NULL)
            ORDER BY mr.monitoring_date DESC
        ");
        $stmt->execute([$student['id']]);
        $officialRecords = $stmt->fetchAll();
        foreach ($officialRecords as $record) {
            $signaturePendingMonitoring[] = $record;
        }
    } catch (Exception $e) {
        error_log("Error fetching monitoring records for student " . $student['id'] . ": " . $e->getMessage());
    }
}

// カレンダー用の確定済み面談を取得
$calendarMeetings = [];
try {
    $firstDayStr = date('Y-m-d', $firstDay);
    $lastDayStr = date('Y-m-d', $lastDay);

    $stmt = $pdo->prepare("
        SELECT mr.*, s.student_name, u.full_name as staff_name
        FROM meeting_requests mr
        INNER JOIN students s ON mr.student_id = s.id
        LEFT JOIN users u ON mr.staff_id = u.id
        WHERE mr.guardian_id = ?
        AND mr.status = 'confirmed'
        AND DATE(mr.confirmed_date) BETWEEN ? AND ?
        ORDER BY mr.confirmed_date ASC
    ");
    $stmt->execute([$guardianId, $firstDayStr, $lastDayStr]);
    $meetings = $stmt->fetchAll();

    foreach ($meetings as $meeting) {
        $date = date('Y-m-d', strtotime($meeting['confirmed_date']));
        if (!isset($calendarMeetings[$date])) {
            $calendarMeetings[$date] = [];
        }
        $calendarMeetings[$date][] = [
            'id' => $meeting['id'],
            'student_name' => $meeting['student_name'],
            'staff_name' => $meeting['staff_name'],
            'purpose' => $meeting['purpose'],
            'purpose_detail' => $meeting['purpose_detail'],
            'meeting_notes' => $meeting['meeting_notes'] ?? '',
            'time' => date('H:i', strtotime($meeting['confirmed_date'])),
            'confirmed_date' => $meeting['confirmed_date'],
            'is_completed' => $meeting['is_completed']
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching calendar meetings: " . $e->getMessage());
}

// 学年表示用のラベル
function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学生',
        'junior_high' => '中学生',
        'high_school' => '高校生'
    ];
    return $labels[$gradeLevel] ?? '';
}

// ページ開始
$currentPage = 'dashboard';
renderPageStart('guardian', $currentPage, 'ダッシュボード', [
    'classroom' => $classroom
]);
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">連絡帳ダッシュボード</h1>
        <p class="page-subtitle"><?= $classroom ? htmlspecialchars($classroom['classroom_name']) : '' ?></p>
    </div>
</div>

<!-- 回答待ちの面談予約通知 -->
<?php if (!empty($pendingMeetingRequests)): ?>
    <div class="notification-banner" style="border-left-color: var(--md-purple);">
        <div class="notification-header" style="color: var(--md-purple);">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">calendar_month</span> 回答待ちの面談予約があります（<?= count($pendingMeetingRequests) ?>件）
        </div>
        <?php foreach ($pendingMeetingRequests as $meeting): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($meeting['student_name']) ?>さんの面談
                    </div>
                    <div class="notification-period">
                        目的: <?= htmlspecialchars($meeting['purpose']) ?>
                    </div>
                    <div class="notification-deadline" style="color: var(--md-purple);">
                        <?= $meeting['status'] === 'staff_counter' ? 'スタッフから新たな日程が提案されました' : '日程を選択してください' ?>
                    </div>
                </div>
                <div class="notification-action">
                    <a href="meeting_response.php?request_id=<?= $meeting['id'] ?>" class="notification-btn" style="background: var(--md-purple);">
                        回答する
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 確定済みの面談予定 -->
<?php if (!empty($confirmedMeetings)): ?>
    <div class="notification-banner" style="border-left-color: var(--md-green);">
        <div class="notification-header" style="color: var(--md-green);">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event_available</span> 予定されている面談があります（<?= count($confirmedMeetings) ?>件）
        </div>
        <?php foreach ($confirmedMeetings as $meeting): ?>
            <?php
            $meetingDate = strtotime($meeting['confirmed_date']);
            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'];
            $dateStr = date('Y年n月j日（', $meetingDate) . $dayOfWeek[date('w', $meetingDate)] . date('）H:i', $meetingDate);
            ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($meeting['student_name']) ?>さんの面談
                    </div>
                    <div class="notification-period">
                        目的: <?= htmlspecialchars($meeting['purpose']) ?>
                    </div>
                    <div class="notification-deadline" style="color: var(--md-green); font-weight: 600;">
                        <?= $dateStr ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 新着チャットメッセージ通知 -->
<?php if ($totalUnreadMessages > 0): ?>
    <div class="notification-banner" style="border-left-color: var(--md-blue);">
        <div class="notification-header" style="color: var(--md-blue);">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> 新着メッセージがあります（<?= $totalUnreadMessages ?>件）
        </div>
        <?php foreach ($unreadChatMessages as $chatRoom): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($chatRoom['student_name']) ?>さんのチャット
                    </div>
                    <div class="notification-period">
                        未読メッセージ: <?= $chatRoom['unread_count'] ?>件
                    </div>
                    <div class="notification-deadline" style="color: var(--md-blue);">
                        最新: <?= date('Y年n月j日 H:i', strtotime($chatRoom['last_message_at'])) ?>
                    </div>
                </div>
                <div class="notification-action">
                    <a href="chat.php?room_id=<?= $chatRoom['room_id'] ?>" class="notification-btn" style="background: var(--md-blue);">
                        チャットを開く
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 確認依頼中の個別支援計画書通知 -->
<?php if (!empty($pendingSupportPlans)): ?>
    <div class="notification-banner" style="border-left-color: var(--md-purple);">
        <div class="notification-header" style="color: var(--md-purple);">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 確認待ちの個別支援計画書があります（<?= count($pendingSupportPlans) ?>件）
        </div>
        <?php foreach ($pendingSupportPlans as $plan): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($plan['student_name']) ?>さんの個別支援計画書（案）
                    </div>
                    <div class="notification-period">
                        作成日: <?= date('Y年n月j日', strtotime($plan['created_date'])) ?>
                    </div>
                    <div class="notification-deadline" style="color: var(--md-purple);">
                        内容をご確認のうえ、「確認しました」ボタンを押してください
                    </div>
                </div>
                <div class="notification-action">
                    <a href="support_plans.php?student_id=<?= $plan['student_id'] ?>&plan_id=<?= $plan['id'] ?>" class="notification-btn" style="background: var(--md-purple);">
                        計画書を確認
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 署名待ちの正式版個別支援計画書通知 -->
<?php if (!empty($signaturePendingPlans)): ?>
    <div class="notification-banner" style="border-left-color: var(--md-green);">
        <div class="notification-header" style="color: var(--md-green);">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">draw</span> 署名待ちの個別支援計画書があります（<?= count($signaturePendingPlans) ?>件）
        </div>
        <?php foreach ($signaturePendingPlans as $plan): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($plan['student_name']) ?>さんの個別支援計画書（正式版）
                    </div>
                    <div class="notification-period">
                        作成日: <?= date('Y年n月j日', strtotime($plan['created_date'])) ?>
                    </div>
                    <div class="notification-deadline" style="color: var(--md-green);">
                        次回の面談時に署名をお願いいたします
                    </div>
                </div>
                <div class="notification-action">
                    <a href="support_plans.php?student_id=<?= $plan['student_id'] ?>&plan_id=<?= $plan['id'] ?>" class="notification-btn" style="background: var(--md-green);">
                        計画書を見る
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 確認待ちのモニタリング表通知 -->
<?php if (!empty($pendingMonitoringRecords)): ?>
    <div class="notification-banner" style="border-left-color: #17a2b8;">
        <div class="notification-header" style="color: #17a2b8;">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> 確認待ちのモニタリング表があります（<?= count($pendingMonitoringRecords) ?>件）
        </div>
        <?php foreach ($pendingMonitoringRecords as $record): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($record['student_name']) ?>さんのモニタリング表
                    </div>
                    <div class="notification-period">
                        モニタリング日: <?= date('Y年n月j日', strtotime($record['monitoring_date'])) ?>
                    </div>
                    <div class="notification-deadline" style="color: #17a2b8;">
                        内容をご確認ください
                    </div>
                </div>
                <div class="notification-action">
                    <a href="monitoring.php?student_id=<?= $record['student_id'] ?>&record_id=<?= $record['id'] ?>" class="notification-btn" style="background: #17a2b8;">
                        モニタリング表を確認
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 署名待ちのモニタリング表通知 -->
<?php if (!empty($signaturePendingMonitoring)): ?>
    <div class="notification-banner" style="border-left-color: #20c997;">
        <div class="notification-header" style="color: #20c997;">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">draw</span> 署名待ちのモニタリング表があります（<?= count($signaturePendingMonitoring) ?>件）
        </div>
        <?php foreach ($signaturePendingMonitoring as $record): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($record['student_name']) ?>さんのモニタリング表（正式版）
                    </div>
                    <div class="notification-period">
                        モニタリング日: <?= date('Y年n月j日', strtotime($record['monitoring_date'])) ?>
                    </div>
                    <div class="notification-deadline" style="color: #20c997;">
                        次回の面談時に署名をお願いいたします
                    </div>
                </div>
                <div class="notification-action">
                    <a href="monitoring.php?student_id=<?= $record['student_id'] ?>&record_id=<?= $record['id'] ?>" class="notification-btn" style="background: #20c997;">
                        モニタリング表を見る
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
                        <?= htmlspecialchars($kakehashi['student_name']) ?>さん
                    </div>
                    <div class="notification-period">
                        対象期間: <?= date('Y年n月j日', strtotime($kakehashi['start_date'])) ?> ～ <?= date('Y年n月j日', strtotime($kakehashi['end_date'])) ?>
                    </div>
                    <div class="notification-deadline overdue">
                        提出期限: <?= date('Y年n月j日', strtotime($kakehashi['submission_deadline'])) ?>
                        （<?= abs($kakehashi['days_left']) ?>日経過）
                    </div>
                </div>
                <div class="notification-action">
                    <a href="kakehashi.php?student_id=<?= $kakehashi['student_id'] ?>&period_id=<?= $kakehashi['period_id'] ?>" class="notification-btn">
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
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">warning</span> 提出期限が近いかけはしがあります
        </div>
        <?php foreach ($urgentKakehashi as $kakehashi): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($kakehashi['student_name']) ?>さん
                    </div>
                    <div class="notification-period">
                        対象期間: <?= date('Y年n月j日', strtotime($kakehashi['start_date'])) ?> ～ <?= date('Y年n月j日', strtotime($kakehashi['end_date'])) ?>
                    </div>
                    <div class="notification-deadline urgent">
                        提出期限: <?= date('Y年n月j日', strtotime($kakehashi['submission_deadline'])) ?>
                        （残り<?= $kakehashi['days_left'] ?>日）
                    </div>
                </div>
                <div class="notification-action">
                    <a href="kakehashi.php?student_id=<?= $kakehashi['student_id'] ?>&period_id=<?= $kakehashi['period_id'] ?>" class="notification-btn">
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
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 未提出のかけはしがあります
        </div>
        <?php foreach ($pendingKakehashi as $kakehashi): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($kakehashi['student_name']) ?>さん
                    </div>
                    <div class="notification-period">
                        対象期間: <?= date('Y年n月j日', strtotime($kakehashi['start_date'])) ?> ～ <?= date('Y年n月j日', strtotime($kakehashi['end_date'])) ?>
                    </div>
                    <div class="notification-deadline pending">
                        提出期限: <?= date('Y年n月j日', strtotime($kakehashi['submission_deadline'])) ?>
                        （残り<?= $kakehashi['days_left'] ?>日）
                    </div>
                </div>
                <div class="notification-action">
                    <a href="kakehashi.php?student_id=<?= $kakehashi['student_id'] ?>&period_id=<?= $kakehashi['period_id'] ?>" class="notification-btn">
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
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">warning</span> 提出期限が過ぎた提出物があります
        </div>
        <?php foreach ($overdueSubmissions as $submission): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($submission['student_name']) ?>さん
                    </div>
                    <div class="notification-period">
                        件名: <?= htmlspecialchars($submission['title']) ?>
                    </div>
                    <?php if ($submission['description']): ?>
                        <div class="notification-period" style="font-size: var(--text-footnote);">
                            <?= nl2br(htmlspecialchars($submission['description'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="notification-deadline overdue">
                        提出期限: <?= date('Y年n月j日', strtotime($submission['due_date'])) ?>
                        （<?= abs($submission['days_left']) ?>日経過）
                    </div>
                    <?php if ($submission['attachment_path']): ?>
                        <div style="margin-top: 10px;">
                            <a href="../<?= htmlspecialchars($submission['attachment_path']) ?>"
                               style="color: var(--md-green); text-decoration: underline; font-size: var(--text-footnote);"
                               download="<?= htmlspecialchars($submission['attachment_original_name']) ?>">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">attach_file</span> <?= htmlspecialchars($submission['attachment_original_name']) ?>
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
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">notifications</span> 提出期限が近い提出物があります
        </div>
        <?php foreach ($urgentSubmissions as $submission): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($submission['student_name']) ?>さん
                    </div>
                    <div class="notification-period">
                        件名: <?= htmlspecialchars($submission['title']) ?>
                    </div>
                    <?php if ($submission['description']): ?>
                        <div class="notification-period" style="font-size: var(--text-footnote);">
                            <?= nl2br(htmlspecialchars($submission['description'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="notification-deadline urgent">
                        提出期限: <?= date('Y年n月j日', strtotime($submission['due_date'])) ?>
                        （残り<?= $submission['days_left'] ?>日）
                    </div>
                    <?php if ($submission['attachment_path']): ?>
                        <div style="margin-top: 10px;">
                            <a href="../<?= htmlspecialchars($submission['attachment_path']) ?>"
                               style="color: var(--md-green); text-decoration: underline; font-size: var(--text-footnote);"
                               download="<?= htmlspecialchars($submission['attachment_original_name']) ?>">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">attach_file</span> <?= htmlspecialchars($submission['attachment_original_name']) ?>
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
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 提出が必要な提出物があります
        </div>
        <?php foreach ($pendingSubmissions as $submission): ?>
            <div class="notification-item">
                <div class="notification-info">
                    <div class="notification-student">
                        <?= htmlspecialchars($submission['student_name']) ?>さん
                    </div>
                    <div class="notification-period">
                        件名: <?= htmlspecialchars($submission['title']) ?>
                    </div>
                    <?php if ($submission['description']): ?>
                        <div class="notification-period" style="font-size: var(--text-footnote);">
                            <?= nl2br(htmlspecialchars($submission['description'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="notification-deadline pending">
                        提出期限: <?= date('Y年n月j日', strtotime($submission['due_date'])) ?>
                        （残り<?= $submission['days_left'] ?>日）
                    </div>
                    <?php if ($submission['attachment_path']): ?>
                        <div style="margin-top: 10px;">
                            <a href="../<?= htmlspecialchars($submission['attachment_path']) ?>"
                               style="color: var(--md-green); text-decoration: underline; font-size: var(--text-footnote);"
                               download="<?= htmlspecialchars($submission['attachment_original_name']) ?>">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">attach_file</span> <?= htmlspecialchars($submission['attachment_original_name']) ?>
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
<div class="calendar-section" id="calendar">
    <div class="calendar-header">
        <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> <?= $year ?>年 <?= $month ?>月のカレンダー</h2>
        <div class="calendar-nav">
            <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>#calendar">← 前月</a>
            <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>#calendar">今月</a>
            <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>#calendar">次月 →</a>
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

        $startDayOfWeek = date('w', $firstDay);
        for ($i = 0; $i < $startDayOfWeek; $i++) {
            echo "<div class='calendar-day empty'></div>";
        }

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

            if (array_key_exists($currentDate, $holidayDates)) {
                echo "<div class='holiday-label'>" . htmlspecialchars($holidayDates[$currentDate]['name']) . "</div>";
            } else {
                // 休日でない場合、活動種別を表示
                if (array_key_exists($currentDate, $schoolHolidayActivities)) {
                    echo "<div class='activity-type-label school-holiday-activity'>学休</div>";
                } else {
                    echo "<div class='activity-type-label weekday-activity'>平日</div>";
                }
            }

            if (isset($eventDates[$currentDate])) {
                foreach ($eventDates[$currentDate] as $event) {
                    $eventJson = htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8');
                    echo "<div class='event-label clickable' onclick='event.stopPropagation(); showEventModal(" . $eventJson . ");'>";
                    echo "<span class='event-marker' style='background: " . htmlspecialchars($event['color']) . ";'></span>";
                    echo htmlspecialchars($event['name']);
                    echo "</div>";
                }
            }

            if (isset($calendarSchedules[$currentDate]) && !empty($calendarSchedules[$currentDate])) {
                foreach ($calendarSchedules[$currentDate] as $schedule) {
                    $isPast = strtotime($currentDate) < strtotime(date('Y-m-d'));
                    $hasNote = isset($calendarNotes[$currentDate]) && !empty($calendarNotes[$currentDate]);

                    if ($isPast && !$hasNote) {
                        echo "<div class='schedule-label no-note' onclick='showNoteModal(\"$currentDate\")'>";
                        echo "<span class='schedule-marker'><span class='material-symbols-outlined' style='font-size: 18px; vertical-align: middle;'>person</span></span>";
                        echo htmlspecialchars($schedule['student_name']) . "さん活動日（連絡帳なし）";
                        echo "</div>";
                    } elseif (!$isPast) {
                        echo "<div class='schedule-label'>";
                        echo "<span class='schedule-marker'><span class='material-symbols-outlined' style='font-size: 18px; vertical-align: middle;'>person</span></span>";
                        echo htmlspecialchars($schedule['student_name']) . "さん活動予定日";
                        echo "</div>";
                    }
                }
            }

            if (isset($calendarNotes[$currentDate]) && !empty($calendarNotes[$currentDate])) {
                foreach ($calendarNotes[$currentDate] as $noteInfo) {
                    $isPast = strtotime($currentDate) < strtotime(date('Y-m-d'));
                    $isConfirmed = $noteInfo['guardian_confirmed'];

                    if ($isPast) {
                        $class = $isConfirmed ? 'note-label confirmed-past' : 'note-label unconfirmed-past';
                        $text = $isConfirmed ? '活動日（確認済み）' : '活動日（要確認）';
                    } else {
                        $class = $isConfirmed ? 'note-label confirmed' : 'note-label unconfirmed';
                        $text = $isConfirmed ? '連絡帳あり' : '連絡帳あり（確認してください）';
                    }

                    echo "<div class='$class' onclick='showNoteModal(\"$currentDate\")'>";
                    echo "<span class='note-marker'><span class='material-symbols-outlined' style='font-size: 18px; vertical-align: middle;'>edit_note</span></span>";
                    echo htmlspecialchars($noteInfo['student_name']) . "さん" . htmlspecialchars($text);
                    echo "</div>";
                }
            }

            // 振替活動日を表示
            if (isset($calendarMakeupDays[$currentDate]) && !empty($calendarMakeupDays[$currentDate])) {
                foreach ($calendarMakeupDays[$currentDate] as $makeupInfo) {
                    echo "<div class='makeup-label'>";
                    echo "<span class='makeup-marker'><span class='material-symbols-outlined' style='font-size: 18px; vertical-align: middle;'>sync</span></span>";
                    echo htmlspecialchars($makeupInfo['student_name']) . "さん振替活動日";
                    echo "</div>";
                }
            }

            // 欠席日を表示
            if (isset($calendarAbsenceDays[$currentDate]) && !empty($calendarAbsenceDays[$currentDate])) {
                foreach ($calendarAbsenceDays[$currentDate] as $absenceInfo) {
                    echo "<div class='absence-label'>";
                    echo "<span class='absence-marker'><span class='material-symbols-outlined' style='font-size: 18px; vertical-align: middle;'>event_busy</span></span>";
                    echo htmlspecialchars($absenceInfo['student_name']) . "さん欠席";
                    echo "</div>";
                }
            }

            // 追加利用日を表示
            if (isset($calendarAdditionalDays[$currentDate]) && !empty($calendarAdditionalDays[$currentDate])) {
                foreach ($calendarAdditionalDays[$currentDate] as $additionalInfo) {
                    echo "<div class='additional-label'>";
                    echo "<span class='additional-marker'><span class='material-symbols-outlined' style='font-size: 18px; vertical-align: middle;'>add</span></span>";
                    echo htmlspecialchars($additionalInfo['student_name']) . "さん追加利用";
                    echo "</div>";
                }
            }

            // 面談予定を表示
            if (isset($calendarMeetings[$currentDate]) && !empty($calendarMeetings[$currentDate])) {
                foreach ($calendarMeetings[$currentDate] as $meetingInfo) {
                    $meetingJson = htmlspecialchars(json_encode($meetingInfo, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    echo "<div class='meeting-label clickable' onclick='event.stopPropagation(); showMeetingModal(" . $meetingJson . ");' style='background: rgba(175, 82, 222, 0.15); color: var(--md-purple); padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-top: 2px; cursor: pointer;'>";
                    echo "<span class='material-symbols-outlined' style='font-size: 14px; vertical-align: middle;'>calendar_month</span> ";
                    echo htmlspecialchars($meetingInfo['time']) . " 面談";
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
            <div class="legend-box" style="background: var(--md-bg-secondary); border: 1px solid var(--md-gray-5);"></div>
            <span>休日</span>
        </div>
        <div class="legend-item">
            <div class="legend-box" style="background: rgba(52, 199, 89, 0.1); border: 2px solid var(--md-green);"></div>
            <span>今日</span>
        </div>
        <div class="legend-item">
            <span style="font-size: 12px;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">menu_book</span></span>
            <span>平日活動</span>
        </div>
        <div class="legend-item">
            <span style="font-size: 12px;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">school</span></span>
            <span>学校休業日活動</span>
        </div>
        <div class="legend-item">
            <span class="event-marker" style="background: var(--md-green);"></span>
            <span>イベント</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-green); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span></span>
            <span>活動予定日</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-green); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span></span>
            <span>連絡帳あり（確認済み）</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-red); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span></span>
            <span>連絡帳あり（未確認）</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-blue); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span></span>
            <span>振替活動日</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-red); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event_busy</span></span>
            <span>欠席日</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-green); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">add</span></span>
            <span>追加利用</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--md-purple); font-weight: 600;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">calendar_month</span></span>
            <span>面談予定</span>
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
                <span class="student-name"><?= htmlspecialchars($student['student_name']) ?></span>
                <span class="grade-badge"><?= getGradeLabel($student['grade_level']) ?></span>
            </div>

            <div style="text-align: right; margin-bottom: var(--spacing-md);">
                <a href="communication_logs.php?student_id=<?= $student['id'] ?>" style="color: var(--md-blue); text-decoration: none; font-size: var(--text-footnote);">
                    すべての連絡帳を見る →
                </a>
            </div>
            <?php if (empty($notesData[$student['id']])): ?>
                <div class="no-notes" style="color: var(--md-green);">
                    ✓ 確認が必要な連絡帳はありません
                </div>
            <?php else: ?>
                <?php foreach ($notesData[$student['id']] as $note): ?>
                    <div class="note-item">
                        <div class="note-header">
                            <span class="activity-name"><?= htmlspecialchars($note['activity_name']) ?></span>
                            <span class="note-date">
                                <?= date('Y年n月j日', strtotime($note['record_date'])) ?>
                                （送信: <?= date('H:i', strtotime($note['sent_at'])) ?>）
                            </span>
                        </div>
                        <div class="note-content"><?= htmlspecialchars($note['integrated_content']) ?></div>

                        <div class="confirmation-box">
                            <div class="confirmation-checkbox <?= $note['guardian_confirmed'] ? 'confirmed' : '' ?>">
                                <input
                                    type="checkbox"
                                    id="confirm_<?= $note['id'] ?>"
                                    <?= $note['guardian_confirmed'] ? 'checked disabled' : '' ?>
                                    onchange="confirmNote(<?= $note['id'] ?>)"
                                >
                                <label for="confirm_<?= $note['id'] ?>">確認しました</label>
                            </div>
                            <?php if ($note['guardian_confirmed'] && $note['guardian_confirmed_at']): ?>
                                <span class="confirmation-date">
                                    確認日時: <?= date('Y年n月j日 H:i', strtotime($note['guardian_confirmed_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- 連絡帳詳細モーダル -->
<div id="noteModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeNoteModal()">&times;</button>
        <div class="modal-header">
            <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 連絡帳</h2>
            <div class="modal-date" id="modalDate"></div>
        </div>
        <div id="modalNoteContent"></div>
    </div>
</div>

<!-- イベント詳細モーダル -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeEventModal()">&times;</button>
        <div class="modal-header">
            <h2 id="eventModalTitle">イベント詳細</h2>
        </div>
        <div id="eventModalContent"></div>
    </div>
</div>

<!-- 面談詳細モーダル -->
<div id="meetingModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeMeetingModal()">&times;</button>
        <div class="modal-header">
            <h2 id="meetingModalTitle" style="color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">calendar_month</span> 面談予定</h2>
        </div>
        <div id="meetingModalContent"></div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
function confirmNote(noteId) {
    if (!confirm('この連絡帳を「確認しました」にしてよろしいですか?')) {
        document.getElementById('confirm_' + noteId).checked = false;
        return;
    }

    fetch('confirm_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'note_id=' + noteId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('エラーが発生しました: ' + (data.error || '不明なエラー'));
            document.getElementById('confirm_' + noteId).checked = false;
        }
    })
    .catch(error => {
        alert('通信エラーが発生しました');
        console.error('Error:', error);
        document.getElementById('confirm_' + noteId).checked = false;
    });
}

function showNoteModal(date) {
    fetch('get_notes_by_date.php?date=' + date)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notes && data.notes.length > 0) {
                const dateObj = new Date(date + 'T00:00:00');
                const dateStr = dateObj.getFullYear() + '年' + (dateObj.getMonth() + 1) + '月' + dateObj.getDate() + '日';
                document.getElementById('modalDate').textContent = dateStr;

                let html = '';
                data.notes.forEach((note, index) => {
                    html += '<div class="note-item" style="margin-bottom: ' + (index < data.notes.length - 1 ? '20px' : '0') + ';">';
                    html += '<div class="note-header">';
                    html += '<span class="activity-name">' + escapeHtml(note.activity_name) + '</span>';
                    html += '<span class="note-date">送信: ' + note.sent_time + '</span>';
                    html += '</div>';
                    html += '<div class="note-content">' + escapeHtml(note.integrated_content) + '</div>';
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
                    html += '</div></div>';
                });
                document.getElementById('modalNoteContent').innerHTML = html;
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

function showEventModal(eventData) {
    const targetAudienceLabels = {
        'all': '全体', 'preschool': '未就学児', 'elementary': '小学生',
        'junior_high': '中学生', 'high_school': '高校生',
        'guardian': '保護者', 'other': 'その他'
    };

    document.getElementById('eventModalTitle').textContent = eventData.name || 'イベント詳細';
    let html = '';
    if (eventData.description) {
        html += '<div class="event-detail-section"><h4>説明</h4><p>' + escapeHtml(eventData.description) + '</p></div>';
    }
    if (eventData.guardian_message) {
        html += '<div class="event-detail-section"><h4>保護者・生徒連絡用</h4><p>' + escapeHtml(eventData.guardian_message) + '</p></div>';
    }
    if (eventData.target_audience) {
        const audiences = eventData.target_audience.split(',').map(a => targetAudienceLabels[a.trim()] || a.trim()).join('、');
        html += '<div class="event-detail-section"><h4>対象者</h4><p>' + audiences + '</p></div>';
    }
    if (html === '') html = '<div class="no-data">詳細情報はありません</div>';
    document.getElementById('eventModalContent').innerHTML = html;
    document.getElementById('eventModal').classList.add('show');
}

function closeEventModal() { document.getElementById('eventModal').classList.remove('show'); }
function closeNoteModal() { document.getElementById('noteModal').classList.remove('show'); }
function closeMeetingModal() { document.getElementById('meetingModal').classList.remove('show'); }

function showMeetingModal(meetingData) {
    const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'];
    const date = new Date(meetingData.confirmed_date);
    const dateStr = date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日（' + dayOfWeek[date.getDay()] + '）';
    const timeStr = meetingData.time;

    let html = '<div style="background: rgba(175, 82, 222, 0.1); padding: 16px; border-radius: 8px; margin-bottom: 16px;">';
    html += '<div style="font-size: 18px; font-weight: 600; color: var(--md-purple); margin-bottom: 8px;">' + dateStr + ' ' + timeStr + '</div>';
    html += '<div style="color: var(--text-secondary);">' + escapeHtml(meetingData.student_name) + 'さんの面談</div>';
    html += '</div>';

    html += '<div class="event-detail-section"><h4 style="color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">target</span> 面談目的</h4>';
    html += '<p>' + escapeHtml(meetingData.purpose) + '</p></div>';

    if (meetingData.purpose_detail) {
        html += '<div class="event-detail-section"><h4 style="color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">description</span> 詳細・ご相談内容</h4>';
        html += '<p style="white-space: pre-wrap;">' + escapeHtml(meetingData.purpose_detail) + '</p></div>';
    }

    if (meetingData.staff_name) {
        html += '<div class="event-detail-section"><h4 style="color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> 担当スタッフ</h4>';
        html += '<p>' + escapeHtml(meetingData.staff_name) + '</p></div>';
    }

    // 面談当日の案内
    html += '<div class="event-detail-section" style="background: var(--md-bg-secondary); padding: 16px; border-radius: 8px; border-left: 4px solid var(--md-purple);">';
    html += '<h4 style="color: var(--md-purple); margin-bottom: 12px;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">info</span> 面談当日のご案内</h4>';

    if (meetingData.meeting_notes && meetingData.meeting_notes.trim() !== '') {
        // スタッフが入力した案内を表示
        html += '<p style="white-space: pre-wrap; color: var(--text-secondary); line-height: 1.8; margin: 0;">' + escapeHtml(meetingData.meeting_notes) + '</p>';
    } else {
        // デフォルトの案内を表示
        html += '<ul style="margin: 0; padding-left: 20px; color: var(--text-secondary); line-height: 1.8;">';
        html += '<li>ご予約時間の5分前にはお越しください</li>';
        html += '<li>印鑑をお持ちください（計画書への署名に必要です）</li>';
        html += '<li>ご質問やご相談事項があれば事前にメモをご用意いただくとスムーズです</li>';
        html += '<li>ご都合が悪くなった場合は、お早めにチャットでご連絡ください</li>';
        html += '</ul>';
    }
    html += '</div>';

    document.getElementById('meetingModalContent').innerHTML = html;
    document.getElementById('meetingModal').classList.add('show');
}

document.getElementById('eventModal').addEventListener('click', function(e) { if (e.target === this) closeEventModal(); });
document.getElementById('noteModal').addEventListener('click', function(e) { if (e.target === this) closeNoteModal(); });
document.getElementById('meetingModal').addEventListener('click', function(e) { if (e.target === this) closeMeetingModal(); });

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
