<?php
/**
 * 利用日変更ページ
 * 生徒ごとに利用日の追加・キャンセルを管理する
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 選択された生徒ID
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

// 選択された年月を取得（デフォルトは今月）
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// 月の情報
$firstDay = strtotime("$year-$month-1");
$lastDay = strtotime(date('Y-m-t', $firstDay));
$daysInMonth = date('t', $firstDay);

// 前月・次月の計算
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

// 生徒一覧を取得（自分の教室のみ）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.grade_level,
               s.scheduled_sunday, s.scheduled_monday, s.scheduled_tuesday,
               s.scheduled_wednesday, s.scheduled_thursday, s.scheduled_friday, s.scheduled_saturday
        FROM students s
        WHERE s.is_active = 1 AND s.classroom_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.grade_level,
               s.scheduled_sunday, s.scheduled_monday, s.scheduled_tuesday,
               s.scheduled_wednesday, s.scheduled_thursday, s.scheduled_friday, s.scheduled_saturday
        FROM students s
        WHERE s.is_active = 1
        ORDER BY s.student_name
    ");
}
$students = $stmt->fetchAll();

// 選択された生徒が無い場合、最初の生徒を選択
if (!$studentId && !empty($students)) {
    $studentId = $students[0]['id'];
}

// 選択された生徒の情報を取得
$selectedStudent = null;
$scheduledDays = [];
foreach ($students as $student) {
    if ($student['id'] == $studentId) {
        $selectedStudent = $student;
        // 通常利用曜日を取得
        $dayColumns = [
            0 => 'scheduled_sunday',
            1 => 'scheduled_monday',
            2 => 'scheduled_tuesday',
            3 => 'scheduled_wednesday',
            4 => 'scheduled_thursday',
            5 => 'scheduled_friday',
            6 => 'scheduled_saturday'
        ];
        foreach ($dayColumns as $dayNum => $col) {
            if (!empty($student[$col])) {
                $scheduledDays[] = $dayNum;
            }
        }
        break;
    }
}

// 休日を取得
$stmt = $pdo->prepare("
    SELECT holiday_date FROM holidays
    WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? AND classroom_id = ?
");
$stmt->execute([$year, $month, $classroomId]);
$holidayDates = array_column($stmt->fetchAll(), 'holiday_date');

// 追加利用日を取得
$additionalUsages = [];
if ($studentId) {
    $monthStart = date('Y-m-01', $firstDay);
    $monthEnd = date('Y-m-t', $firstDay);
    $stmt = $pdo->prepare("
        SELECT usage_date, notes FROM additional_usages
        WHERE student_id = ? AND usage_date BETWEEN ? AND ?
    ");
    $stmt->execute([$studentId, $monthStart, $monthEnd]);
    while ($row = $stmt->fetch()) {
        $additionalUsages[$row['usage_date']] = $row['notes'];
    }
}

// 欠席・キャンセル日を取得
$absenceDates = [];
if ($studentId) {
    $monthStart = date('Y-m-01', $firstDay);
    $monthEnd = date('Y-m-t', $firstDay);
    $stmt = $pdo->prepare("
        SELECT absence_date, reason FROM absence_notifications
        WHERE student_id = ? AND absence_date BETWEEN ? AND ?
    ");
    $stmt->execute([$studentId, $monthStart, $monthEnd]);
    while ($row = $stmt->fetch()) {
        $absenceDates[$row['absence_date']] = $row['reason'];
    }
}

// ページ開始
$currentPage = 'additional_usage';
renderPageStart('staff', $currentPage, '利用日変更');
?>

<style>
.student-selector {
    background: var(--md-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
}

.student-selector select {
    width: 100%;
    padding: var(--spacing-md);
    border: 1px solid var(--md-gray-4);
    border-radius: var(--radius-sm);
    font-size: var(--text-body);
    background: var(--md-bg-primary);
}

.calendar-section {
    background: var(--md-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.calendar-header h2 {
    font-size: var(--text-title-3);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.calendar-nav {
    display: flex;
    gap: var(--spacing-md);
}

.calendar-nav a {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-subhead);
}

.calendar-nav a:hover {
    background: var(--md-gray-5);
}

.calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    background: var(--md-gray-5);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.calendar-day-header {
    background: var(--md-bg-secondary);
    padding: var(--spacing-md);
    text-align: center;
    font-weight: 600;
    font-size: var(--text-subhead);
    color: var(--text-secondary);
}

.calendar-day-header.sunday { color: var(--md-red); }
.calendar-day-header.saturday { color: var(--md-blue); }

.calendar-day {
    background: var(--md-bg-primary);
    min-height: 100px;
    padding: var(--spacing-sm);
    position: relative;
}

.calendar-day.empty {
    background: var(--md-bg-secondary);
}

.calendar-day.holiday {
    background: rgba(218, 30, 40, 0.15);
}

.calendar-day.regular-day {
    background: var(--cds-blue-60);
}

.calendar-day.today {
    border: 2px solid var(--md-green);
}

.day-number {
    font-weight: 600;
    font-size: var(--text-body);
    margin-bottom: var(--spacing-sm);
}

.day-number.sunday { color: var(--md-red); }
.day-number.saturday { color: var(--md-blue); }

.day-labels {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: var(--spacing-sm);
}

.day-label {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 0;
}

.day-label.regular {
    background: var(--cds-blue-60);
    color: var(--cds-blue-60);
}

.day-label.holiday {
    background: rgba(218, 30, 40, 0.15);
    color: var(--cds-support-error);
}

.day-label.additional {
    background: rgba(36, 161, 72, 0.15);
    color: var(--cds-support-success);
}

.day-label.cancelled {
    background: rgba(218, 30, 40, 0.15);
    color: var(--cds-support-error);
}

.usage-checkbox {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    margin-top: var(--spacing-sm);
}

.usage-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--md-green);
}

.usage-checkbox label {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    cursor: pointer;
}

.usage-checkbox.checked label {
    color: var(--md-green);
    font-weight: 600;
}

.legend {
    display: flex;
    gap: var(--spacing-lg);
    margin-top: var(--spacing-lg);
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
}

.legend-box {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.save-button {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: var(--spacing-md) var(--spacing-xl);
    background: var(--cds-support-success);
    color: white;
    border: none;
    border-radius: 0;
    font-size: var(--text-body);
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(36, 161, 72, 0.4);
    display: none;
}

.save-button:hover {
    background: var(--cds-support-success);
}

.save-button.show {
    display: block;
}

.toast {
    position: fixed;
    bottom: 80px;
    right: 20px;
    padding: var(--spacing-md) var(--spacing-lg);
    background: var(--md-gray-1);
    color: white;
    border-radius: var(--radius-md);
    font-size: var(--text-subhead);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    z-index: 1000;
}

.toast.show {
    opacity: 1;
    transform: translateY(0);
}

.toast.success {
    background: var(--md-green);
}

.toast.error {
    background: var(--md-red);
}

@media (max-width: 768px) {
    .calendar-day {
        min-height: 80px;
        padding: 4px;
    }

    .day-number {
        font-size: var(--text-subhead);
    }

    .day-label {
        font-size: 8px;
        padding: 1px 3px;
    }

    .usage-checkbox input[type="checkbox"] {
        width: 16px;
        height: 16px;
    }

    .usage-checkbox label {
        font-size: 10px;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">利用日変更</h1>
        <p class="page-subtitle">利用日の追加・キャンセルを管理</p>
    </div>
</div>

<!-- 生徒選択 -->
<div class="student-selector">
    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);">生徒を選択</label>
    <select id="studentSelect" onchange="changeStudent(this.value)">
        <?php foreach ($students as $student): ?>
            <option value="<?= $student['id'] ?>" <?= $student['id'] == $studentId ? 'selected' : '' ?>>
                <?= htmlspecialchars($student['student_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<?php if ($selectedStudent): ?>
<!-- カレンダーセクション -->
<div class="calendar-section">
    <div class="calendar-header">
        <h2><?= $year ?>年<?= $month ?>月</h2>
        <div class="calendar-nav">
            <a href="?student_id=<?= $studentId ?>&year=<?= $prevYear ?>&month=<?= $prevMonth ?>">← 前月</a>
            <a href="?student_id=<?= $studentId ?>&year=<?= date('Y') ?>&month=<?= date('n') ?>">今月</a>
            <a href="?student_id=<?= $studentId ?>&year=<?= $nextYear ?>&month=<?= $nextMonth ?>">次月 →</a>
        </div>
    </div>

    <div class="calendar">
        <?php
        // 曜日ヘッダー
        $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
        foreach ($weekDays as $index => $day) {
            $class = '';
            if ($index === 0) $class = 'sunday';
            if ($index === 6) $class = 'saturday';
            echo "<div class='calendar-day-header $class'>$day</div>";
        }

        // カレンダーの空白セル
        $startDayOfWeek = date('w', $firstDay);
        for ($i = 0; $i < $startDayOfWeek; $i++) {
            echo "<div class='calendar-day empty'></div>";
        }

        // 日付セル
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $dayOfWeek = date('w', strtotime($currentDate));

            $classes = ['calendar-day'];
            $isToday = ($currentDate === date('Y-m-d'));
            $isHoliday = in_array($currentDate, $holidayDates);
            $isRegularDay = in_array($dayOfWeek, $scheduledDays) && !$isHoliday;
            $isAdditional = array_key_exists($currentDate, $additionalUsages);
            $isCancelled = array_key_exists($currentDate, $absenceDates);

            if ($isToday) $classes[] = 'today';
            if ($isHoliday) $classes[] = 'holiday';
            if ($isRegularDay && !$isCancelled) $classes[] = 'regular-day';

            $dayClass = '';
            if ($dayOfWeek === 0) $dayClass = 'sunday';
            if ($dayOfWeek === 6) $dayClass = 'saturday';

            echo "<div class='" . implode(' ', $classes) . "'>";
            echo "<div class='day-number $dayClass'>$day</div>";

            echo "<div class='day-labels'>";
            if ($isHoliday) {
                echo "<span class='day-label holiday'>休日</span>";
            }
            if ($isRegularDay && !$isCancelled) {
                echo "<span class='day-label regular'>通常</span>";
            }
            if ($isCancelled) {
                echo "<span class='day-label cancelled'>キャンセル</span>";
            }
            if ($isAdditional) {
                echo "<span class='day-label additional'>追加</span>";
            }
            echo "</div>";

            // 休日以外はチェックボックスを表示
            if (!$isHoliday) {
                // 利用状態を判定: 通常利用日で未キャンセル、または追加利用
                $isUsing = ($isRegularDay && !$isCancelled) || $isAdditional;
                $checked = $isUsing ? 'checked' : '';
                $checkedClass = $isUsing ? 'checked' : '';

                // データ属性: 日付、通常利用日かどうか
                $dataType = $isRegularDay ? 'regular' : 'additional';

                echo "<div class='usage-checkbox $checkedClass'>";
                echo "<input type='checkbox' id='usage_$currentDate' data-date='$currentDate' data-type='$dataType' $checked onchange='toggleUsage(this)'>";
                echo "<label for='usage_$currentDate'>利用</label>";
                echo "</div>";
            }

            echo "</div>";
        }
        ?>
    </div>

    <div class="legend">
        <div class="legend-item">
            <div class="legend-box" style="background: var(--cds-blue-60); border: 1px solid var(--cds-border-subtle-00);"></div>
            <span>通常利用日</span>
        </div>
        <div class="legend-item">
            <div class="legend-box" style="background: rgba(36, 161, 72, 0.15); border: 1px solid var(--cds-border-subtle-00);"></div>
            <span>追加利用</span>
        </div>
        <div class="legend-item">
            <div class="legend-box" style="background: rgba(218, 30, 40, 0.15); border: 1px solid var(--cds-border-subtle-00);"></div>
            <span>キャンセル / 休日</span>
        </div>
    </div>
</div>
<?php else: ?>
<div style="text-align: center; padding: 60px; color: var(--text-tertiary);">
    <p>生徒が登録されていません</p>
</div>
<?php endif; ?>

<button id="saveButton" class="save-button" onclick="saveChanges()">変更を保存</button>
<div id="toast" class="toast"></div>

<?php
$inlineJs = <<<JS
const studentId = {$studentId};
let changes = {};

function changeStudent(id) {
    const year = new URLSearchParams(window.location.search).get('year') || new Date().getFullYear();
    const month = new URLSearchParams(window.location.search).get('month') || (new Date().getMonth() + 1);
    window.location.href = '?student_id=' + id + '&year=' + year + '&month=' + month;
}

function toggleUsage(checkbox) {
    const date = checkbox.dataset.date;
    const type = checkbox.dataset.type; // 'regular' or 'additional'
    const isChecked = checkbox.checked;
    const parent = checkbox.parentElement;
    const labelsDiv = parent.previousElementSibling;
    const dayCell = parent.closest('.calendar-day');

    if (isChecked) {
        parent.classList.add('checked');

        if (type === 'regular') {
            // 通常利用日の復活: キャンセルラベルを削除、通常ラベルを追加
            const cancelledLabel = labelsDiv.querySelector('.cancelled');
            if (cancelledLabel) cancelledLabel.remove();

            if (!labelsDiv.querySelector('.regular')) {
                const label = document.createElement('span');
                label.className = 'day-label regular';
                label.textContent = '通常';
                labelsDiv.insertBefore(label, labelsDiv.firstChild);
            }
            dayCell.classList.add('regular-day');

            changes[date] = { action: 'restore', type: type };
        } else {
            // 追加利用
            if (!labelsDiv.querySelector('.additional')) {
                const label = document.createElement('span');
                label.className = 'day-label additional';
                label.textContent = '追加';
                labelsDiv.appendChild(label);
            }

            changes[date] = { action: 'add', type: type };
        }
    } else {
        parent.classList.remove('checked');

        if (type === 'regular') {
            // 通常利用日のキャンセル: 通常ラベルを削除、キャンセルラベルを追加
            const regularLabel = labelsDiv.querySelector('.regular');
            if (regularLabel) regularLabel.remove();

            if (!labelsDiv.querySelector('.cancelled')) {
                const label = document.createElement('span');
                label.className = 'day-label cancelled';
                label.textContent = 'キャンセル';
                labelsDiv.appendChild(label);
            }
            dayCell.classList.remove('regular-day');

            changes[date] = { action: 'cancel', type: type };
        } else {
            // 追加利用の削除
            const additionalLabel = labelsDiv.querySelector('.additional');
            if (additionalLabel) additionalLabel.remove();

            changes[date] = { action: 'remove', type: type };
        }
    }

    updateSaveButton();
}

function updateSaveButton() {
    const saveButton = document.getElementById('saveButton');
    if (Object.keys(changes).length > 0) {
        saveButton.classList.add('show');
    } else {
        saveButton.classList.remove('show');
    }
}

function saveChanges() {
    const saveButton = document.getElementById('saveButton');
    saveButton.disabled = true;
    saveButton.textContent = '保存中...';

    fetch('additional_usage_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            student_id: studentId,
            changes: changes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('保存しました', 'success');
            changes = {};
            updateSaveButton();
            // 1秒後にリロードして最新の状態を表示
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('エラー: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('エラーが発生しました', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        saveButton.disabled = false;
        saveButton.textContent = '変更を保存';
    });
}

function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
