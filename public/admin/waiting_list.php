<?php
/**
 * 待機児童管理ページ
 * 管理者・スタッフ用
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
require_once __DIR__ . '/../../includes/student_helper.php';

// 管理者またはスタッフのみアクセス可能
requireUserType(['admin', 'staff']);

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    die('教室IDが設定されていません');
}

// 曜日名の定義
$dayNames = ['日', '月', '火', '水', '木', '金', '土'];
$dayNamesLong = ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'];
$scheduledColumns = ['scheduled_sunday', 'scheduled_monday', 'scheduled_tuesday', 'scheduled_wednesday', 'scheduled_thursday', 'scheduled_friday', 'scheduled_saturday'];
$desiredColumns = ['desired_sunday', 'desired_monday', 'desired_tuesday', 'desired_wednesday', 'desired_thursday', 'desired_friday', 'desired_saturday'];

// 教室の定員設定を取得（存在しない場合はデフォルト値で作成）
$stmt = $pdo->prepare("SELECT * FROM classroom_capacity WHERE classroom_id = ? ORDER BY day_of_week");
$stmt->execute([$classroomId]);
$capacitySettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 定員設定がない場合はデフォルト値で初期化（すべて営業日として初期化）
if (count($capacitySettings) < 7) {
    $existingDays = array_column($capacitySettings, 'day_of_week');
    for ($day = 0; $day <= 6; $day++) {
        if (!in_array($day, $existingDays)) {
            $stmt = $pdo->prepare("INSERT INTO classroom_capacity (classroom_id, day_of_week, max_capacity, is_open) VALUES (?, ?, 10, 1)");
            $stmt->execute([$classroomId, $day]);
        }
    }
    // 再取得
    $stmt = $pdo->prepare("SELECT * FROM classroom_capacity WHERE classroom_id = ? ORDER BY day_of_week");
    $stmt->execute([$classroomId]);
    $capacitySettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 定員設定を曜日でインデックス化
$capacityByDay = [];
foreach ($capacitySettings as $cap) {
    $capacityByDay[$cap['day_of_week']] = $cap;
}

// 曜日別の利用者数を計算（在籍中・体験・短期利用）
$stmt = $pdo->prepare("
    SELECT
        SUM(scheduled_sunday) AS sunday_count,
        SUM(scheduled_monday) AS monday_count,
        SUM(scheduled_tuesday) AS tuesday_count,
        SUM(scheduled_wednesday) AS wednesday_count,
        SUM(scheduled_thursday) AS thursday_count,
        SUM(scheduled_friday) AS friday_count,
        SUM(scheduled_saturday) AS saturday_count
    FROM students
    WHERE classroom_id = ?
      AND status IN ('active', 'trial', 'short_term')
      AND is_active = 1
");
$stmt->execute([$classroomId]);
$currentUsage = $stmt->fetch(PDO::FETCH_ASSOC);

// 曜日別配列に変換
$usageByDay = [
    0 => (int)($currentUsage['sunday_count'] ?? 0),
    1 => (int)($currentUsage['monday_count'] ?? 0),
    2 => (int)($currentUsage['tuesday_count'] ?? 0),
    3 => (int)($currentUsage['wednesday_count'] ?? 0),
    4 => (int)($currentUsage['thursday_count'] ?? 0),
    5 => (int)($currentUsage['friday_count'] ?? 0),
    6 => (int)($currentUsage['saturday_count'] ?? 0),
];

// 曜日別の待機人数を計算
$stmt = $pdo->prepare("
    SELECT
        SUM(desired_sunday) AS sunday_count,
        SUM(desired_monday) AS monday_count,
        SUM(desired_tuesday) AS tuesday_count,
        SUM(desired_wednesday) AS wednesday_count,
        SUM(desired_thursday) AS thursday_count,
        SUM(desired_friday) AS friday_count,
        SUM(desired_saturday) AS saturday_count
    FROM students
    WHERE classroom_id = ?
      AND status = 'waiting'
");
$stmt->execute([$classroomId]);
$waitingUsage = $stmt->fetch(PDO::FETCH_ASSOC);

// 曜日別配列に変換
$waitingByDay = [
    0 => (int)($waitingUsage['sunday_count'] ?? 0),
    1 => (int)($waitingUsage['monday_count'] ?? 0),
    2 => (int)($waitingUsage['tuesday_count'] ?? 0),
    3 => (int)($waitingUsage['wednesday_count'] ?? 0),
    4 => (int)($waitingUsage['thursday_count'] ?? 0),
    5 => (int)($waitingUsage['friday_count'] ?? 0),
    6 => (int)($waitingUsage['saturday_count'] ?? 0),
];

// 待機児童一覧を取得
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as guardian_name
    FROM students s
    LEFT JOIN users u ON s.guardian_id = u.id
    WHERE s.classroom_id = ?
      AND s.status = 'waiting'
    ORDER BY s.desired_start_date ASC, s.created_at ASC
");
$stmt->execute([$classroomId]);
$waitingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 曜日別の在籍生徒一覧を取得
$studentsByDay = [];
for ($day = 0; $day <= 6; $day++) {
    $column = $scheduledColumns[$day];
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.grade_level, s.status, u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.classroom_id = ?
          AND s.status IN ('active', 'trial', 'short_term')
          AND s.is_active = 1
          AND s.{$column} = 1
        ORDER BY s.grade_level, s.student_name
    ");
    $stmt->execute([$classroomId]);
    $studentsByDay[$day] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 曜日別の待機児童一覧を取得
$waitingByDayList = [];
for ($day = 0; $day <= 6; $day++) {
    $column = $desiredColumns[$day];
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.grade_level, s.desired_start_date, u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.classroom_id = ?
          AND s.status = 'waiting'
          AND s.{$column} = 1
        ORDER BY s.desired_start_date ASC, s.student_name
    ");
    $stmt->execute([$classroomId]);
    $waitingByDayList[$day] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ページ開始
$currentPage = 'waiting_list';
renderPageStart($userType, $currentPage, '待機児童管理');
?>

<style>
.content-box {
    background: var(--md-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    margin-bottom: var(--spacing-xl);
}

.section-title {
    font-size: 20px;
    color: var(--text-primary);
    margin-bottom: var(--spacing-lg);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--md-purple);
}

.capacity-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
}

@media (max-width: 900px) {
    .capacity-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 600px) {
    .capacity-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.capacity-card {
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    text-align: center;
    transition: all var(--duration-fast);
    cursor: pointer;
}

.capacity-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.capacity-card.closed {
    background: var(--md-gray-5);
    opacity: 0.7;
}

.capacity-card.available {
    border: 2px solid var(--md-green);
}

.capacity-card.full {
    border: 2px solid var(--md-red);
}

.capacity-card .day-name {
    font-size: var(--text-headline);
    font-weight: 700;
    margin-bottom: var(--spacing-sm);
}

.capacity-card .day-name.sunday { color: var(--md-red); }
.capacity-card .day-name.saturday { color: var(--md-blue); }

.capacity-card .stats {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
    margin-bottom: var(--spacing-xs);
}

.capacity-card .availability {
    font-size: var(--text-subheadline);
    font-weight: 600;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--radius-sm);
    display: inline-block;
}

.capacity-card .availability.available {
    background: var(--md-green);
    color: white;
}

.capacity-card .availability.full {
    background: var(--md-red);
    color: white;
}

.capacity-card .availability.closed {
    background: var(--md-gray-4);
    color: white;
}

.capacity-card .waiting-badge {
    font-size: var(--text-caption-1);
    color: var(--md-orange);
    margin-top: var(--spacing-xs);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: var(--spacing-md);
    text-align: left;
    border-bottom: 1px solid var(--md-gray-5);
}

.data-table th {
    background: var(--md-bg-secondary);
    font-weight: 600;
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.data-table tr:hover {
    background: var(--md-bg-secondary);
}

.desired-days {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.desired-day {
    padding: 2px 8px;
    border-radius: var(--radius-xs);
    font-size: var(--text-caption-1);
    background: var(--md-purple);
    color: white;
}

.btn-admit {
    background: var(--md-green);
    color: white;
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--radius-sm);
    font-size: var(--text-caption-1);
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all var(--duration-fast);
}

.btn-admit:hover {
    background: #2da94f;
    transform: translateY(-1px);
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: var(--spacing-md);
}

@media (max-width: 900px) {
    .settings-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 600px) {
    .settings-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.settings-card {
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
    padding: var(--spacing-md);
    text-align: center;
}

.settings-card .day-label {
    font-weight: 700;
    margin-bottom: var(--spacing-sm);
}

.settings-card .day-label.sunday { color: var(--md-red); }
.settings-card .day-label.saturday { color: var(--md-blue); }

.settings-card input[type="number"] {
    width: 60px;
    padding: var(--spacing-xs);
    text-align: center;
    border: 1px solid var(--md-gray-4);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-sm);
}

.settings-card .checkbox-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
    font-size: var(--text-caption-1);
    cursor: pointer;
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--md-gray-5); }

.empty-message {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--text-secondary);
}

.alert {
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
}

.alert-success {
    background: rgba(52, 199, 89, 0.15);
    color: var(--md-green);
    border: 1px solid var(--md-green);
}

.alert-error {
    background: rgba(255, 59, 48, 0.15);
    color: var(--md-red);
    border: 1px solid var(--md-red);
}

/* 曜日詳細モーダル */
.day-detail-section {
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-xl);
    border-top: 1px solid var(--md-gray-5);
}

