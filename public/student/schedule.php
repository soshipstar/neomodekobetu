<?php
/**
 * 生徒用スケジュールページ
 * 週間計画表、提出物、イベント、休日を統合したカレンダー表示
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 生徒の教室IDを取得（保護者経由）
$stmt = $pdo->prepare("
    SELECT u.classroom_id
    FROM students s
    INNER JOIN users u ON s.guardian_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$classroomId = $stmt->fetchColumn();

// カレンダー表示用
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);

// その月の開始日と終了日
$monthStart = date('Y-m-01', $firstDay);
$monthEnd = date('Y-m-t', $firstDay);

// イベント取得（自分の教室のみ）
$stmt = $pdo->prepare("
    SELECT id, event_date, event_name, event_description, guardian_message, target_audience
    FROM events
    WHERE event_date BETWEEN ? AND ? AND classroom_id = ?
    ORDER BY event_date
");
$stmt->execute([$monthStart, $monthEnd, $classroomId]);
$events = [];
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['event_date']));
    if (!isset($events[$day])) {
        $events[$day] = [];
    }
    $events[$day][] = [
        'type' => 'event',
        'id' => $row['id'],
        'name' => $row['event_name'],
        'date' => $row['event_date'],
        'description' => $row['event_description'],
        'guardian_message' => $row['guardian_message'],
        'target_audience' => $row['target_audience']
    ];
}

// 休日取得（自分の教室のみ）
$stmt = $pdo->prepare("
    SELECT holiday_date, holiday_name, holiday_type
    FROM holidays
    WHERE holiday_date BETWEEN ? AND ? AND classroom_id = ?
    ORDER BY holiday_date
");
$stmt->execute([$monthStart, $monthEnd, $classroomId]);
$holidays = [];
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['holiday_date']));
    if (!isset($holidays[$day])) {
        $holidays[$day] = [];
    }
    $holidays[$day][] = [
        'type' => 'holiday',
        'name' => $row['holiday_name'],
        'holiday_type' => $row['holiday_type']
    ];
}

// 学校休業日活動を取得
$schoolHolidayActivities = [];
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT activity_date
            FROM school_holiday_activities
            WHERE classroom_id = ? AND activity_date BETWEEN ? AND ?
        ");
        $stmt->execute([$classroomId, $monthStart, $monthEnd]);
        while ($row = $stmt->fetch()) {
            $day = date('j', strtotime($row['activity_date']));
            $schoolHolidayActivities[$day] = true;
        }
    } catch (Exception $e) {
        error_log("Error fetching school holiday activities: " . $e->getMessage());
    }
}

// 提出物期限取得（統合）
$submissions = [];

// 1. 週間計画表からの提出物
$stmt = $pdo->prepare("
    SELECT wps.id, wps.submission_item as item, wps.due_date, wps.is_completed
    FROM weekly_plan_submissions wps
    INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
    WHERE wp.student_id = ? AND wps.due_date BETWEEN ? AND ?
    ORDER BY wps.due_date
");
$stmt->execute([$studentId, $monthStart, $monthEnd]);
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['due_date']));
    if (!isset($submissions[$day])) $submissions[$day] = [];
    $submissions[$day][] = ['type' => 'submission', 'item' => $row['item'], 'is_completed' => $row['is_completed'], 'due_date' => $row['due_date']];
}

// 2. 保護者チャット経由の提出物
$stmt = $pdo->prepare("
    SELECT sr.id, sr.title as item, sr.due_date, sr.is_completed
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.due_date BETWEEN ? AND ?
    ORDER BY sr.due_date
");
$stmt->execute([$studentId, $monthStart, $monthEnd]);
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['due_date']));
    if (!isset($submissions[$day])) $submissions[$day] = [];
    $submissions[$day][] = ['type' => 'submission', 'item' => $row['item'], 'is_completed' => $row['is_completed'], 'due_date' => $row['due_date']];
}

// 3. 生徒自身が登録した提出物
$stmt = $pdo->prepare("
    SELECT id, title as item, due_date, is_completed
    FROM student_submissions
    WHERE student_id = ? AND due_date BETWEEN ? AND ?
    ORDER BY due_date
");
$stmt->execute([$studentId, $monthStart, $monthEnd]);
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['due_date']));
    if (!isset($submissions[$day])) $submissions[$day] = [];
    $submissions[$day][] = ['type' => 'submission', 'item' => $row['item'], 'is_completed' => $row['is_completed'], 'due_date' => $row['due_date']];
}

// 週間計画表取得
$stmt = $pdo->prepare("
    SELECT id, week_start_date, plan_data
    FROM weekly_plans
    WHERE student_id = ? AND week_start_date <= ? AND DATE_ADD(week_start_date, INTERVAL 6 DAY) >= ?
    ORDER BY week_start_date
");
$stmt->execute([$studentId, $monthEnd, $monthStart]);
$weeklyPlans = [];
while ($row = $stmt->fetch()) {
    $planData = json_decode($row['plan_data'], true);
    $weekStart = strtotime($row['week_start_date']);
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("+$i days", $weekStart));
        $dayKey = "day_$i";
        if (isset($planData[$dayKey]) && !empty($planData[$dayKey])) {
            $day = date('j', strtotime($date));
            $dateMonth = date('n', strtotime($date));
            $dateYear = date('Y', strtotime($date));
            if ($dateMonth == $month && $dateYear == $year) {
                if (!isset($weeklyPlans[$day])) $weeklyPlans[$day] = [];
                $weeklyPlans[$day][] = ['type' => 'plan', 'value' => $planData[$dayKey], 'date' => $date];
            }
        }
    }
}

// 生徒の活動予定日を取得
$stmt = $pdo->prepare("
    SELECT scheduled_sunday, scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday, scheduled_friday, scheduled_saturday
    FROM students WHERE id = ?
");
$stmt->execute([$studentId]);
$studentSchedule = $stmt->fetch();

$scheduledDays = [];
$dayColumns = [0 => 'scheduled_sunday', 1 => 'scheduled_monday', 2 => 'scheduled_tuesday', 3 => 'scheduled_wednesday', 4 => 'scheduled_thursday', 5 => 'scheduled_friday', 6 => 'scheduled_saturday'];
foreach ($dayColumns as $dayNum => $columnName) {
    if (!empty($studentSchedule[$columnName])) $scheduledDays[] = $dayNum;
}

// カレンダー表示月の全日付について活動予定日を判定
$activitySchedules = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
    $currentDayOfWeek = date('w', strtotime($currentDate));
    $isDateHoliday = isset($holidays[$day]);
    if (!$isDateHoliday && in_array($currentDayOfWeek, $scheduledDays)) {
        $activitySchedules[$day] = ['type' => 'activity', 'date' => $currentDate];
    }
}

// カレンダーデータを統合
$calendarData = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    // 活動種別を判定（休日でない場合のみ）
    $activityType = null;
    if (!isset($holidays[$day])) {
        if (isset($schoolHolidayActivities[$day])) {
            $activityType = 'school_holiday'; // 学校休業日活動
        } else {
            $activityType = 'weekday'; // 平日活動
        }
    }

    $calendarData[$day] = [
        'events' => $events[$day] ?? [],
        'holidays' => $holidays[$day] ?? [],
        'submissions' => $submissions[$day] ?? [],
        'plans' => $weeklyPlans[$day] ?? [],
        'activity' => $activitySchedules[$day] ?? null,
        'activityType' => $activityType
    ];
}

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$_SESSION['user_type'] = 'student';
$_SESSION['full_name'] = $student['student_name'];

// ページ開始
$currentPage = 'schedule';
renderPageStart('student', $currentPage, 'スケジュール');
?>

<style>
.calendar-section {
    background: var(--apple-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.calendar-header h2 {
    color: var(--text-primary);
    font-size: var(--text-title-3);
    margin: 0;
}

.calendar-nav {
    display: flex;
    gap: var(--spacing-sm);
}

.calendar {
    width: 100%;
    border-collapse: collapse;
}

.calendar th {
    padding: var(--spacing-md);
    background: var(--apple-gray-6);
    font-weight: 600;
    color: var(--text-secondary);
    border: 1px solid var(--apple-gray-5);
}

.calendar td {
    padding: var(--spacing-sm);
    border: 1px solid var(--apple-gray-5);
    vertical-align: top;
    height: 120px;
    cursor: pointer;
    transition: background var(--duration-fast) var(--ease-out);
}

.calendar td:hover {
    background: var(--apple-bg-secondary);
}

.calendar td.other-month {
    background: var(--apple-gray-6);
    color: var(--apple-gray-4);
    cursor: default;
}

.calendar .day-number {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: var(--text-callout);
}

.calendar .indicator {
    font-size: 10px;
    padding: 2px 5px;
    border-radius: 3px;
    margin: 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

.calendar .indicator.event { background: var(--apple-purple); color: white; }
.calendar .indicator.holiday { background: var(--apple-red); color: white; }
.calendar .indicator.submission { background: var(--apple-orange); color: white; }
.calendar .indicator.submission-done { background: var(--apple-gray-4); color: white; text-decoration: line-through; }
.calendar .indicator.plan { background: var(--apple-green); color: white; }
.calendar .indicator.activity { background: var(--apple-blue); color: white; font-weight: 600; }
.calendar .indicator.activity-type { font-size: 9px; padding: 1px 4px; }
.calendar .indicator.weekday-activity { background: rgba(52, 199, 89, 0.2); color: var(--apple-green); }
.calendar .indicator.school-holiday-activity { background: rgba(0, 122, 255, 0.2); color: var(--apple-blue); }

.calendar .sunday { color: var(--apple-red); }
.calendar .saturday { color: var(--apple-blue); }
.calendar .today { background: rgba(0, 122, 255, 0.1); }

/* モーダル */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    overflow-y: auto;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--apple-bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: var(--shadow-xl);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 2px solid var(--apple-purple);
}

