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

// この保護者に紐づく生徒を取得
try {
    $stmt = $pdo->prepare("
        SELECT id, student_name, grade_level
        FROM students
        WHERE guardian_id = ? AND is_active = 1
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📖 連絡帳</h1>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="kakehashi.php" style="padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; font-size: 14px;">
                    🌉 かけはし
                </a>
                <a href="communication_logs.php" style="padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 14px;">
                    📚 連絡帳一覧・検索
                </a>
                <span class="user-info">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>さん（保護者）
                </span>
                <a href="/logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>

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
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