.day-tabs {
    display: flex;
    gap: var(--spacing-xs);
    margin-bottom: var(--spacing-lg);
    flex-wrap: wrap;
}

.day-tab {
    padding: var(--spacing-sm) var(--spacing-lg);
    border: 2px solid var(--md-gray-4);
    border-radius: var(--radius-md);
    background: var(--md-bg-secondary);
    cursor: pointer;
    font-weight: 600;
    transition: all var(--duration-fast);
}

.day-tab:hover {
    border-color: var(--md-purple);
}

.day-tab.active {
    background: var(--md-purple);
    color: white;
    border-color: var(--md-purple);
}

.day-tab.sunday { color: var(--md-red); }
.day-tab.saturday { color: var(--md-blue); }
.day-tab.sunday.active, .day-tab.saturday.active { color: white; }

.day-content {
    display: none;
}

.day-content.active {
    display: block;
}

.student-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--spacing-sm);
}

.student-item {
    background: var(--md-bg-secondary);
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
}

.student-item .name {
    font-weight: 600;
}

.student-item .grade {
    color: var(--text-secondary);
    font-size: var(--text-caption-1);
}

.status-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: var(--radius-xs);
    font-size: var(--text-caption-2);
    margin-left: var(--spacing-xs);
}

.status-badge.trial {
    background: rgba(255, 149, 0, 0.2);
    color: var(--md-orange);
}

