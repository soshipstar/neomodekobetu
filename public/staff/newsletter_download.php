<?php
/**
 * 施設通信ダウンロード（Word形式）
 * POSTデータ（プレビューモード）またはDBから取得
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// プレビューモード（POSTデータ）かDBから取得かを判定
$isPreviewMode = isset($_POST['preview_mode']) && $_POST['preview_mode'] === '1';

if ($isPreviewMode) {
    // POSTデータから通信情報を構築（プレビューモード）
    $id = $_POST['id'] ?? null;

    if (!$id) {
        die('通信IDが指定されていません');
    }

    // DBから基本情報（日付等）を取得
    $stmt = $pdo->prepare("SELECT year, month, report_start_date, report_end_date, schedule_start_date, schedule_end_date, status, published_at FROM newsletters WHERE id = ?");
    $stmt->execute([$id]);
    $baseData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$baseData) {
        die('通信が見つかりません');
    }

    // POSTデータとDBデータをマージ
    $newsletter = [
        'id' => $id,
        'title' => $_POST['title'] ?? '',
        'year' => $baseData['year'],
        'month' => $baseData['month'],
        'report_start_date' => $baseData['report_start_date'],
        'report_end_date' => $baseData['report_end_date'],
        'schedule_start_date' => $baseData['schedule_start_date'],
        'schedule_end_date' => $baseData['schedule_end_date'],
        'status' => $baseData['status'],
        'published_at' => $baseData['published_at'],
        'greeting' => $_POST['greeting'] ?? '',
        'event_calendar' => $_POST['event_calendar'] ?? '',
        'event_details' => $_POST['event_details'] ?? '',
        'weekly_reports' => $_POST['weekly_reports'] ?? '',
        'weekly_intro' => $_POST['weekly_intro'] ?? '',
        'event_results' => $_POST['event_results'] ?? '',
        'elementary_report' => $_POST['elementary_report'] ?? '',
        'junior_report' => $_POST['junior_report'] ?? '',
        'requests' => $_POST['requests'] ?? '',
        'others' => $_POST['others'] ?? '',
    ];
} else {
    // GETパラメータからDBデータを取得（従来の方式）
    $id = $_GET['id'] ?? null;

    if (!$id) {
        die('通信IDが指定されていません');
    }

    // 通信を取得
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$id]);
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$newsletter) {
        die('通信が見つかりません');
    }
}

// 教室情報を取得
$classroomId = $_SESSION['classroom_id'] ?? null;
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 設定を取得
$settings = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM newsletter_settings WHERE classroom_id = ?");
    $stmt->execute([$classroomId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

$calendarFormat = $settings['calendar_format'] ?? 'list';
$classroomName = $classroom['classroom_name'] ?? '施設';

// カレンダー表形式の場合、イベントデータを取得
$calendarEvents = [];
$calendarHolidays = [];
if ($calendarFormat === 'table' && $classroomId) {
    $scheduleStart = $newsletter['schedule_start_date'];
    $scheduleEnd = $newsletter['schedule_end_date'];

    $stmt = $pdo->prepare("
        SELECT id, event_date, event_name, event_color
        FROM events
        WHERE classroom_id = ? AND event_date BETWEEN ? AND ?
        ORDER BY event_date
    ");
    $stmt->execute([$classroomId, $scheduleStart, $scheduleEnd]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        $date = $event['event_date'];
        if (!isset($calendarEvents[$date])) {
            $calendarEvents[$date] = [];
        }
        $calendarEvents[$date][] = [
            'name' => $event['event_name'],
            'color' => $event['event_color'] ?? '#6366f1'
        ];
    }

    $stmt = $pdo->prepare("
        SELECT holiday_date, holiday_name
        FROM holidays
        WHERE classroom_id = ? AND holiday_date BETWEEN ? AND ?
    ");
    $stmt->execute([$classroomId, $scheduleStart, $scheduleEnd]);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($holidays as $holiday) {
        $calendarHolidays[$holiday['holiday_date']] = $holiday['holiday_name'];
    }
}

// ファイル名を生成
$filename = sprintf(
    "%d年%d月_%s通信.doc",
    $newsletter['year'],
    $newsletter['month'],
    $classroomName
);

// ヘッダーを設定（Word文書としてダウンロード）
header('Content-Type: application/msword; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// セクションデータを収集
$sections = [];

if (!empty($newsletter['greeting'])) {
    $sections[] = ['title' => '', 'type' => 'greeting', 'content' => $newsletter['greeting']];
}

// カレンダーセクション
$hasCalendarContent = ($calendarFormat === 'table' && (!empty($calendarEvents) || !empty($calendarHolidays))) || !empty($newsletter['event_calendar']);
if ($hasCalendarContent) {
    $sections[] = ['title' => '今月の予定', 'icon' => '[予定]', 'content' => $newsletter['event_calendar'] ?? '', 'type' => 'calendar', 'format' => $calendarFormat];
}

if (!empty($newsletter['event_details'])) {
    $sections[] = ['title' => 'イベント詳細', 'icon' => '[詳細]', 'content' => $newsletter['event_details']];
}

if (!empty($newsletter['weekly_reports'])) {
    $sections[] = ['title' => '活動紹介まとめ', 'icon' => '[活動]', 'content' => $newsletter['weekly_reports']];
}

if (!empty($newsletter['weekly_intro'])) {
    $sections[] = ['title' => '曜日別活動紹介', 'icon' => '[曜日]', 'content' => $newsletter['weekly_intro']];
}

if (!empty($newsletter['event_results'])) {
    $sections[] = ['title' => 'イベント結果報告', 'icon' => '[結果]', 'content' => $newsletter['event_results']];
}

if (!empty($newsletter['elementary_report'])) {
    $sections[] = ['title' => '小学生の活動', 'icon' => '[小学]', 'content' => $newsletter['elementary_report']];
}

if (!empty($newsletter['junior_report'])) {
    $sections[] = ['title' => '中高生の活動', 'icon' => '[中高]', 'content' => $newsletter['junior_report']];
}

if (!empty($newsletter['requests'])) {
    $sections[] = ['title' => '施設からのお願い', 'icon' => '[お願い]', 'content' => $newsletter['requests']];
}

if (!empty($newsletter['others'])) {
    $sections[] = ['title' => 'その他のお知らせ', 'icon' => '[他]', 'content' => $newsletter['others']];
}

$hasContent = count($sections) > 0;

// HTML出力（Wordで開ける形式）
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }

        body {
            font-family: 'Yu Gothic', 'YuGothic', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            font-size: 11pt;
            line-height: 1.8;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .facility-name {
            font-size: 10pt;
            color: #666;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .title {
            font-size: 22pt;
            font-weight: bold;
            color: #1f2937;
            margin: 10px 0;
        }

        .issue {
            font-size: 12pt;
            color: #6366f1;
            font-weight: bold;
        }

        .meta {
            font-size: 9pt;
            color: #888;
            margin-top: 10px;
        }

        .greeting-box {
            background-color: #f0f9ff;
            border-left: 4px solid #6366f1;
            padding: 15px 20px;
            margin: 20px 0;
        }

        .greeting-text {
            font-size: 11pt;
            line-height: 2;
        }

        .section {
            margin: 25px 0;
            page-break-inside: avoid;
        }

        .section-header {
            background-color: #6366f1;
            color: white;
            padding: 8px 15px;
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .section-content {
            padding: 0 10px;
            font-size: 11pt;
            line-height: 1.9;
            white-space: pre-wrap;
        }

        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            text-align: center;
        }

        .footer-text {
            font-size: 9pt;
            color: #888;
        }

        .footer-facility {
            font-size: 11pt;
            color: #6366f1;
            font-weight: bold;
            margin-top: 5px;
        }

        .empty-notice {
            background-color: #fef2f2;
            border: 2px dashed #ef4444;
            padding: 30px;
            text-align: center;
            color: #dc2626;
            margin: 40px 0;
        }

        .empty-notice h3 {
            font-size: 16pt;
            margin-bottom: 10px;
        }

        .empty-notice p {
            font-size: 11pt;
            color: #666;
        }

        /* カレンダー表形式 */
        .calendar-grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .calendar-grid-table th {
            background-color: #f3f4f6;
            padding: 6px 4px;
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .calendar-grid-table th.sunday { color: #ef4444; }
        .calendar-grid-table th.saturday { color: #3b82f6; }

        .calendar-grid-table td {
            width: 14.28%;
            height: 60px;
            border: 1px solid #e5e7eb;
            padding: 4px;
            vertical-align: top;
            font-size: 9pt;
        }

        .calendar-grid-table td.empty {
            background-color: #f9fafb;
        }

        .calendar-grid-table td.holiday {
            background-color: #fef2f2;
        }

        .calendar-day-num {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .calendar-day-num.sunday { color: #ef4444; }
        .calendar-day-num.saturday { color: #3b82f6; }

        .calendar-event {
            font-size: 8pt;
            background-color: #e0e7ff;
            color: #3730a3;
            padding: 1px 3px;
            margin-bottom: 2px;
            border-radius: 2px;
        }

        .calendar-holiday-name {
            font-size: 8pt;
            color: #dc2626;
            font-weight: bold;
        }

        .calendar-month-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin: 15px 0 8px 0;
            color: #374151;
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="header">
        <div class="facility-name"><?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="title"><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="issue"><?= $newsletter['year'] ?>年<?= $newsletter['month'] ?>月号</div>
        <div class="meta">
            報告期間: <?= date('Y/n/j', strtotime($newsletter['report_start_date'])) ?> ～ <?= date('n/j', strtotime($newsletter['report_end_date'])) ?>
            ｜予定期間: <?= date('n/j', strtotime($newsletter['schedule_start_date'])) ?> ～ <?= date('n/j', strtotime($newsletter['schedule_end_date'])) ?>
        </div>
    </div>

    <?php if (!$hasContent): ?>
    <!-- コンテンツがない場合 -->
    <div class="empty-notice">
        <h3>コンテンツがありません</h3>
        <p>この通信にはまだ内容が入力されていません。<br>編集画面で内容を入力するか、AIで生成してください。</p>
    </div>
    <?php else: ?>

    <?php foreach ($sections as $section): ?>
        <?php if (isset($section['type']) && $section['type'] === 'greeting'): ?>
        <!-- あいさつ文 -->
        <div class="greeting-box">
            <div class="greeting-text"><?= nl2br(htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8')) ?></div>
        </div>
        <?php elseif (isset($section['type']) && $section['type'] === 'calendar'): ?>
        <!-- カレンダーセクション -->
        <div class="section">
            <div class="section-header"><?= $section['icon'] ?? '' ?> <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php if (($section['format'] ?? 'list') === 'table'): ?>
            <!-- カレンダー表形式 -->
            <?php
            $startDate = new DateTime($newsletter['schedule_start_date']);
            $endDate = new DateTime($newsletter['schedule_end_date']);
            $currentMonth = clone $startDate;
            $currentMonth->modify('first day of this month');

            while ($currentMonth <= $endDate):
                $year = (int)$currentMonth->format('Y');
                $month = (int)$currentMonth->format('n');
                $daysInMonth = (int)$currentMonth->format('t');
                $firstDayOfWeek = (int)$currentMonth->format('w');
            ?>
            <div class="calendar-month-title"><?= $year ?>年<?= $month ?>月</div>
            <table class="calendar-grid-table">
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
                $dayCounter = 0;
                $totalCells = $firstDayOfWeek + $daysInMonth;
                $totalRows = ceil($totalCells / 7);

                for ($row = 0; $row < $totalRows; $row++):
                ?>
                <tr>
                    <?php for ($col = 0; $col < 7; $col++):
                        $cellIndex = $row * 7 + $col;
                        $day = $cellIndex - $firstDayOfWeek + 1;

                        if ($cellIndex < $firstDayOfWeek || $day > $daysInMonth):
                    ?>
                    <td class="empty"></td>
                    <?php else:
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $dayOfWeek = ($firstDayOfWeek + $day - 1) % 7;
                        $isHoliday = isset($calendarHolidays[$dateStr]);
                        $dayNumClass = '';
                        if ($dayOfWeek === 0) $dayNumClass = 'sunday';
                        if ($dayOfWeek === 6) $dayNumClass = 'saturday';
                    ?>
                    <td class="<?= $isHoliday ? 'holiday' : '' ?>">
                        <div class="calendar-day-num <?= $dayNumClass ?>"><?= $day ?></div>
                        <?php if ($isHoliday): ?>
                        <div class="calendar-holiday-name"><?= htmlspecialchars($calendarHolidays[$dateStr], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if (isset($calendarEvents[$dateStr])): ?>
                            <?php foreach ($calendarEvents[$dateStr] as $event): ?>
                            <div class="calendar-event"><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </table>
            <?php
                $currentMonth->modify('first day of next month');
            endwhile;
            ?>
            <?php else: ?>
            <!-- カレンダー一覧形式 -->
            <div class="section-content"><?= htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- 通常セクション -->
        <div class="section">
            <div class="section-header"><?= $section['icon'] ?? '' ?> <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="section-content"><?= htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- フッター -->
    <div class="footer">
        <div class="footer-text">
            <?php if ($isPreviewMode): ?>
            ※ プレビュー（未保存のデータを表示中）
            <?php elseif ($newsletter['published_at']): ?>
            発行日: <?= date('Y年n月j日', strtotime($newsletter['published_at'])) ?>
            <?php else: ?>
            ※ この通信は下書き状態です
            <?php endif; ?>
        </div>
        <div class="footer-facility"><?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
</body>
</html>
