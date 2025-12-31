<?php
/**
 * タブレットユーザー用トップページ
 * PC操作ができないユーザー向けに大きく表示し、音声入力機能を提供
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// タブレットユーザーのみアクセス可能
requireUserType('tablet_user');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

// 選択された日付（デフォルトは本日）
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$year = (int)date('Y', strtotime($selectedDate));
$month = (int)date('n', strtotime($selectedDate));

// 本日の活動一覧を取得
$stmt = $pdo->prepare("
    SELECT dr.id, dr.activity_name, dr.common_activity,
           u.full_name as staff_name,
           COUNT(DISTINCT sr.id) as participant_count
    FROM daily_records dr
    INNER JOIN users u ON dr.staff_id = u.id
    LEFT JOIN student_records sr ON dr.id = sr.daily_record_id
    WHERE dr.record_date = ? AND u.classroom_id = ?
    GROUP BY dr.id, dr.activity_name, dr.common_activity, u.full_name
    ORDER BY dr.created_at DESC
");
$stmt->execute([$selectedDate, $classroomId]);
$activities = $stmt->fetchAll();

// カレンダー表示用のデータ
$firstDay = strtotime("$year-$month-1");
$lastDay = strtotime(date('Y-m-t', $firstDay));

// 前月・次月の計算
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

// この月の活動がある日付を取得
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
$activeDates = array_column($stmt->fetchAll(), 'date');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <style>@media(prefers-color-scheme:dark){html,body{background:#1E1E1E;color:rgba(255,255,255,0.87)}}</style>
    <link rel="stylesheet" href="/assets/css/google-design.css">
    <title>本日の記録 - タブレット</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: var(--md-gray-6);
            padding: var(--spacing-lg);
            font-size: var(--text-title-2); /* 大きめのフォントサイズ */
        }

        .header {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-md);
        }

        .header h1 {
            font-size: 36px;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .classroom-name {
            font-size: 28px;
            color: var(--text-secondary);
        }

        .logout-btn {
            background: var(--md-red);
            color: var(--text-primary);
            border: none;
            padding: var(--spacing-lg) 40px;
            font-size: var(--text-title-2);
            border-radius: var(--radius-md);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: var(--md-red);
        }

        .calendar-section {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-md);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-2xl);
        }

        .calendar-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .calendar-nav button {
            background: var(--md-blue);
            color: white;
            border: none;
            padding: var(--spacing-lg) 30px;
            font-size: 28px;
            border-radius: var(--radius-md);
            cursor: pointer;
            min-width: 80px;
        }

        .calendar-nav button:hover {
            background: #1565C0;
        }

        .calendar-title {
            font-size: 32px;
            font-weight: bold;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .calendar-day-header {
            text-align: center;
            padding: 15px;
            font-weight: bold;
            font-size: var(--text-title-2);
            background: var(--md-gray-6);
            border-radius: var(--radius-sm);
        }

        .calendar-day {
            aspect-ratio: 1;
            padding: 15px;
            background: var(--md-gray-6);
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-align: center;
            font-size: 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .calendar-day:hover {
            background: #e9ecef;
        }

        .calendar-day.has-activity {
            background: #d4edda;
            font-weight: bold;
        }

        .calendar-day.selected {
            background: var(--md-blue);
            color: white;
        }

        .calendar-day.today {
            border: 3px solid var(--md-blue);
        }

        .calendar-day.empty {
            background: transparent;
            cursor: default;
        }

        .activities-section {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-md);
        }

        .section-title {
            font-size: 32px;
            margin-bottom: var(--spacing-2xl);
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .add-activity-btn {
            background: var(--md-green);
            color: white;
            border: none;
            padding: var(--spacing-lg) 40px;
            font-size: 28px;
            border-radius: var(--radius-md);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .add-activity-btn:hover {
            background: var(--md-green);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .activity-card {
            border: 2px solid var(--md-gray-5);
            border-radius: var(--radius-md);
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .activity-info {
            flex: 1;
        }

        .activity-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: var(--spacing-md);
        }

        .activity-meta {
            font-size: 22px;
            color: var(--text-secondary);
        }

        .activity-actions {
            display: flex;
            gap: 15px;
        }

        .action-btn {
            padding: 18px 35px;
            font-size: var(--text-title-2);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: var(--md-blue);
            color: white;
        }

        .btn-edit:hover {
            background: #1565C0;
        }

        .btn-renrakucho {
            background: var(--md-green);
            color: white;
        }

        .btn-renrakucho:hover {
            background: var(--md-green);
        }

        .btn-integrate {
            background: var(--md-gray);
            color: white;
        }

        .btn-integrate:hover {
            background: var(--md-gray);
        }

        .btn-delete {
            background: var(--md-red);
            color: white;
        }

        .btn-delete:hover {
            background: var(--md-red);
        }

        .no-activities {
            text-align: center;
            padding: 60px;
            color: var(--text-secondary);
            font-size: 26px;
        }

        @media (max-width: 768px) {
            .calendar {
                gap: 5px;
            }

            .calendar-day {
                font-size: 20px;
                padding: var(--spacing-md);
            }

            .activity-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .activity-actions {
                width: 100%;
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">smartphone</span> 本日の記録</h1>
        <div class="header-info">
            <div class="classroom-name">
                <?php if ($classroom): ?>
                    <?php echo htmlspecialchars($classroom['classroom_name']); ?>
                <?php endif; ?>
                | <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> <?php echo htmlspecialchars($currentUser['full_name']); ?>
            </div>
            <a href="/logout.php" class="logout-btn">ログアウト</a>
        </div>
    </div>

    <div class="calendar-section">
        <div class="calendar-header">
            <div class="calendar-nav">
                <button onclick="location.href='?date=<?php echo date('Y-m-d', strtotime("$year-$prevMonth-1")); ?>'">◀</button>
                <span class="calendar-title"><?php echo $year; ?>年<?php echo $month; ?>月</span>
                <button onclick="location.href='?date=<?php echo date('Y-m-d', strtotime("$year-$nextMonth-1")); ?>'">▶</button>
            </div>
            <button class="add-activity-btn" onclick="location.href='activity_edit.php?date=<?php echo $selectedDate; ?>'">
                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">add</span> 新しい活動を追加
            </button>
        </div>

        <div class="calendar">
            <div class="calendar-day-header">日</div>
            <div class="calendar-day-header">月</div>
            <div class="calendar-day-header">火</div>
            <div class="calendar-day-header">水</div>
            <div class="calendar-day-header">木</div>
            <div class="calendar-day-header">金</div>
            <div class="calendar-day-header">土</div>

            <?php
            $firstDayOfWeek = (int)date('w', $firstDay);
            $daysInMonth = (int)date('t', $firstDay);

            // 空白セルを追加
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }

            // 日付を表示
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $classes = ['calendar-day'];

                if (in_array($date, $activeDates)) {
                    $classes[] = 'has-activity';
                }
                if ($date === $selectedDate) {
                    $classes[] = 'selected';
                }
                if ($date === date('Y-m-d')) {
                    $classes[] = 'today';
                }

                echo '<div class="' . implode(' ', $classes) . '" onclick="location.href=\'?date=' . $date . '\'">';
                echo $day;
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="activities-section">
        <div class="section-title">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> <?php echo date('Y年n月j日', strtotime($selectedDate)); ?>の活動
        </div>

        <?php if (count($activities) > 0): ?>
            <div class="activity-list">
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-card">
                        <div class="activity-info">
                            <div class="activity-name">
                                <?php echo htmlspecialchars($activity['activity_name'] ?? $activity['common_activity']); ?>
                            </div>
                            <div class="activity-meta">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> <?php echo htmlspecialchars($activity['staff_name']); ?> |
                                <?php echo $activity['participant_count']; ?>名参加
                            </div>
                        </div>
                        <div class="activity-actions">
                            <a href="activity_edit.php?id=<?php echo $activity['id']; ?>&date=<?php echo $selectedDate; ?>" class="action-btn btn-edit">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit</span> 編集
                            </a>
                            <a href="renrakucho_form.php?activity_id=<?php echo $activity['id']; ?>" class="action-btn btn-renrakucho">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 連絡帳入力
                            </a>
                            <a href="activity_integrate.php?id=<?php echo $activity['id']; ?>&date=<?php echo $selectedDate; ?>" class="action-btn btn-integrate">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 統合
                            </a>
                            <button onclick="deleteActivity(<?php echo $activity['id']; ?>)" class="action-btn btn-delete">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">delete</span> 削除
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-activities">
                この日の活動はまだ登録されていません。<br>
                「新しい活動を追加」ボタンから登録してください。
            </div>
        <?php endif; ?>
    </div>

    <script>
        function deleteActivity(id) {
            if (confirm('この活動を削除してもよろしいですか？')) {
                fetch('activity_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('削除に失敗しました: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('エラーが発生しました');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>