.status-badge.short_term {
    background: rgba(0, 122, 255, 0.2);
    color: var(--md-blue);
}

.waiting-list-mini {
    margin-top: var(--spacing-md);
    padding-top: var(--spacing-md);
    border-top: 1px dashed var(--md-gray-4);
}

.waiting-list-mini h4 {
    color: var(--md-orange);
    font-size: var(--text-footnote);
    margin-bottom: var(--spacing-sm);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">待機児童管理</h1>
        <p class="page-subtitle">空き状況の確認と待機児童の管理</p>
    </div>
</div>

<a href="<?= $userType === 'admin' ? 'index.php' : '../staff/index.php' ?>" class="quick-link">← <?= $userType === 'admin' ? '管理画面' : 'スタッフ画面' ?>に戻る</a>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        switch($_GET['success']) {
            case 'admitted': echo '生徒を入所させました。'; break;
            case 'capacity_updated': echo '営業日・定員設定を更新しました。'; break;
            default: echo '操作が完了しました。';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
        エラー: <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<!-- 営業日・定員設定 -->
<div class="content-box">
    <h2 class="section-title">営業日・定員設定</h2>
    <form action="waiting_list_save.php" method="POST">
        <input type="hidden" name="action" value="update_capacity">
        <div class="settings-grid">
            <?php
            // 月〜日の順で表示
            $dayOrder = [1, 2, 3, 4, 5, 6, 0]; // 月火水木金土日
            foreach ($dayOrder as $day):
                $cap = $capacityByDay[$day] ?? ['max_capacity' => 10, 'is_open' => 1];
                $dayClass = ($day == 0) ? 'sunday' : (($day == 6) ? 'saturday' : '');
            ?>
                <div class="settings-card">
                    <div class="day-label <?= $dayClass ?>"><?= $dayNamesLong[$day] ?></div>
                    <div>
                        <label class="checkbox-label" style="margin-bottom: var(--spacing-sm);">
                            <input type="checkbox" name="is_open[<?= $day ?>]" value="1" <?= $cap['is_open'] ? 'checked' : '' ?>>
                            営業日
                        </label>
                    </div>
                    <div>
                        <label style="font-size: var(--text-caption-1); color: var(--text-secondary);">定員</label>
                        <input type="number" name="capacity[<?= $day ?>]" value="<?= $cap['max_capacity'] ?>" min="0" max="100">
                        <div style="font-size: var(--text-caption-2); color: var(--text-secondary);">名</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: right; margin-top: var(--spacing-lg);">
            <button type="submit" class="btn btn-primary">設定を保存</button>
        </div>
    </form>
</div>

<!-- 空き状況サマリー -->
<div class="content-box">
    <h2 class="section-title">曜日別 空き状況</h2>
    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg); font-size: var(--text-footnote);">カードをクリックすると、その曜日の利用者一覧が表示されます</p>
    <div class="capacity-grid">
        <?php
        foreach ($dayOrder as $day):
            $cap = $capacityByDay[$day] ?? ['max_capacity' => 10, 'is_open' => 1];
            $isOpen = $cap['is_open'];
            $maxCap = $cap['max_capacity'];
            $current = $usageByDay[$day];
            $waiting = $waitingByDay[$day];
            $available = max(0, $maxCap - $current);
            $dayClass = ($day == 0) ? 'sunday' : (($day == 6) ? 'saturday' : '');
            $cardClass = !$isOpen ? 'closed' : ($available > 0 ? 'available' : 'full');
        ?>
            <div class="capacity-card <?= $cardClass ?>" onclick="showDayDetail(<?= $day ?>)">
                <div class="day-name <?= $dayClass ?>"><?= $dayNames[$day] ?></div>
                <?php if ($isOpen): ?>
                    <div class="stats">利用中: <?= $current ?> / <?= $maxCap ?>名</div>
                    <div class="availability <?= $available > 0 ? 'available' : 'full' ?>">
                        <?= $available > 0 ? "空き{$available}名" : "満員" ?>
                    </div>
                    <?php if ($waiting > 0): ?>
                        <div class="waiting-badge">待機 <?= $waiting ?>名</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="availability closed">休業日</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 曜日別詳細 -->
    <div class="day-detail-section">
        <div class="day-tabs">
            <?php foreach ($dayOrder as $day):
                $dayClass = ($day == 0) ? 'sunday' : (($day == 6) ? 'saturday' : '');
            ?>
                <div class="day-tab <?= $dayClass ?>" data-day="<?= $day ?>" onclick="showDayDetail(<?= $day ?>)"><?= $dayNamesLong[$day] ?></div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($dayOrder as $day):
            $cap = $capacityByDay[$day] ?? ['max_capacity' => 10, 'is_open' => 1];
            $isOpen = $cap['is_open'];
            $students = $studentsByDay[$day];
            $waitingList = $waitingByDayList[$day];
        ?>
            <div class="day-content" id="day-content-<?= $day ?>">
                <?php if (!$isOpen): ?>
                    <div class="empty-message">この曜日は休業日です</div>
                <?php else: ?>
                    <h3 style="margin-bottom: var(--spacing-md);"><?= $dayNamesLong[$day] ?>の利用者 (<?= count($students) ?>名)</h3>
                    <?php if (empty($students)): ?>
                        <div class="empty-message" style="padding: var(--spacing-lg);">利用者がいません</div>
                    <?php else: ?>
                        <div class="student-list">
                            <?php foreach ($students as $student): ?>
                                <div class="student-item">
                                    <span class="name"><?= htmlspecialchars($student['student_name']) ?></span>
                                    <?php if ($student['status'] === 'trial'): ?>
                                        <span class="status-badge trial">体験</span>
                                    <?php elseif ($student['status'] === 'short_term'): ?>
                                        <span class="status-badge short_term">短期</span>
                                    <?php endif; ?>
                                    <div class="grade"><?= getGradeLevelLabel($student['grade_level']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($waitingList)): ?>
                        <div class="waiting-list-mini">
                            <h4>待機中 (<?= count($waitingList) ?>名)</h4>
                            <div class="student-list">
                                <?php foreach ($waitingList as $student): ?>
                                    <div class="student-item" style="border-left: 3px solid var(--md-orange);">
                                        <span class="name"><?= htmlspecialchars($student['student_name']) ?></span>
                                        <div class="grade">
                                            <?= getGradeLevelLabel($student['grade_level']) ?>
                                            <?php if ($student['desired_start_date']): ?>
                                                / 希望: <?= date('n/j', strtotime($student['desired_start_date'])) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 待機児童一覧 -->
<div class="content-box">
    <h2 class="section-title">待機児童一覧 (<?= count($waitingStudents) ?>名)</h2>
    <?php if (empty($waitingStudents)): ?>
        <div class="empty-message">
            現在、待機児童はいません。
        </div>
    <?php else: ?>
        <!-- 曜日別待機人数サマリー -->
        <div style="margin-bottom: var(--spacing-xl);">
            <h3 style="font-size: var(--text-subheadline); color: var(--text-secondary); margin-bottom: var(--spacing-md);">曜日別待機人数</h3>
            <div class="capacity-grid">
                <?php foreach ($dayOrder as $day):
                    $waiting = $waitingByDay[$day];
                    $cap = $capacityByDay[$day] ?? ['is_open' => 1];
                    $isOpen = $cap['is_open'];
                    $dayClass = ($day == 0) ? 'sunday' : (($day == 6) ? 'saturday' : '');
                ?>
                    <div class="waiting-summary-card" style="background: var(--md-bg-secondary); border-radius: var(--radius-md); padding: var(--spacing-md); text-align: center; <?= !$isOpen ? 'opacity: 0.5;' : '' ?>">
                        <div class="day-name <?= $dayClass ?>" style="font-weight: 700; margin-bottom: var(--spacing-xs);"><?= $dayNames[$day] ?></div>
                        <?php if ($isOpen): ?>
                            <?php if ($waiting > 0): ?>
                                <div style="font-size: var(--text-title-2); font-weight: 700; color: var(--md-orange);"><?= $waiting ?>名</div>
                            <?php else: ?>
                                <div style="font-size: var(--text-footnote); color: var(--text-secondary);">待機なし</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="font-size: var(--text-footnote); color: var(--text-secondary);">休業日</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 入所希望日別一覧 -->
        <?php
        // 入所希望日でグループ化
        $groupedByDate = [];
        foreach ($waitingStudents as $student) {
            $dateKey = $student['desired_start_date'] ?: '9999-99-99'; // 未定は最後に
            if (!isset($groupedByDate[$dateKey])) {
                $groupedByDate[$dateKey] = [];
            }
            $groupedByDate[$dateKey][] = $student;
        }
        ksort($groupedByDate);
        ?>

        <h3 style="font-size: var(--text-subheadline); color: var(--text-secondary); margin-bottom: var(--spacing-md);">入所希望日別一覧</h3>

        <?php foreach ($groupedByDate as $dateKey => $students): ?>
            <div class="waiting-date-group" style="margin-bottom: var(--spacing-lg); background: var(--md-bg-secondary); border-radius: var(--radius-md); overflow: hidden;">
                <div style="background: var(--md-orange); color: white; padding: var(--spacing-sm) var(--spacing-md); font-weight: 600;">
                    <?php if ($dateKey === '9999-99-99'): ?>
                        入所希望日 未定
                    <?php else: ?>
                        <?= date('Y年n月j日', strtotime($dateKey)) ?> 入所希望
                    <?php endif; ?>
                    <span style="opacity: 0.8; margin-left: var(--spacing-sm);">(<?= count($students) ?>名)</span>
                </div>
                <div style="padding: var(--spacing-md);">
                    <?php foreach ($students as $student): ?>
                        <div class="waiting-student-row" style="display: flex; align-items: center; gap: var(--spacing-md); padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--md-gray-5); flex-wrap: wrap;">
                            <div style="min-width: 120px;">
                                <strong><?= htmlspecialchars($student['student_name']) ?></strong>
                                <div style="font-size: var(--text-caption-1); color: var(--text-secondary);"><?= getGradeLevelLabel($student['grade_level']) ?></div>
                            </div>
                            <div style="min-width: 80px;">
                                <?php if ($student['desired_weekly_count']): ?>
                                    <span style="background: var(--md-blue); color: white; padding: 2px 8px; border-radius: var(--radius-sm); font-size: var(--text-caption-1); font-weight: 600;">週<?= $student['desired_weekly_count'] ?>回</span>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary); font-size: var(--text-caption-1);">回数未定</span>
                                <?php endif; ?>
                            </div>
                            <div class="desired-days" style="flex: 1;">
                                <?php if ($student['desired_monday']): ?><span class="desired-day">月</span><?php endif; ?>
                                <?php if ($student['desired_tuesday']): ?><span class="desired-day">火</span><?php endif; ?>
                                <?php if ($student['desired_wednesday']): ?><span class="desired-day">水</span><?php endif; ?>
                                <?php if ($student['desired_thursday']): ?><span class="desired-day">木</span><?php endif; ?>
                                <?php if ($student['desired_friday']): ?><span class="desired-day">金</span><?php endif; ?>
                                <?php if ($student['desired_saturday']): ?><span class="desired-day">土</span><?php endif; ?>
                                <?php if ($student['desired_sunday']): ?><span class="desired-day">日</span><?php endif; ?>
                            </div>
                            <?php if ($student['waiting_notes']): ?>
                                <div style="flex-basis: 100%; font-size: var(--text-caption-1); color: var(--text-secondary); padding-left: var(--spacing-sm); margin-top: var(--spacing-xs);">
                                    備考: <?= htmlspecialchars($student['waiting_notes']) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <form action="waiting_list_save.php" method="POST" style="display: inline;" onsubmit="return confirm('<?= htmlspecialchars($student['student_name']) ?>さんを入所させますか？\n希望曜日が利用曜日に自動設定されます。');">
                                    <input type="hidden" name="action" value="admit">
                                    <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                    <button type="submit" class="btn-admit">入所</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function showDayDetail(day) {
    // タブの切り替え
    document.querySelectorAll('.day-tab').forEach(tab => {
        tab.classList.remove('active');
        if (parseInt(tab.dataset.day) === day) {
            tab.classList.add('active');
        }
    });

    // コンテンツの切り替え
    document.querySelectorAll('.day-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById('day-content-' + day).classList.add('active');
}

// 初期表示（最初の営業日を表示）
document.addEventListener('DOMContentLoaded', function() {
    const firstTab = document.querySelector('.day-tab');
    if (firstTab) {
        showDayDetail(parseInt(firstTab.dataset.day));
    }
});
</script>

<?php renderPageEnd(); ?>
