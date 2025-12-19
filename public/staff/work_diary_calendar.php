<?php
/**
 * 業務日誌カレンダー表示ページ
 * 過去の業務日誌を月単位で閲覧
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    $_SESSION['error'] = '教室が設定されていません。';
    header('Location: renrakucho_activities.php');
    exit;
}

// 選択された年月を取得
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selectedDate = $_GET['date'] ?? null;

// 月の初日と最終日
$firstDay = strtotime("$year-$month-1");
$lastDay = strtotime(date('Y-m-t', $firstDay));

// 前月・次月の計算
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

// この月の業務日誌がある日付を取得
$stmt = $pdo->prepare("
    SELECT diary_date, previous_day_review, daily_communication, daily_roles, prev_day_children_status, children_special_notes
    FROM work_diaries
    WHERE classroom_id = ? AND YEAR(diary_date) = ? AND MONTH(diary_date) = ?
    ORDER BY diary_date
");
$stmt->execute([$classroomId, $year, $month]);
$diaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$diaryDates = [];
foreach ($diaries as $diary) {
    $diaryDates[$diary['diary_date']] = $diary;
}

// 選択された日付の業務日誌を取得
$selectedDiary = null;
if ($selectedDate && isset($diaryDates[$selectedDate])) {
    $stmt = $pdo->prepare("
        SELECT wd.*, u1.full_name as creator_name, u2.full_name as updater_name
        FROM work_diaries wd
        LEFT JOIN users u1 ON wd.created_by = u1.id
        LEFT JOIN users u2 ON wd.updated_by = u2.id
        WHERE wd.classroom_id = ? AND wd.diary_date = ?
    ");
    $stmt->execute([$classroomId, $selectedDate]);
    $selectedDiary = $stmt->fetch();
}

$currentPage = 'work_diary_calendar';
renderPageStart('staff', $currentPage, '業務日誌');
?>

<style>
.calendar-page-container {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 900px) {
    .calendar-page-container {
        grid-template-columns: 1fr;
    }
}

.calendar-panel {
    background: var(--apple-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.calendar-header h2 {
    font-size: 18px;
    color: var(--text-primary);
}

.calendar-nav {
    display: flex;
    gap: 10px;
}

.calendar-nav a {
    padding: 6px 12px;
    background: var(--apple-gray-5);
    color: var(--text-primary);
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
}

.calendar-nav a:hover {
    background: var(--apple-gray-4);
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.calendar-day-header {
    text-align: center;
    font-weight: bold;
    padding: 8px;
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.calendar-day-header.sunday { color: var(--apple-red); }
.calendar-day-header.saturday { color: var(--apple-blue); }

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background var(--duration-fast);
    font-size: var(--text-subhead);
    position: relative;
}

.calendar-day:hover {
    background: var(--apple-gray-6);
}

.calendar-day.empty {
    cursor: default;
}

.calendar-day.today {
    background: var(--apple-blue);
    color: white;
}

.calendar-day.selected {
    background: var(--apple-green);
    color: white;
}

.calendar-day.has-diary::after {
    content: '';
    position: absolute;
    bottom: 4px;
    width: 6px;
    height: 6px;
    background: var(--apple-orange);
    border-radius: 50%;
}

.calendar-day.today.has-diary::after,
.calendar-day.selected.has-diary::after {
    background: white;
}

.calendar-day .day-number {
    font-weight: 500;
}

.calendar-day .day-number.sunday { color: var(--apple-red); }
.calendar-day .day-number.saturday { color: var(--apple-blue); }
.calendar-day.today .day-number,
.calendar-day.selected .day-number {
    color: white;
}

.calendar-legend {
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--apple-gray-5);
    display: flex;
    gap: 20px;
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-dot.diary { background: var(--apple-orange); }
.legend-dot.today { background: var(--apple-blue); }

.diary-panel {
    background: var(--apple-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.diary-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 2px solid var(--apple-blue);
}

.diary-panel-header h3 {
    font-size: 18px;
    color: var(--text-primary);
}

.edit-btn {
    padding: 8px 16px;
    background: var(--apple-blue);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
}

.edit-btn:hover {
    opacity: 0.9;
}

.diary-section {
    margin-bottom: var(--spacing-lg);
}

.diary-section h4 {
    font-size: var(--text-subhead);
    color: var(--apple-blue);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.diary-content {
    background: var(--apple-gray-6);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    line-height: 1.6;
    white-space: pre-wrap;
}

.diary-content.empty {
    color: var(--text-tertiary);
    font-style: italic;
}

.no-diary {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.no-diary p {
    margin-bottom: var(--spacing-lg);
    font-size: var(--text-body);
}

.create-btn {
    padding: 12px 24px;
    background: var(--apple-blue);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-weight: bold;
}

.create-btn:hover {
    opacity: 0.9;
}

.diary-meta {
    font-size: var(--text-footnote);
    color: var(--text-tertiary);
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--apple-gray-5);
}

.quick-actions {
    margin-bottom: var(--spacing-lg);
    display: flex;
    gap: 10px;
}

.quick-action-btn {
    padding: 10px 20px;
    background: var(--apple-green);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.quick-action-btn:hover {
    opacity: 0.9;
}
</style>

<div class="quick-actions">
    <a href="work_diary.php?date=<?php echo date('Y-m-d'); ?>" class="quick-action-btn">
        <span>+</span> 本日の業務日誌を作成
    </a>
    <a href="renrakucho_activities.php" class="nav-btn" style="padding: 10px 20px; background: var(--apple-gray-5); color: var(--text-primary); text-decoration: none; border-radius: var(--radius-sm);">
        ← 活動管理に戻る
    </a>
</div>

<div class="calendar-page-container">
    <!-- カレンダー -->
    <div class="calendar-panel">
        <div class="calendar-header">
            <h2><?php echo $year; ?>年 <?php echo $month; ?>月</h2>
            <div class="calendar-nav">
                <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>">← 前月</a>
                <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('n'); ?>">今月</a>
                <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>">次月 →</a>
            </div>
        </div>

        <div class="calendar-grid">
            <?php
            $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
            foreach ($weekDays as $index => $day) {
                $class = '';
                if ($index === 0) $class = 'sunday';
                if ($index === 6) $class = 'saturday';
                echo "<div class='calendar-day-header $class'>$day</div>";
            }

            // 月初の曜日
            $startDayOfWeek = date('w', $firstDay);

            // 空白セル
            for ($i = 0; $i < $startDayOfWeek; $i++) {
                echo "<div class='calendar-day empty'></div>";
            }

            // 日付セル
            $daysInMonth = date('t', $firstDay);
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $dayOfWeek = date('w', strtotime($currentDate));

                $classes = ['calendar-day'];
                if ($currentDate === date('Y-m-d')) {
                    $classes[] = 'today';
                }
                if ($currentDate === $selectedDate) {
                    $classes[] = 'selected';
                }
                if (isset($diaryDates[$currentDate])) {
                    $classes[] = 'has-diary';
                }

                $dayClass = '';
                if ($dayOfWeek == 0) $dayClass = 'sunday';
                if ($dayOfWeek == 6) $dayClass = 'saturday';

                echo "<div class='" . implode(' ', $classes) . "' onclick=\"location.href='?year=$year&month=$month&date=$currentDate'\">";
                echo "<span class='day-number $dayClass'>$day</span>";
                echo "</div>";
            }
            ?>
        </div>

        <div class="calendar-legend">
            <div class="legend-item">
                <span class="legend-dot diary"></span>
                <span>業務日誌あり</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot today"></span>
                <span>今日</span>
            </div>
        </div>
    </div>

    <!-- 業務日誌詳細 -->
    <div class="diary-panel">
        <?php if ($selectedDate && $selectedDiary): ?>
            <?php
            $dateObj = new DateTime($selectedDate);
            $formattedDate = $dateObj->format('Y年n月j日');
            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][(int)$dateObj->format('w')];
            ?>
            <div class="diary-panel-header">
                <h3><?php echo $formattedDate; ?>（<?php echo $dayOfWeek; ?>）の業務日誌</h3>
                <a href="work_diary.php?date=<?php echo $selectedDate; ?>" class="edit-btn">編集</a>
            </div>

            <div class="diary-section">
                <h4><span>📝</span> 前日の振り返り</h4>
                <div class="diary-content <?php echo empty($selectedDiary['previous_day_review']) ? 'empty' : ''; ?>"><?php echo !empty($selectedDiary['previous_day_review']) ? htmlspecialchars($selectedDiary['previous_day_review']) : '記入なし'; ?></div>
            </div>

            <div class="diary-section">
                <h4><span>📢</span> 本日の伝達事項</h4>
                <div class="diary-content <?php echo empty($selectedDiary['daily_communication']) ? 'empty' : ''; ?>"><?php echo !empty($selectedDiary['daily_communication']) ? htmlspecialchars($selectedDiary['daily_communication']) : '記入なし'; ?></div>
            </div>

            <div class="diary-section">
                <h4><span>👥</span> 本日の役割分担</h4>
                <div class="diary-content <?php echo empty($selectedDiary['daily_roles']) ? 'empty' : ''; ?>"><?php echo !empty($selectedDiary['daily_roles']) ? htmlspecialchars($selectedDiary['daily_roles']) : '記入なし'; ?></div>
            </div>

            <div class="diary-section">
                <h4><span>👧</span> 前日の児童の状況</h4>
                <div class="diary-content <?php echo empty($selectedDiary['prev_day_children_status']) ? 'empty' : ''; ?>"><?php echo !empty($selectedDiary['prev_day_children_status']) ? htmlspecialchars($selectedDiary['prev_day_children_status']) : '記入なし'; ?></div>
            </div>

            <div class="diary-section">
                <h4><span>📌</span> 児童に関する特記事項</h4>
                <div class="diary-content <?php echo empty($selectedDiary['children_special_notes']) ? 'empty' : ''; ?>"><?php echo !empty($selectedDiary['children_special_notes']) ? htmlspecialchars($selectedDiary['children_special_notes']) : '記入なし'; ?></div>
            </div>

            <div class="diary-section">
                <h4><span>📋</span> その他メモ</h4>
                <div class="diary-content <?php echo empty($selectedDiary['other_notes']) ? 'empty' : ''; ?>"><?php echo !empty($selectedDiary['other_notes']) ? htmlspecialchars($selectedDiary['other_notes']) : '記入なし'; ?></div>
            </div>

            <div class="diary-meta">
                作成者: <?php echo htmlspecialchars($selectedDiary['creator_name']); ?>
                （<?php echo date('Y/m/d H:i', strtotime($selectedDiary['created_at'])); ?>）
                <?php if ($selectedDiary['updated_by']): ?>
                    / 最終更新: <?php echo htmlspecialchars($selectedDiary['updater_name']); ?>
                    （<?php echo date('Y/m/d H:i', strtotime($selectedDiary['updated_at'])); ?>）
                <?php endif; ?>
            </div>
        <?php elseif ($selectedDate): ?>
            <?php
            $dateObj = new DateTime($selectedDate);
            $formattedDate = $dateObj->format('Y年n月j日');
            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][(int)$dateObj->format('w')];
            ?>
            <div class="diary-panel-header">
                <h3><?php echo $formattedDate; ?>（<?php echo $dayOfWeek; ?>）</h3>
            </div>
            <div class="no-diary">
                <p>この日の業務日誌はまだ作成されていません。</p>
                <a href="work_diary.php?date=<?php echo $selectedDate; ?>" class="create-btn">業務日誌を作成</a>
            </div>
        <?php else: ?>
            <div class="no-diary">
                <p>カレンダーから日付を選択してください。</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php renderPageEnd(); ?>
