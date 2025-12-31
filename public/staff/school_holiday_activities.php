<?php
/**
 * 学校休業日活動設定ページ
 * 学校が休みの日（夏休み、春休み等）に施設が活動する日を設定
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    $_SESSION['error_message'] = '教室が選択されていません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 表示する年月を取得
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// 月の範囲を調整
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// 前月・次月の計算
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

// 月の初日と日数
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$firstDayOfWeek = (int)date('w', $firstDay);

// この月の学校休業日活動を取得
$stmt = $pdo->prepare("
    SELECT activity_date, note
    FROM school_holiday_activities
    WHERE classroom_id = ? AND YEAR(activity_date) = ? AND MONTH(activity_date) = ?
");
$stmt->execute([$classroomId, $year, $month]);
$activityDates = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $activityDates[$row['activity_date']] = $row['note'];
}

// この月の休日を取得（休日は学校休業日活動にできない）
$stmt = $pdo->prepare("
    SELECT holiday_date, holiday_name
    FROM holidays
    WHERE classroom_id = ? AND YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?
");
$stmt->execute([$classroomId, $year, $month]);
$holidayDates = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $holidayDates[$row['holiday_date']] = $row['holiday_name'];
}

// ページ開始
$currentPage = 'school_holiday_activities';
renderPageStart('staff', $currentPage, '学校休業日活動設定');
?>

<style>
.calendar-container {
    background: var(--md-bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    max-width: 800px;
    margin: 0 auto;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.calendar-header h2 {
    color: var(--text-primary);
    font-size: var(--text-title-2);
    font-weight: 600;
}

.calendar-nav {
    display: flex;
    gap: 8px;
}

.calendar-nav a {
    padding: 8px 16px;
    background: var(--md-purple);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    transition: all var(--duration-normal) var(--ease-out);
}

.calendar-nav a:hover {
    background: var(--md-blue);
    transform: translateY(-1px);
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-day-header {
    text-align: center;
    padding: 10px 5px;
    font-weight: bold;
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    background: var(--md-gray-6);
    border-radius: var(--radius-sm);
}

.calendar-day-header.sunday { color: #ef4444; }
.calendar-day-header.saturday { color: #3b82f6; }

.calendar-day {
    min-height: 80px;
    border: 1px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
    padding: 8px;
    background: var(--md-bg-secondary);
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
}

.calendar-day:hover:not(.empty):not(.holiday) {
    background: var(--md-bg-tertiary);
    transform: scale(1.02);
}

.calendar-day.empty {
    background: var(--md-gray-6);
    opacity: 0.5;
    cursor: default;
}

.calendar-day.holiday {
    background: #fef2f2;
    cursor: not-allowed;
}

.calendar-day.selected {
    background: #dbeafe;
    border-color: #3b82f6;
}

.calendar-day-num {
    font-size: var(--text-subhead);
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.calendar-day-num.sunday { color: #ef4444; }
.calendar-day-num.saturday { color: #3b82f6; }

.day-checkbox {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
}

.day-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.day-checkbox label {
    font-size: 11px;
    color: var(--text-secondary);
    cursor: pointer;
}

.day-status {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 5px;
    display: inline-block;
}

.status-school-holiday {
    background: #dbeafe;
    color: #1e40af;
}

.status-holiday {
    background: #fee2e2;
    color: #b91c1c;
}

.status-weekday {
    background: #d1fae5;
    color: #065f46;
}

.info-box {
    background: rgba(0,122,255,0.1);
    border-left: 4px solid var(--md-blue);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    color: var(--text-primary);
    font-size: var(--text-subhead);
    line-height: 1.6;
}

.legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: var(--text-subhead);
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-color.school-holiday { background: #dbeafe; border: 1px solid #3b82f6; }
.legend-color.holiday { background: #fee2e2; border: 1px solid #ef4444; }
.legend-color.weekday { background: white; border: 1px solid #d1d5db; }

.submit-section {
    text-align: center;
    margin-top: var(--spacing-xl);
}

.submit-btn {
    padding: 12px 40px;
    background: var(--md-blue);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,122,255,0.4);
}

.success-message {
    background: rgba(52, 199, 89, 0.15);
    color: var(--md-green);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--md-green);
}

.error-message {
    background: rgba(255, 59, 48, 0.15);
    color: var(--md-red);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--md-red);
}

@media (max-width: 768px) {
    .calendar-day {
        min-height: 60px;
        padding: 5px;
    }
    .calendar-day-num {
        font-size: 12px;
    }
    .day-checkbox label {
        display: none;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">学校休業日活動設定</h1>
        <p class="page-subtitle">学校が休みの日（夏休み・春休み等）に活動する日を設定</p>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="success-message">
    <?= htmlspecialchars($_SESSION['success_message']) ?>
    <?php unset($_SESSION['success_message']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="error-message">
    <?= htmlspecialchars($_SESSION['error_message']) ?>
    <?php unset($_SESSION['error_message']); ?>
</div>
<?php endif; ?>

<div class="info-box">
    <strong>学校休業日活動とは？</strong><br>
    学校が休みの日（夏休み、春休み、冬休み等）に施設で活動する日です。<br>
    チェックを入れた日は保護者カレンダーに「学校休業日活動」と表示されます。<br>
    チェックがない日は「平日活動」として表示されます。<br>
    <span style="color: #ef4444;">※ 休日として登録されている日は選択できません。</span>
</div>

<div class="legend">
    <div class="legend-item">
        <div class="legend-color school-holiday"></div>
        <span>学校休業日活動</span>
    </div>
    <div class="legend-item">
        <div class="legend-color holiday"></div>
        <span>休日（選択不可）</span>
    </div>
    <div class="legend-item">
        <div class="legend-color weekday"></div>
        <span>平日活動</span>
    </div>
</div>

<form id="activityForm" method="POST" action="school_holiday_activities_save.php">
    <input type="hidden" name="year" value="<?= $year ?>">
    <input type="hidden" name="month" value="<?= $month ?>">

    <div class="calendar-container">
        <div class="calendar-header">
            <div class="calendar-nav">
                <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>">← 前月</a>
                <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>">今月</a>
            </div>
            <h2><?= $year ?>年<?= $month ?>月</h2>
            <div class="calendar-nav">
                <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>">次月 →</a>
            </div>
        </div>

        <div class="calendar-grid">
            <?php
            $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
            foreach ($weekDays as $index => $day):
                $class = '';
                if ($index === 0) $class = 'sunday';
                if ($index === 6) $class = 'saturday';
            ?>
            <div class="calendar-day-header <?= $class ?>"><?= $day ?></div>
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
                $isHoliday = array_key_exists($dateStr, $holidayDates);
                $isSchoolHoliday = array_key_exists($dateStr, $activityDates);

                $dayNumClass = '';
                if ($dayOfWeek === 0) $dayNumClass = 'sunday';
                if ($dayOfWeek === 6) $dayNumClass = 'saturday';

                $cellClass = 'calendar-day';
                if ($isHoliday) $cellClass .= ' holiday';
                elseif ($isSchoolHoliday) $cellClass .= ' selected';
            ?>
            <div class="<?= $cellClass ?>" data-date="<?= $dateStr ?>">
                <div class="calendar-day-num <?= $dayNumClass ?>"><?= $day ?></div>
                <?php if ($isHoliday): ?>
                    <div class="day-status status-holiday">休日</div>
                    <div style="font-size: 9px; color: #b91c1c; margin-top: 2px;">
                        <?= htmlspecialchars($holidayDates[$dateStr], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php else: ?>
                    <div class="day-checkbox">
                        <input type="checkbox"
                               name="activity_dates[]"
                               value="<?= $dateStr ?>"
                               id="date_<?= $dateStr ?>"
                               <?= $isSchoolHoliday ? 'checked' : '' ?>>
                        <label for="date_<?= $dateStr ?>">休業日活動</label>
                    </div>
                    <?php if ($isSchoolHoliday): ?>
                        <div class="day-status status-school-holiday">休業日活動</div>
                    <?php else: ?>
                        <div class="day-status status-weekday">平日活動</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <div class="submit-section">
            <button type="submit" class="submit-btn"><span class="material-symbols-outlined">save</span> この月の設定を保存</button>
        </div>
    </div>
</form>

<script>
// カレンダーセルクリックでチェックボックスをトグル
document.querySelectorAll('.calendar-day:not(.empty):not(.holiday)').forEach(cell => {
    cell.addEventListener('click', function(e) {
        if (e.target.type === 'checkbox' || e.target.tagName === 'LABEL') return;

        const checkbox = this.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateCellStatus(this, checkbox.checked);
        }
    });
});

// チェックボックス変更時のスタイル更新
document.querySelectorAll('.day-checkbox input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const cell = this.closest('.calendar-day');
        updateCellStatus(cell, this.checked);
    });
});

function updateCellStatus(cell, isChecked) {
    const statusDiv = cell.querySelector('.day-status');
    if (isChecked) {
        cell.classList.add('selected');
        statusDiv.className = 'day-status status-school-holiday';
        statusDiv.textContent = '休業日活動';
    } else {
        cell.classList.remove('selected');
        statusDiv.className = 'day-status status-weekday';
        statusDiv.textContent = '平日活動';
    }
}
</script>

<?php renderPageEnd(); ?>
