<?php
/**
 * 施設通信PDF出力ページ
 * GPT設計による美しいレイアウト
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

// デフォルト設定
$showFacilityName = $settings['show_facility_name'] ?? 1;
$showLogo = $settings['show_logo'] ?? 1;
$calendarFormat = $settings['calendar_format'] ?? 'list';

$classroomName = $classroom['classroom_name'] ?? '施設';
$logoPath = $classroom['logo_path'] ?? null;

// ファイル名
$pdfFilename = sprintf(
    "%d年%d月_%s通信",
    $newsletter['year'],
    $newsletter['month'],
    $classroomName
);

// カレンダー表形式の場合、イベントデータを取得
$calendarEvents = [];
$calendarHolidays = [];
if ($calendarFormat === 'table' && $classroomId) {
    // 予定期間のイベントを取得
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

    // 休日を取得
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

// セクションデータを配列にまとめる
$sections = [];

if (!empty($newsletter['greeting'])) {
    $sections[] = ['type' => 'greeting', 'content' => $newsletter['greeting']];
}

// カレンダーセクション（表形式または一覧形式）
$hasCalendarContent = ($calendarFormat === 'table' && (!empty($calendarEvents) || !empty($calendarHolidays))) || !empty($newsletter['event_calendar']);
if ($hasCalendarContent) {
    $sections[] = ['type' => 'calendar', 'title' => '今月の予定', 'icon' => '📅', 'content' => $newsletter['event_calendar'] ?? '', 'format' => $calendarFormat];
}

if (!empty($newsletter['event_details'])) {
    $sections[] = ['type' => 'normal', 'title' => 'イベント詳細', 'icon' => '📝', 'content' => $newsletter['event_details']];
}

if (!empty($newsletter['weekly_reports'])) {
    $sections[] = ['type' => 'normal', 'title' => '活動紹介まとめ', 'icon' => '📖', 'content' => $newsletter['weekly_reports']];
}

if (!empty($newsletter['weekly_intro'])) {
    $sections[] = ['type' => 'normal', 'title' => '曜日別活動紹介', 'icon' => '🗓', 'content' => $newsletter['weekly_intro']];
}

if (!empty($newsletter['event_results'])) {
    $sections[] = ['type' => 'normal', 'title' => 'イベント結果報告', 'icon' => '🎉', 'content' => $newsletter['event_results']];
}

if (!empty($newsletter['elementary_report'])) {
    $sections[] = ['type' => 'grade', 'title' => '小学生の活動', 'icon' => '🎒', 'content' => $newsletter['elementary_report']];
}

if (!empty($newsletter['junior_report'])) {
    $sections[] = ['type' => 'grade', 'title' => '中高生の活動', 'icon' => '📚', 'content' => $newsletter['junior_report']];
}

if (!empty($newsletter['requests'])) {
    $sections[] = ['type' => 'notice', 'title' => '施設からのお願い', 'icon' => '🙏', 'content' => $newsletter['requests']];
}

if (!empty($newsletter['others'])) {
    $sections[] = ['type' => 'notice', 'title' => 'その他のお知らせ', 'icon' => '📌', 'content' => $newsletter['others']];
}

$hasContent = count($sections) > 0;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?> - PDF</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Yu Gothic", "YuGothic", "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Meiryo", sans-serif;
            background: #e8e8e8;
            padding: 20px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ツールバー */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .toolbar h1 {
            font-size: 16px;
            font-weight: 600;
        }

        .toolbar-buttons {
            display: flex;
            gap: 12px;
        }

        .toolbar button {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-pdf {
            background: #ef4444;
            color: white;
        }

        .btn-pdf:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239,68,68,0.4);
        }

        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.25);
        }

        /* PDFコンテナ */
        .pdf-container {
            margin-top: 80px;
            display: flex;
            justify-content: center;
            padding-bottom: 40px;
        }

        /* PDF本体 */
        #pdf-content {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 12mm 15mm;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-radius: 4px;
        }

        /* ヘッダー */
        .header {
            text-align: center;
            padding-bottom: 12px;
            margin-bottom: 15px;
            border-bottom: 3px solid #6366f1;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: #8b5cf6;
        }

        .header-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .header-facility {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 4px;
            letter-spacing: 2px;
        }

        .header-title {
            font-size: 22px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .header-issue {
            font-size: 12px;
            color: #6366f1;
            font-weight: 600;
        }

        .header-meta {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 8px;
        }

        /* あいさつ文 */
        .greeting-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #6366f1;
        }

        .greeting-text {
            font-size: 11px;
            line-height: 1.9;
            color: #374151;
        }

        /* セクション */
        .section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 7px 14px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: bold;
        }

        .section-icon {
            font-size: 14px;
        }

        .section-content {
            padding: 0 8px;
            font-size: 10.5px;
            line-height: 1.85;
            color: #374151;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* カレンダーセクション（一覧形式） */
        .calendar-box {
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
        }

        .calendar-content {
            font-family: "Yu Gothic", "Meiryo", monospace;
            font-size: 10px;
            line-height: 1.6;
            white-space: pre-wrap;
            color: #374151;
        }

        /* カレンダーセクション（表形式） */
        .calendar-grid-container {
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .calendar-day-header {
            text-align: center;
            padding: 4px 2px;
            font-weight: bold;
            font-size: 9px;
            color: #6b7280;
            background: #f3f4f6;
            border-radius: 2px;
        }

        .calendar-day-header.sunday { color: #ef4444; }
        .calendar-day-header.saturday { color: #3b82f6; }

        .calendar-day {
            min-height: 45px;
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 2px;
            background: white;
            font-size: 8px;
        }

        .calendar-day.empty {
            background: #f9fafb;
            border-color: #f3f4f6;
        }

        .calendar-day.holiday {
            background: #fef2f2;
        }

        .calendar-day-num {
            font-size: 10px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 2px;
        }

        .calendar-day-num.sunday { color: #ef4444; }
        .calendar-day-num.saturday { color: #3b82f6; }

        .calendar-event {
            font-size: 7px;
            line-height: 1.3;
            padding: 1px 2px;
            margin-bottom: 1px;
            border-radius: 2px;
            background: #e0e7ff;
            color: #3730a3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .calendar-holiday-name {
            font-size: 7px;
            color: #dc2626;
            font-weight: 500;
        }

        /* お知らせセクション */
        .notice-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 10px 14px;
        }

        .notice-content {
            font-size: 10.5px;
            line-height: 1.8;
            color: #92400e;
            white-space: pre-wrap;
        }

        /* 学年別セクション - 2カラム */
        .grade-sections {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
        }

        .grade-section {
            flex: 1;
            background: #f9fafb;
            border-radius: 6px;
            padding: 10px;
            border: 1px solid #e5e7eb;
        }

        .grade-header {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: bold;
            color: #6366f1;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #6366f1;
        }

        .grade-content {
            font-size: 10px;
            line-height: 1.8;
            color: #374151;
            white-space: pre-wrap;
        }

        /* フッター */
        .footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-text {
            font-size: 9px;
            color: #9ca3af;
        }

        .footer-facility {
            font-size: 11px;
            color: #6366f1;
            font-weight: 600;
            margin-top: 4px;
        }

        /* 空のコンテンツ警告 */
        .empty-warning {
            background: #fef2f2;
            border: 2px dashed #ef4444;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            color: #dc2626;
        }

        .empty-warning h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .empty-warning p {
            font-size: 14px;
            color: #6b7280;
        }

        /* ローディング */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 40px 60px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .spinner {
            border: 4px solid #e5e7eb;
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 14px;
            color: #374151;
        }

        /* 印刷用 */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .toolbar {
                display: none !important;
            }
            .pdf-container {
                margin-top: 0;
                padding: 0;
            }
            #pdf-content {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="toolbar-buttons">
            <button class="btn-back" onclick="window.close()">閉じる</button>
            <button class="btn-pdf" onclick="generatePDF()">📄 PDFをダウンロード</button>
        </div>
    </div>

    <div class="pdf-container">
        <div id="pdf-content">
            <!-- ヘッダー -->
            <div class="header">
                <?php if ($showLogo && $logoPath): ?>
                <img src="/<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="ロゴ" class="header-logo">
                <?php endif; ?>

                <?php if ($showFacilityName): ?>
                <div class="header-facility"><?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="header-title"><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?></div>

                <div class="header-issue">
                    <?= $newsletter['year'] ?>年<?= $newsletter['month'] ?>月号
                </div>

                <div class="header-meta">
                    報告期間: <?= date('Y/n/j', strtotime($newsletter['report_start_date'])) ?> ～ <?= date('n/j', strtotime($newsletter['report_end_date'])) ?>
                    ｜予定期間: <?= date('n/j', strtotime($newsletter['schedule_start_date'])) ?> ～ <?= date('n/j', strtotime($newsletter['schedule_end_date'])) ?>
                </div>
            </div>

            <?php if (!$hasContent): ?>
            <!-- コンテンツがない場合の警告 -->
            <div class="empty-warning">
                <h3>⚠️ コンテンツがありません</h3>
                <p>この通信にはまだ内容が入力されていません。<br>編集画面で内容を入力するか、AIで生成してください。</p>
            </div>
            <?php else: ?>

            <?php
            // あいさつ文
            foreach ($sections as $section):
                if ($section['type'] === 'greeting'):
            ?>
            <div class="greeting-box">
                <div class="greeting-text"><?= nl2br(htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
            <?php
                endif;
            endforeach;

            // 学年別セクションを収集
            $gradeSections = array_filter($sections, fn($s) => $s['type'] === 'grade');
            $otherSections = array_filter($sections, fn($s) => $s['type'] !== 'greeting' && $s['type'] !== 'grade');

            // 通常セクション
            foreach ($otherSections as $section):
                if ($section['type'] === 'calendar'):
                    $format = $section['format'] ?? 'list';
            ?>
            <div class="section">
                <div class="section-header">
                    <span class="section-icon"><?= $section['icon'] ?></span>
                    <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if ($format === 'table'): ?>
                <!-- カレンダー表形式 -->
                <div class="calendar-grid-container">
                    <?php
                    // 予定期間の月を取得
                    $startDate = new DateTime($newsletter['schedule_start_date']);
                    $endDate = new DateTime($newsletter['schedule_end_date']);

                    // 月ごとにカレンダーを表示
                    $currentMonth = clone $startDate;
                    $currentMonth->modify('first day of this month');

                    while ($currentMonth <= $endDate):
                        $year = (int)$currentMonth->format('Y');
                        $month = (int)$currentMonth->format('n');
                        $daysInMonth = (int)$currentMonth->format('t');
                        $firstDayOfWeek = (int)$currentMonth->format('w');
                    ?>
                    <div style="margin-bottom: 10px;">
                        <div style="text-align: center; font-weight: bold; font-size: 11px; margin-bottom: 6px; color: #374151;">
                            <?= $year ?>年<?= $month ?>月
                        </div>
                        <div class="calendar-grid">
                            <?php
                            $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
                            foreach ($weekDays as $idx => $dayName):
                                $dayClass = '';
                                if ($idx === 0) $dayClass = 'sunday';
                                if ($idx === 6) $dayClass = 'saturday';
                            ?>
                            <div class="calendar-day-header <?= $dayClass ?>"><?= $dayName ?></div>
                            <?php endforeach; ?>

                            <?php
                            // 空白セル
                            for ($i = 0; $i < $firstDayOfWeek; $i++):
                            ?>
                            <div class="calendar-day empty"></div>
                            <?php endfor; ?>

                            <?php
                            // 日付セル
                            for ($day = 1; $day <= $daysInMonth; $day++):
                                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                $dayOfWeek = ($firstDayOfWeek + $day - 1) % 7;
                                $isHoliday = isset($calendarHolidays[$dateStr]);
                                $dayNumClass = '';
                                if ($dayOfWeek === 0) $dayNumClass = 'sunday';
                                if ($dayOfWeek === 6) $dayNumClass = 'saturday';
                            ?>
                            <div class="calendar-day<?= $isHoliday ? ' holiday' : '' ?>">
                                <div class="calendar-day-num <?= $dayNumClass ?>"><?= $day ?></div>
                                <?php if ($isHoliday): ?>
                                <div class="calendar-holiday-name"><?= htmlspecialchars($calendarHolidays[$dateStr], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <?php if (isset($calendarEvents[$dateStr])): ?>
                                    <?php foreach ($calendarEvents[$dateStr] as $event): ?>
                                    <div class="calendar-event"><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php
                        $currentMonth->modify('first day of next month');
                    endwhile;
                    ?>
                </div>
                <?php else: ?>
                <!-- カレンダー一覧形式 -->
                <div class="calendar-box">
                    <div class="calendar-content"><?= htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($section['type'] === 'notice'): ?>
            <div class="section">
                <div class="section-header">
                    <span class="section-icon"><?= $section['icon'] ?></span>
                    <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notice-box">
                    <div class="notice-content"><?= htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="section">
                <div class="section-header">
                    <span class="section-icon"><?= $section['icon'] ?></span>
                    <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="section-content"><?= htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php
                endif;
            endforeach;

            // 学年別セクション（2カラム）
            if (count($gradeSections) > 0):
            ?>
            <div class="grade-sections">
                <?php foreach ($gradeSections as $section): ?>
                <div class="grade-section">
                    <div class="grade-header">
                        <span><?= $section['icon'] ?></span>
                        <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="grade-content"><?= htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

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
        </div>
    </div>

    <!-- ローディング -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <p class="loading-text">PDFを生成中...</p>
        </div>
    </div>

    <script>
        const pdfFilename = <?= json_encode($pdfFilename, JSON_UNESCAPED_UNICODE) ?>;

        async function generatePDF() {
            const element = document.getElementById('pdf-content');
            const overlay = document.getElementById('loadingOverlay');

            overlay.classList.add('active');

            // html2pdfの設定
            const opt = {
                margin: [5, 5, 5, 5],
                filename: pdfFilename + '.pdf',
                image: { type: 'jpeg', quality: 0.95 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true,
                    allowTaint: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                },
                pagebreak: {
                    mode: ['avoid-all', 'css', 'legacy'],
                    before: '.page-break-before',
                    after: '.page-break-after',
                    avoid: '.section, .grade-sections'
                }
            };

            try {
                await html2pdf().set(opt).from(element).save();
            } catch (error) {
                console.error('PDF generation error:', error);
                alert('PDFの生成に失敗しました。\nエラー: ' + error.message);
            } finally {
                overlay.classList.remove('active');
            }
        }
    </script>
</body>
</html>