.modal-header h2 {
    color: var(--apple-purple);
    font-size: var(--text-title-3);
    margin: 0;
}

.modal-section {
    margin-bottom: var(--spacing-lg);
}

.modal-section h3 {
    color: var(--apple-purple);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-gray-6);
    border-radius: var(--radius-sm);
}

.modal-section.holiday h3 { color: var(--apple-red); }
.modal-section.submission h3 { color: var(--apple-orange); }
.modal-section.plan h3 { color: var(--apple-green); }

.modal-item {
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-sm);
    background: var(--apple-gray-6);
    border-radius: var(--radius-sm);
    border-left: 4px solid var(--apple-purple);
}

.modal-item.holiday { border-left-color: var(--apple-red); }
.modal-item.submission { border-left-color: var(--apple-orange); }
.modal-item.plan { border-left-color: var(--apple-green); }

.completion-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: var(--text-caption-1);
    margin-left: 8px;
}

.completion-badge.done { background: var(--apple-green); color: white; }
.completion-badge.pending { background: var(--apple-orange); color: white; }

.no-data {
    text-align: center;
    color: var(--text-secondary);
    padding: var(--spacing-xl);
}

@media (max-width: 768px) {
    .calendar-section { padding: var(--spacing-md); overflow-x: auto; }
    .calendar-header { flex-direction: column; gap: var(--spacing-md); }
    .calendar { min-width: 600px; }
    .calendar th { font-size: var(--text-caption-1); padding: var(--spacing-sm); }
    .calendar td { height: 80px; padding: 5px; font-size: 10px; }
    .calendar .indicator { font-size: 9px; padding: 2px 4px; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">スケジュール</h1>
        <p class="page-subtitle">週間計画、提出物、イベント、休日を確認</p>
    </div>
</div>

<div class="calendar-section">
    <div class="calendar-header">
        <h2><?= $year ?>年<?= $month ?>月</h2>
        <div class="calendar-nav">
            <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-secondary btn-sm">← 前月</a>
            <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-secondary btn-sm">次月 →</a>
        </div>
    </div>

    <table class="calendar">
        <tr>
            <th class="sunday">日</th>
            <th>月</th>
            <th>火</th>
            <th>水</th>
            <th>木</th>
            <th>金</th>
            <th class="saturday">土</th>
        </tr>
        <?php
        $day = 1;
        for ($week = 0; $week < 6; $week++) {
            echo "<tr>";
            for ($dow = 0; $dow < 7; $dow++) {
                if (($week == 0 && $dow < $dayOfWeek) || $day > $daysInMonth) {
                    echo "<td class='other-month'></td>";
                } else {
                    $isToday = ($day == date('j') && $month == date('n') && $year == date('Y'));
                    $class = $isToday ? 'today' : '';
                    $data = $calendarData[$day];
                    $hasData = !empty($data['events']) || !empty($data['holidays']) || !empty($data['submissions']) || !empty($data['plans']) || !empty($data['activity']) || !empty($data['activityType']);
                    $dataJson = htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

                    echo "<td class='$class' onclick='showDetail(\"$dateStr\", $dataJson)'>";
                    $actualDayOfWeek = date('w', strtotime($dateStr));
                    $dayClass = ($actualDayOfWeek == 0) ? 'sunday' : (($actualDayOfWeek == 6) ? 'saturday' : '');
                    echo "<div class='day-number $dayClass'>$day</div>";

                    $count = 0;
                    $maxDisplay = 4;

                    // 休日の場合
                    foreach ($data['holidays'] as $holiday) {
                        if ($count >= $maxDisplay) break;
                        echo "<span class='indicator holiday'>" . htmlspecialchars($holiday['name'], ENT_QUOTES, 'UTF-8') . "</span>";
                        $count++;
                    }

                    // 活動種別を表示（休日でない場合のみ）
                    if (empty($data['holidays']) && !empty($data['activityType']) && $count < $maxDisplay) {
                        if ($data['activityType'] === 'school_holiday') {
                            echo "<span class='indicator activity-type school-holiday-activity'>🏫 学校休業日活動</span>";
                        } else {
                            echo "<span class='indicator activity-type weekday-activity'>📚 平日活動</span>";
                        }
                        $count++;
                    }

                    foreach ($data['events'] as $event) {
                        if ($count >= $maxDisplay) break;
                        echo "<span class='indicator event'>" . htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') . "</span>";
                        $count++;
                    }

                    if (!empty($data['activity']) && $count < $maxDisplay) {
                        echo "<span class='indicator activity'>👤 活動予定日</span>";
                        $count++;
                    }

                    foreach ($data['submissions'] as $sub) {
                        if ($count >= $maxDisplay) break;
                        $subClass = $sub['is_completed'] ? 'submission-done' : 'submission';
                        echo "<span class='indicator $subClass'>📤 " . htmlspecialchars($sub['item'], ENT_QUOTES, 'UTF-8') . "</span>";
                        $count++;
                    }

                    if (!empty($data['plans']) && $count < $maxDisplay) {
                        echo "<span class='indicator plan'>📝 計画</span>";
                        $count++;
                    }

                    echo "</td>";
                    $day++;
                }
            }
            echo "</tr>";
            if ($day > $daysInMonth) break;
        }
        ?>
    </table>
</div>

<!-- 詳細モーダル -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalDate"></h2>
            <button class="btn btn-secondary btn-sm" onclick="closeModal()">閉じる</button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<?php
$inlineJs = <<<JS
function showDetail(dateStr, data) {
    const modal = document.getElementById('detailModal');
    const modalDate = document.getElementById('modalDate');
    const modalBody = document.getElementById('modalBody');

    const date = new Date(dateStr + 'T00:00:00');
    const dateFormatted = date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日';
    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    modalDate.textContent = dateFormatted + '（' + dayNames[date.getDay()] + '）';

    let html = '';

    if (data.holidays && data.holidays.length > 0) {
        html += '<div class="modal-section holiday"><h3>🎌 休日</h3>';
        data.holidays.forEach(h => {
            html += '<div class="modal-item holiday"><div class="modal-item-value">' + escapeHtml(h.name) + '</div></div>';
        });
        html += '</div>';
    } else if (data.activityType) {
        // 休日でない場合は活動種別を表示
        if (data.activityType === 'school_holiday') {
            html += '<div class="modal-section"><h3>🏫 学校休業日活動</h3>';
            html += '<div class="modal-item"><div class="modal-item-value">この日は学校休業日活動日です（夏休み・春休み等）</div></div></div>';
        } else {
            html += '<div class="modal-section plan"><h3>📚 平日活動</h3>';
            html += '<div class="modal-item plan"><div class="modal-item-value">この日は通常の平日活動日です</div></div></div>';
        }
    }

    if (data.events && data.events.length > 0) {
        html += '<div class="modal-section"><h3>📅 イベント</h3>';
        data.events.forEach(e => {
            html += '<div class="modal-item"><div class="modal-item-value">' + escapeHtml(e.name) + '</div></div>';
        });
        html += '</div>';
    }

    if (data.activity) {
        html += '<div class="modal-section"><h3>👤 活動予定日</h3>';
        html += '<div class="modal-item"><div class="modal-item-value">この日はあなたの活動予定日です</div></div></div>';
    }

    if (data.submissions && data.submissions.length > 0) {
        html += '<div class="modal-section submission"><h3>📤 提出物</h3>';
        data.submissions.forEach(s => {
            const itemClass = s.is_completed ? 'submission-done' : 'submission';
            const badgeClass = s.is_completed ? 'done' : 'pending';
            const badgeText = s.is_completed ? '完了' : '未提出';
            html += '<div class="modal-item submission"><div class="modal-item-value">' + escapeHtml(s.item) + '<span class="completion-badge ' + badgeClass + '">' + badgeText + '</span></div></div>';
        });
        html += '</div>';
    }

    if (data.plans && data.plans.length > 0) {
        html += '<div class="modal-section plan"><h3>📝 週間計画</h3>';
        data.plans.forEach(p => {
            html += '<div class="modal-item plan"><div class="modal-item-value">' + escapeHtml(p.value) + '</div></div>';
        });
        html += '</div>';
    }

    if (html === '') {
        html = '<div class="no-data">この日の予定はありません</div>';
    }

    modalBody.innerHTML = html;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
