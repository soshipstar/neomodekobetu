<?php
/**
 * 生徒用スケジュールページ
 * 週間計画表、提出物、イベント、休日を統合したカレンダー表示
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// カレンダー表示用
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);

// その月の開始日と終了日
$monthStart = date('Y-m-01', $firstDay);
$monthEnd = date('Y-m-t', $firstDay);

// イベント取得
$stmt = $pdo->prepare("
    SELECT event_date, event_name
    FROM events
    WHERE event_date BETWEEN ? AND ?
    ORDER BY event_date
");
$stmt->execute([$monthStart, $monthEnd]);
$events = [];
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['event_date']));
    if (!isset($events[$day])) {
        $events[$day] = [];
    }
    $events[$day][] = [
        'type' => 'event',
        'name' => $row['event_name'],
        'date' => $row['event_date']
    ];
}

// 休日取得
$stmt = $pdo->prepare("
    SELECT holiday_date, holiday_name, holiday_type
    FROM holidays
    WHERE holiday_date BETWEEN ? AND ?
    ORDER BY holiday_date
");
$stmt->execute([$monthStart, $monthEnd]);
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

// 提出物期限取得（統合）
$submissions = [];

// 1. 週間計画表からの提出物
$stmt = $pdo->prepare("
    SELECT
        wps.id,
        wps.submission_item as item,
        wps.due_date,
        wps.is_completed
    FROM weekly_plan_submissions wps
    INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
    WHERE wp.student_id = ? AND wps.due_date BETWEEN ? AND ?
    ORDER BY wps.due_date
");
$stmt->execute([$studentId, $monthStart, $monthEnd]);
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['due_date']));
    if (!isset($submissions[$day])) {
        $submissions[$day] = [];
    }
    $submissions[$day][] = [
        'type' => 'submission',
        'item' => $row['item'],
        'is_completed' => $row['is_completed'],
        'due_date' => $row['due_date']
    ];
}

// 2. 保護者チャット経由の提出物
$stmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.title as item,
        sr.due_date,
        sr.is_completed
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.due_date BETWEEN ? AND ?
    ORDER BY sr.due_date
");
$stmt->execute([$studentId, $monthStart, $monthEnd]);
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['due_date']));
    if (!isset($submissions[$day])) {
        $submissions[$day] = [];
    }
    $submissions[$day][] = [
        'type' => 'submission',
        'item' => $row['item'],
        'is_completed' => $row['is_completed'],
        'due_date' => $row['due_date']
    ];
}

// 3. 生徒自身が登録した提出物
$stmt = $pdo->prepare("
    SELECT
        id,
        title as item,
        due_date,
        is_completed
    FROM student_submissions
    WHERE student_id = ? AND due_date BETWEEN ? AND ?
    ORDER BY due_date
");
$stmt->execute([$studentId, $monthStart, $monthEnd]);
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['due_date']));
    if (!isset($submissions[$day])) {
        $submissions[$day] = [];
    }
    $submissions[$day][] = [
        'type' => 'submission',
        'item' => $row['item'],
        'is_completed' => $row['is_completed'],
        'due_date' => $row['due_date']
    ];
}

// 週間計画表取得
$stmt = $pdo->prepare("
    SELECT
        id,
        week_start_date,
        plan_data
    FROM weekly_plans
    WHERE student_id = ?
      AND week_start_date <= ?
      AND DATE_ADD(week_start_date, INTERVAL 6 DAY) >= ?
    ORDER BY week_start_date
");
$stmt->execute([$studentId, $monthEnd, $monthStart]);
$weeklyPlans = [];
while ($row = $stmt->fetch()) {
    $planData = json_decode($row['plan_data'], true);
    $weekStart = strtotime($row['week_start_date']);

    // 各曜日のデータを日付に変換
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("+$i days", $weekStart));
        $dayKey = "day_$i";

        if (isset($planData[$dayKey]) && !empty($planData[$dayKey])) {
            $day = date('j', strtotime($date));
            $dateMonth = date('n', strtotime($date));
            $dateYear = date('Y', strtotime($date));

            // 該当月のみ
            if ($dateMonth == $month && $dateYear == $year) {
                if (!isset($weeklyPlans[$day])) {
                    $weeklyPlans[$day] = [];
                }

                // 計画データを追加
                $weeklyPlans[$day][] = [
                    'type' => 'plan',
                    'value' => $planData[$dayKey],
                    'date' => $date
                ];
            }
        }
    }
}

// カレンダーデータを統合
$calendarData = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendarData[$day] = [
        'events' => $events[$day] ?? [],
        'holidays' => $holidays[$day] ?? [],
        'submissions' => $submissions[$day] ?? [],
        'plans' => $weeklyPlans[$day] ?? []
    ];
}

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// フィールド名の日本語表記
$fieldNames = [
    'goal' => '今日の目標',
    'schedule' => '予定・スケジュール',
    'homework' => '宿題',
    'study' => '学習内容',
    'reflection' => '振り返り',
    'tomorrow_goal' => '明日の目標',
    'notes' => '備考'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スケジュール - 個別支援連絡帳システム</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .back-btn {
            float: right;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .calendar-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav a {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .calendar-nav a:hover {
            background: #5568d3;
        }

        .calendar {
            width: 100%;
            border-collapse: collapse;
        }

        .calendar th {
            padding: 10px;
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
            border: 1px solid #ddd;
        }

        .calendar td {
            padding: 10px;
            border: 1px solid #ddd;
            vertical-align: top;
            height: 120px;
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }

        .calendar td:hover {
            background: #f0f0f0;
        }

        .calendar td.other-month {
            background: #fafafa;
            color: #ccc;
            cursor: default;
        }

        .calendar td.other-month:hover {
            background: #fafafa;
        }

        .calendar .day-number {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
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

        .calendar .indicator.event {
            background: #667eea;
            color: white;
        }

        .calendar .indicator.holiday {
            background: #e74c3c;
            color: white;
        }

        .calendar .indicator.submission {
            background: #f39c12;
            color: white;
        }

        .calendar .indicator.submission-done {
            background: #95a5a6;
            color: white;
            text-decoration: line-through;
        }

        .calendar .indicator.plan {
            background: #27ae60;
            color: white;
        }

        .calendar .sunday {
            color: #e74c3c;
        }

        .calendar .saturday {
            color: #3498db;
        }

        .calendar .today {
            background: #fff3cd;
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
            background: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 24px;
        }

        .modal-close {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .modal-close:hover {
            background: #c0392b;
        }

        .modal-section {
            margin-bottom: 25px;
        }

        .modal-section h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #f0f4ff;
            border-radius: 5px;
        }

        .modal-section.holiday h3 {
            color: #e74c3c;
            background: #ffe8e8;
        }

        .modal-section.submission h3 {
            color: #f39c12;
            background: #fff8e8;
        }

        .modal-section.plan h3 {
            color: #27ae60;
            background: #e8ffe8;
        }

        .modal-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }

        .modal-item.holiday {
            border-left-color: #e74c3c;
        }

        .modal-item.submission {
            border-left-color: #f39c12;
        }

        .modal-item.submission-done {
            border-left-color: #95a5a6;
            opacity: 0.6;
        }

        .modal-item.plan {
            border-left-color: #27ae60;
        }

        .modal-item-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .modal-item-value {
            color: #333;
            line-height: 1.6;
        }

        .completion-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 8px;
        }

        .completion-badge.done {
            background: #27ae60;
            color: white;
        }

        .completion-badge.pending {
            background: #f39c12;
            color: white;
        }

        .no-data {
            text-align: center;
            color: #999;
            padding: 40px;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .header p {
                font-size: 14px;
            }

            .back-btn {
                float: none;
                display: block;
                text-align: center;
                margin-bottom: 15px;
            }

            .calendar-section {
                padding: 15px;
                overflow-x: auto;
            }

            .calendar-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .calendar-header h2 {
                font-size: 18px;
            }

            .calendar-nav {
                width: 100%;
            }

            .calendar-nav a {
                flex: 1;
                text-align: center;
            }

            .calendar {
                min-width: 600px;
            }

            .calendar th {
                font-size: 12px;
                padding: 8px 4px;
            }

            .calendar td {
                height: 80px;
                padding: 5px 3px;
                font-size: 10px;
            }

            .calendar .day-number {
                font-size: 13px;
                margin-bottom: 3px;
            }

            .calendar .indicator {
                font-size: 9px;
                padding: 2px 4px;
                margin: 1px 0;
            }

            .modal-content {
                padding: 20px;
                width: 95%;
                max-height: 90vh;
            }

            .modal-header h2 {
                font-size: 18px;
            }

            .modal-section h3 {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 5px;
            }

            .header {
                padding: 15px;
            }

            .header h1 {
                font-size: 18px;
            }

            .header p {
                font-size: 12px;
            }

            .calendar-section {
                padding: 10px;
            }

            .calendar-header h2 {
                font-size: 16px;
            }

            .calendar-nav a {
                padding: 6px 10px;
                font-size: 12px;
            }

            .calendar {
                min-width: 500px;
            }

            .calendar th {
                font-size: 11px;
                padding: 6px 2px;
            }

            .calendar td {
                height: 70px;
                padding: 4px 2px;
            }

            .calendar .day-number {
                font-size: 12px;
            }

            .calendar .indicator {
                font-size: 8px;
                padding: 1px 3px;
            }

            .modal-content {
                padding: 15px;
            }

            .modal-header {
                padding-bottom: 10px;
            }

            .modal-header h2 {
                font-size: 16px;
            }

            .modal-section h3 {
                font-size: 14px;
                padding: 6px 10px;
            }

            .modal-item {
                padding: 8px 12px;
            }

            .modal-item-label {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← マイページへ戻る</a>
            <h1>📅 スケジュール</h1>
            <p>週間計画、提出物、イベント、休日を確認</p>
        </div>

        <div class="calendar-section">
            <div class="calendar-header">
                <h2><?php echo $year; ?>年<?php echo $month; ?>月</h2>
                <div class="calendar-nav">
                    <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>">← 前月</a>
                    <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>">次月 →</a>
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
                            $hasData = !empty($data['events']) || !empty($data['holidays']) ||
                                      !empty($data['submissions']) || !empty($data['plans']);

                            $dataJson = htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
                            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

                            echo "<td class='$class' " . ($hasData ? "onclick='showDetail(\"$dateStr\", $dataJson)'" : "") . ">";

                            $dayClass = '';
                            if ($dow == 0) $dayClass = 'sunday';
                            if ($dow == 6) $dayClass = 'saturday';
                            echo "<div class='day-number $dayClass'>$day</div>";

                            // インジケーター表示（最大4件）
                            $count = 0;
                            $maxDisplay = 4;

                            foreach ($data['holidays'] as $holiday) {
                                if ($count >= $maxDisplay) break;
                                echo "<span class='indicator holiday'>" . htmlspecialchars($holiday['name'], ENT_QUOTES, 'UTF-8') . "</span>";
                                $count++;
                            }

                            foreach ($data['events'] as $event) {
                                if ($count >= $maxDisplay) break;
                                echo "<span class='indicator event'>" . htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') . "</span>";
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
    </div>

    <!-- 詳細モーダル -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalDate"></h2>
                <button class="modal-close" onclick="closeModal()">閉じる</button>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        const fieldNames = <?php echo json_encode($fieldNames); ?>;

        function showDetail(dateStr, data) {
            const modal = document.getElementById('detailModal');
            const modalDate = document.getElementById('modalDate');
            const modalBody = document.getElementById('modalBody');

            // 日付表示
            const date = new Date(dateStr + 'T00:00:00');
            const dateFormatted = `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
            const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
            const dayName = dayNames[date.getDay()];
            modalDate.textContent = `${dateFormatted}（${dayName}）`;

            // 内容表示
            let html = '';

            // 休日
            if (data.holidays && data.holidays.length > 0) {
                html += '<div class="modal-section holiday">';
                html += '<h3>🎌 休日</h3>';
                data.holidays.forEach(h => {
                    html += `<div class="modal-item holiday">`;
                    html += `<div class="modal-item-value">${escapeHtml(h.name)}</div>`;
                    html += `</div>`;
                });
                html += '</div>';
            }

            // イベント
            if (data.events && data.events.length > 0) {
                html += '<div class="modal-section">';
                html += '<h3>📅 イベント</h3>';
                data.events.forEach(e => {
                    html += `<div class="modal-item">`;
                    html += `<div class="modal-item-value">${escapeHtml(e.name)}</div>`;
                    html += `</div>`;
                });
                html += '</div>';
            }

            // 提出物
            if (data.submissions && data.submissions.length > 0) {
                html += '<div class="modal-section submission">';
                html += '<h3>📤 提出物</h3>';
                data.submissions.forEach(s => {
                    const itemClass = s.is_completed ? 'submission-done' : 'submission';
                    const badgeClass = s.is_completed ? 'done' : 'pending';
                    const badgeText = s.is_completed ? '完了' : '未提出';
                    html += `<div class="modal-item ${itemClass}">`;
                    html += `<div class="modal-item-value">`;
                    html += escapeHtml(s.item);
                    html += `<span class="completion-badge ${badgeClass}">${badgeText}</span>`;
                    html += `</div>`;
                    html += `</div>`;
                });
                html += '</div>';
            }

            // 週間計画
            if (data.plans && data.plans.length > 0) {
                html += '<div class="modal-section plan">';
                html += '<h3>📝 週間計画</h3>';
                data.plans.forEach(p => {
                    html += `<div class="modal-item plan">`;
                    html += `<div class="modal-item-value">${escapeHtml(p.value)}</div>`;
                    html += `</div>`;
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
            const modal = document.getElementById('detailModal');
            modal.classList.remove('active');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // モーダル外クリックで閉じる
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
