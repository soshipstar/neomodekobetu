<?php
/**
 * 生徒用ダッシュボード
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 今月のカレンダー表示用
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);

// イベント取得
$stmt = $pdo->prepare("
    SELECT event_date, event_name
    FROM events
    WHERE YEAR(event_date) = ? AND MONTH(event_date) = ?
    ORDER BY event_date
");
$stmt->execute([$year, $month]);
$events = [];
while ($row = $stmt->fetch()) {
    $day = date('j', strtotime($row['event_date']));
    $events[$day][] = $row['event_name'];
}

// 提出物の未提出数を取得
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.is_completed = 0 AND sr.due_date >= CURDATE()
");
$stmt->execute([$studentId]);
$pendingSubmissions = $stmt->fetchColumn();

// 新着メッセージ数を取得（生徒用チャット）
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM student_chat_rooms scr
    LEFT JOIN student_chat_messages scm ON scr.id = scm.room_id
    WHERE scr.student_id = ? AND scm.sender_type = 'staff'
      AND scm.created_at > COALESCE(
          (SELECT MAX(created_at) FROM student_chat_messages WHERE room_id = scr.id AND sender_type = 'student'),
          '1970-01-01'
      )
");
$stmt->execute([$studentId]);
$newMessages = $stmt->fetchColumn();

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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ - 個別支援連絡帳システム</title>
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

        .logout-btn {
            float: right;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .menu-item {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .menu-item-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .menu-item h2 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #667eea;
        }

        .menu-item p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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
            padding: 15px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
            height: 100px;
            position: relative;
        }

        .calendar td.other-month {
            background: #fafafa;
            color: #ccc;
        }

        .calendar .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .calendar .event {
            font-size: 11px;
            background: #667eea;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        @media (max-width: 768px) {
            .menu-grid {
                grid-template-columns: 1fr;
            }

            .calendar td {
                height: 80px;
                padding: 8px 5px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="logout.php" class="logout-btn">ログアウト</a>
            <h1>🎓 マイページ</h1>
            <p>ようこそ、<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さん</p>
        </div>

        <div class="menu-grid">
            <a href="schedule.php" class="menu-item">
                <div class="menu-item-icon">📅</div>
                <h2>スケジュール</h2>
                <p>出席日、イベント、休日を確認</p>
            </a>

            <a href="chat.php" class="menu-item">
                <?php if ($newMessages > 0): ?>
                    <span class="badge"><?php echo $newMessages; ?></span>
                <?php endif; ?>
                <div class="menu-item-icon">💬</div>
                <h2>チャット</h2>
                <p>スタッフとメッセージをやり取り</p>
            </a>

            <a href="weekly_plan.php" class="menu-item">
                <div class="menu-item-icon">📝</div>
                <h2>週間計画表</h2>
                <p>今週の計画を立てる・確認する</p>
            </a>

            <a href="submissions.php" class="menu-item">
                <?php if ($pendingSubmissions > 0): ?>
                    <span class="badge"><?php echo $pendingSubmissions; ?></span>
                <?php endif; ?>
                <div class="menu-item-icon">📤</div>
                <h2>提出物</h2>
                <p>提出物の確認と管理</p>
            </a>

            <a href="change_password.php" class="menu-item">
                <div class="menu-item-icon">🔐</div>
                <h2>パスワード変更</h2>
                <p>ログインパスワードを変更する</p>
            </a>
        </div>

        <div class="calendar-section">
            <div class="calendar-header">
                <h2><?php echo $year; ?>年<?php echo $month; ?>月のカレンダー</h2>
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

                            echo "<td class='$class'>";
                            $dayClass = '';
                            if ($dow == 0) $dayClass = 'sunday';
                            if ($dow == 6) $dayClass = 'saturday';
                            echo "<div class='day-number $dayClass'>$day</div>";

                            if (isset($events[$day])) {
                                foreach ($events[$day] as $event) {
                                    echo "<div class='event'>" . htmlspecialchars($event, ENT_QUOTES, 'UTF-8') . "</div>";
                                }
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
</body>
</html>
