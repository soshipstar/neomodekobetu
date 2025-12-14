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

<!-- 新着チャットメッセージ通知 -->
<?php if ($totalUnreadMessages > 0): ?>
    <div class="notification-banner" style="border-left-color: var(--apple-blue);">
        <div class="notification-header" style="color: var(--apple-blue);">
            💬 新着メッセージがあります（<?= $totalUnreadMessages ?>件）
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
                    <div class="notification-deadline" style="color: var(--apple-blue);">
                        最新: <?= date('Y年n月j日 H:i', strtotime($chatRoom['last_message_at'])) ?>
                    </div>
                </div>
                <div class="notification-action">
                    <a href="chat.php?room_id=<?= $chatRoom['room_id'] ?>" class="notification-btn" style="background: var(--apple-blue);">
                        チャットを開く
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
            ⚠️ 提出期限が近いかけはしがあります
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
            📝 未提出のかけはしがあります
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
            ⚠️ 提出期限が過ぎた提出物があります
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
                               style="color: var(--apple-green); text-decoration: underline; font-size: var(--text-footnote);"
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
                               style="color: var(--apple-green); text-decoration: underline; font-size: var(--text-footnote);"
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
                               style="color: var(--apple-green); text-decoration: underline; font-size: var(--text-footnote);"
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
        <h2>📅 <?= $year ?>年 <?= $month ?>月のカレンダー</h2>
        <div class="calendar-nav">
            <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>">← 前月</a>
            <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>">今月</a>
            <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>">次月 →</a>
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

            if (isset($holidayDates[$currentDate])) {
                echo "<div class='holiday-label'>" . htmlspecialchars($holidayDates[$currentDate]['name']) . "</div>";
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
                        echo "<span class='schedule-marker'>👤</span>";
                        echo htmlspecialchars($schedule['student_name']) . "さん活動日（連絡帳なし）";
                        echo "</div>";
                    } elseif (!$isPast) {
                        echo "<div class='schedule-label'>";
                        echo "<span class='schedule-marker'>👤</span>";
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
            <div class="legend-box" style="background: var(--apple-bg-secondary); border: 1px solid var(--apple-gray-5);"></div>
            <span>休日</span>
        </div>
        <div class="legend-item">
            <div class="legend-box" style="background: rgba(52, 199, 89, 0.1); border: 2px solid var(--apple-green);"></div>
            <span>今日</span>
        </div>
        <div class="legend-item">
            <span class="event-marker" style="background: var(--apple-green);"></span>
            <span>イベント</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--apple-green); font-weight: 600;">👤</span>
            <span>活動予定日（未来）</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--apple-green); font-weight: 600;">📝</span>
            <span>連絡帳あり（確認済み）</span>
        </div>
        <div class="legend-item">
            <span style="color: var(--apple-red); font-weight: 600;">📝</span>
            <span>連絡帳あり（未確認）</span>
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

            <?php if (empty($notesData[$student['id']])): ?>
                <div class="no-notes">
                    まだ連絡帳が送信されていません
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
            <h2>📝 連絡帳</h2>
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
        'all': '全体', 'elementary': '小学生', 'junior_high_school': '中高生',
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
        html += '<div class="event-detail-section"><h4>対象者</h4><p>' + (targetAudienceLabels[eventData.target_audience] || '全体') + '</p></div>';
    }
    if (html === '') html = '<div class="no-data">詳細情報はありません</div>';
    document.getElementById('eventModalContent').innerHTML = html;
    document.getElementById('eventModal').classList.add('show');
}

function closeEventModal() { document.getElementById('eventModal').classList.remove('show'); }
function closeNoteModal() { document.getElementById('noteModal').classList.remove('show'); }

document.getElementById('eventModal').addEventListener('click', function(e) { if (e.target === this) closeEventModal(); });
document.getElementById('noteModal').addEventListener('click', function(e) { if (e.target === this) closeNoteModal(); });

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
