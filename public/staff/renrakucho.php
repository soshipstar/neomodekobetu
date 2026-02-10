<?php
/**
 * 連絡帳入力ページ（スタッフ用）
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/student_helper.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室の対象学年設定を取得
$targetGrades = getClassroomTargetGrades($pdo, $classroomId);

// 学年フィルター取得
$gradeFilter = $_GET['grade'] ?? 'all';

// 日付を取得（URLパラメータから、または本日）
$today = $_GET['date'] ?? date('Y-m-d');

// 本日の曜日を取得
$todayDayOfWeek = date('w', strtotime($today));
$dayColumns = [
    0 => 'scheduled_sunday',
    1 => 'scheduled_monday',
    2 => 'scheduled_tuesday',
    3 => 'scheduled_wednesday',
    4 => 'scheduled_thursday',
    5 => 'scheduled_friday',
    6 => 'scheduled_saturday'
];
$todayColumn = $dayColumns[$todayDayOfWeek];

// 本日が休日かチェック（自分の教室の休日のみ）
$stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ? AND classroom_id = ?");
$stmt->execute([$today, $classroomId]);
$isTodayHoliday = $stmt->fetchColumn() > 0;

// 本日の予定参加者IDを取得（自分の教室の生徒のみ）
$scheduledStudentIds = [];
if (!$isTodayHoliday) {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT s.id
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE s.is_active = 1 AND s.$todayColumn = 1 AND u.classroom_id = ?
        ");
        $stmt->execute([$classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM students
            WHERE is_active = 1 AND $todayColumn = 1
        ");
        $stmt->execute();
    }
    $scheduledStudentIds = array_column($stmt->fetchAll(), 'id');
}

// 生徒を取得（学年フィルターと本日の予定参加者フィルター対応、教室フィルタリング）
if ($classroomId) {
    $sql = "
        SELECT s.id, s.student_name, s.grade_level, s.birth_date, s.grade_adjustment
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = :classroom_id
    ";
} else {
    $sql = "
        SELECT id, student_name, grade_level, birth_date, grade_adjustment
        FROM students
        WHERE is_active = 1
    ";
}

if ($gradeFilter === 'scheduled') {
    // 本日の予定参加者フィルター
    if (empty($scheduledStudentIds)) {
        $allStudents = [];
    } else {
        // 名前付きプレースホルダーを生成
        $placeholders = [];
        $params = $classroomId ? ['classroom_id' => $classroomId] : [];
        foreach ($scheduledStudentIds as $index => $id) {
            $key = 'student_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $placeholdersStr = implode(',', $placeholders);
        $sql .= " AND " . ($classroomId ? "s.id" : "id") . " IN ($placeholdersStr)";
        $sql .= " ORDER BY " . ($classroomId ? "s.grade_level, s.student_name" : "grade_level, student_name");
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $allStudents = $stmt->fetchAll();
    }
} else {
    if ($gradeFilter !== 'all') {
        $sql .= " AND " . ($classroomId ? "s.grade_level" : "grade_level") . " = :grade_level";
    }

    $sql .= " ORDER BY " . ($classroomId ? "s.grade_level, s.student_name" : "grade_level, student_name");

    $stmt = $pdo->prepare($sql);

    if ($gradeFilter !== 'all') {
        if ($classroomId) {
            $stmt->execute(['classroom_id' => $classroomId, 'grade_level' => $gradeFilter]);
        } else {
            $stmt->execute(['grade_level' => $gradeFilter]);
        }
    } else {
        if ($classroomId) {
            $stmt->execute(['classroom_id' => $classroomId]);
        } else {
            $stmt->execute();
        }
    }

    $allStudents = $stmt->fetchAll();
}

// 既存の本日の記録があるかチェック
$stmt = $pdo->prepare("
    SELECT dr.id, dr.common_activity, dr.record_date
    FROM daily_records dr
    WHERE dr.record_date = ? AND dr.staff_id = ?
");
$stmt->execute([$today, $currentUser['id']]);
$existingRecord = $stmt->fetch();

// 既存の記録がある場合、参加者を取得
$existingParticipants = [];
if ($existingRecord) {
    $stmt = $pdo->prepare("
        SELECT sr.*, s.student_name
        FROM student_records sr
        JOIN students s ON sr.student_id = s.id
        WHERE sr.daily_record_id = ?
    ");
    $stmt->execute([$existingRecord['id']]);
    $existingParticipants = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
}

// 支援案検索パラメータ
$searchTag = $_GET['plan_tag'] ?? '';
$searchDayOfWeek = $_GET['plan_day'] ?? '';

// 今日の曜日を取得
$todayDayOfWeek = date('w', strtotime($today));
$dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$todayDayName = $dayNames[$todayDayOfWeek];

// 支援案を取得（検索条件付き）
$planWhere = [];
$planParams = [];

if ($classroomId) {
    $planWhere[] = "sp.classroom_id = ?";
    $planParams[] = $classroomId;
}

// 日付またはタグ・曜日で絞り込み
if (empty($searchTag) && empty($searchDayOfWeek)) {
    // 検索条件がない場合、その日の支援案のみ
    $planWhere[] = "sp.activity_date = ?";
    $planParams[] = $today;
} else {
    // 検索条件がある場合
    if (!empty($searchTag)) {
        $planWhere[] = "FIND_IN_SET(?, sp.tags) > 0";
        $planParams[] = $searchTag;
    }
    if (!empty($searchDayOfWeek)) {
        $planWhere[] = "FIND_IN_SET(?, sp.day_of_week) > 0";
        $planParams[] = $searchDayOfWeek;
    }
}

$planWhereClause = !empty($planWhere) ? 'WHERE ' . implode(' AND ', $planWhere) : '';

$sql = "
    SELECT sp.*, u.full_name as staff_name
    FROM support_plans sp
    INNER JOIN users u ON sp.staff_id = u.id
    {$planWhereClause}
    ORDER BY sp.activity_date DESC, sp.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($planParams);
$supportPlans = $stmt->fetchAll();

// ページ開始
$currentPage = 'renrakucho';
renderPageStart('staff', $currentPage, '連絡帳入力');
?>

<style>
.student-selection {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.student-checkbox {
    display: flex;
    align-items: center;
    padding: var(--spacing-md) 15px;
    background: var(--md-gray-6);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background 0.3s;
}

.student-checkbox:hover {
    background: var(--md-gray-5);
}

.student-checkbox input[type="checkbox"] {
    margin-right: 8px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.student-grade-badge {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 0;
    margin-left: 5px;
}

.badge-preschool { background: rgba(255, 149, 0, 0.15); color: var(--md-orange); }
.badge-elementary { background: rgba(255, 59, 48, 0.15); color: var(--md-red); }
.badge-junior-high { background: rgba(0, 122, 255, 0.15); color: var(--md-blue); }
.badge-high-school { background: rgba(175, 82, 222, 0.15); color: var(--md-purple); }

.grade-filter {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
}

.grade-btn {
    padding: var(--spacing-sm) 16px;
    border: 2px solid var(--md-blue);
    background: var(--md-bg-primary);
    color: var(--md-blue);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
    text-decoration: none;
    font-size: var(--text-subhead);
}

.grade-btn:hover { background: var(--md-bg-secondary); }
.grade-btn.active { background: var(--md-blue); color: white; }

.grade-btn-scheduled {
    border-color: var(--md-green);
    color: var(--md-green);
}
.grade-btn-scheduled:hover { background: rgba(52, 199, 89, 0.15); }
.grade-btn-scheduled.active { background: var(--md-green); color: white; }

.quick-links {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
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
}
.quick-link:hover { background: var(--md-gray-5); }

.plan-search-box {
    background: var(--md-gray-6);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--md-green);
}

.plan-search-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.plan-info-box {
    background: var(--md-bg-secondary);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    border-left: 4px solid var(--md-orange);
    font-size: var(--text-subhead);
    margin-bottom: var(--spacing-md);
}

.plan-details-box {
    display: none;
    background: var(--md-gray-6);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--md-blue);
}

@media (max-width: 768px) {
    .plan-search-form {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">連絡帳入力</h1>
        <p class="page-subtitle">記録日: <?= date('Y年n月j日', strtotime($today)) . '（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($today))] . '）' ?></p>
    </div>
</div>

<!-- クイックリンク -->
<div class="quick-links">
    <a href="kakehashi_staff.php" class="quick-link"><span class="material-symbols-outlined">handshake</span> スタッフかけはし</a>
    <a href="kakehashi_guardian_view.php" class="quick-link"><span class="material-symbols-outlined">assignment</span> 保護者かけはし確認</a>
    <a href="renrakucho_activities.php" class="quick-link"><span class="material-symbols-outlined">edit_note</span> 活動一覧</a>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if ($isTodayHoliday): ?>
    <div class="alert alert-danger">本日は休日です。</div>
<?php endif; ?>

<?php if ($existingRecord): ?>
    <div class="alert alert-success">本日の記録が既に存在します。修正する場合は下記のフォームから編集してください。</div>
<?php endif; ?>

<!-- 学年フィルター -->
<div class="grade-filter">
    <label style="font-weight: 600; color: var(--text-primary);">フィルター:</label>
    <a href="?date=<?= urlencode($today) ?>&grade=all" class="grade-btn <?= $gradeFilter === 'all' ? 'active' : '' ?>">すべて</a>
    <a href="?date=<?= urlencode($today) ?>&grade=scheduled" class="grade-btn grade-btn-scheduled <?= $gradeFilter === 'scheduled' ? 'active' : '' ?>">
        本日の予定参加者<?php if (!$isTodayHoliday && !empty($scheduledStudentIds)): ?> (<?= count($scheduledStudentIds) ?>名)<?php endif; ?>
    </a>
    <?php if (in_array('preschool', $targetGrades)): ?>
    <a href="?date=<?= urlencode($today) ?>&grade=preschool" class="grade-btn <?= $gradeFilter === 'preschool' ? 'active' : '' ?>">未就学児</a>
    <?php endif; ?>
    <?php if (in_array('elementary', $targetGrades)): ?>
    <a href="?date=<?= urlencode($today) ?>&grade=elementary" class="grade-btn <?= $gradeFilter === 'elementary' ? 'active' : '' ?>">小学生</a>
    <?php endif; ?>
    <?php if (in_array('junior_high', $targetGrades)): ?>
    <a href="?date=<?= urlencode($today) ?>&grade=junior_high" class="grade-btn <?= $gradeFilter === 'junior_high' ? 'active' : '' ?>">中学生</a>
    <?php endif; ?>
    <?php if (in_array('high_school', $targetGrades)): ?>
    <a href="?date=<?= urlencode($today) ?>&grade=high_school" class="grade-btn <?= $gradeFilter === 'high_school' ? 'active' : '' ?>">高校生</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--md-blue);">新しい活動の追加</h2>

        <!-- 支援案検索 -->
        <div class="plan-search-box">
            <h3 style="margin-bottom: var(--spacing-md); color: var(--text-primary); font-size: var(--text-callout);"><span class="material-symbols-outlined">search</span> 支援案を検索</h3>
            <form method="GET" class="plan-search-form">
                <input type="hidden" name="date" value="<?= htmlspecialchars($today) ?>">
                <input type="hidden" name="grade" value="<?= htmlspecialchars($gradeFilter) ?>">

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: var(--text-footnote);">タグ</label>
                    <select name="plan_tag" class="form-control">
                        <option value="">すべて</option>
                        <?php
                        $tags = ['動画', '食', '学習', 'イベント', 'その他'];
                        foreach ($tags as $tag):
                        ?>
                            <option value="<?= htmlspecialchars($tag) ?>" <?= $searchTag === $tag ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: var(--text-footnote);">曜日</label>
                    <select name="plan_day" class="form-control">
                        <option value="">すべて</option>
                        <?php
                        $days = ['monday' => '月曜日', 'tuesday' => '火曜日', 'wednesday' => '水曜日', 'thursday' => '木曜日', 'friday' => '金曜日', 'saturday' => '土曜日', 'sunday' => '日曜日'];
                        foreach ($days as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= $searchDayOfWeek === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">検索</button>
                <?php if (!empty($searchTag) || !empty($searchDayOfWeek)): ?>
                    <a href="?date=<?= htmlspecialchars($today) ?>&grade=<?= htmlspecialchars($gradeFilter) ?>" class="btn btn-secondary">クリア</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- 支援案選択 -->
        <div class="form-group">
            <label class="form-label">
                支援案を選択
                <span style="font-size: var(--text-caption-1); color: var(--text-secondary); font-weight: normal;">(任意)</span>
                <a href="support_plan_form.php" style="font-size: var(--text-caption-1); margin-left: 10px;"><span class="material-symbols-outlined">edit_note</span> この日の支援案を作成</a>
            </label>
            <?php if (empty($supportPlans)): ?>
                <div class="plan-info-box">
                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">lightbulb</span> この日（<?= date('Y年n月j日', strtotime($today)) ?>）の支援案がまだ作成されていません。
                    <a href="support_plan_form.php" style="color: var(--md-blue); text-decoration: underline;">支援案を作成</a>してから活動を追加すると、より効率的に記録できます。
                </div>
            <?php endif; ?>
            <select id="supportPlan" class="form-control">
                <option value="">支援案を選択しない（手動入力）</option>
                <?php foreach ($supportPlans as $plan): ?>
                    <option value="<?= $plan['id'] ?>"
                            data-activity-name="<?= htmlspecialchars($plan['activity_name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-purpose="<?= htmlspecialchars($plan['activity_purpose'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-content="<?= htmlspecialchars($plan['activity_content'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-domains="<?= htmlspecialchars($plan['five_domains_consideration'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-other="<?= htmlspecialchars($plan['other_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($plan['activity_name']) ?>
                        <span style="color: var(--text-secondary);">(作成者: <?= htmlspecialchars($plan['staff_name']) ?>)</span>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 支援案の内容表示 -->
        <div id="supportPlanDetails" class="plan-details-box">
            <h3 style="color: var(--md-blue); font-size: var(--text-callout); margin-bottom: var(--spacing-md);">選択した支援案の内容</h3>
            <div id="planPurpose"></div>
            <div id="planContent"></div>
            <div id="planDomains"></div>
            <div id="planOther"></div>
        </div>

        <div class="form-group">
            <label class="form-label">活動名 <span style="color: var(--md-red);">*</span></label>
            <input type="text" id="activityName" class="form-control" placeholder="例: 午前の活動、外出活動、制作活動など" required>
        </div>

        <h3 style="margin-top: var(--spacing-lg); margin-bottom: var(--spacing-md); font-size: var(--text-headline); color: var(--text-primary);">参加者選択</h3>
        <div class="student-selection">
            <?php
            $gradeLabelMap = [
                'preschool' => ['未', 'badge-preschool'],
                'elementary' => ['小', 'badge-elementary'],
                'junior_high' => ['中', 'badge-junior-high'],
                'high_school' => ['高', 'badge-high-school']
            ];

            foreach ($allStudents as $student):
                // 生年月日から学年を再計算
                $calculatedGrade = $student['birth_date']
                    ? calculateGradeLevel($student['birth_date'], null, $student['grade_adjustment'] ?? 0)
                    : $student['grade_level'];
                // カテゴリを取得
                $gradeCategory = getGradeCategory($calculatedGrade);
                $gradeInfo = $gradeLabelMap[$gradeCategory] ?? ['?', ''];
            ?>
                <label class="student-checkbox">
                    <input type="checkbox" name="students[]" value="<?= $student['id'] ?>"
                           data-name="<?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                           <?= isset($existingParticipants[$student['id']]) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="student-grade-badge <?= $gradeInfo[1] ?>"><?= $gradeInfo[0] ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-success" id="addParticipantsBtn">参加者を追加</button>
    </div>
</div>

<div id="formArea" style="display: none;">
    <!-- フォームはJavaScriptで動的に生成 -->
</div>

<?php
$existingRecordJson = json_encode($existingRecord);
$existingParticipantsJson = json_encode($existingParticipants);

$inlineJs = <<<JS
const addParticipantsBtn = document.getElementById('addParticipantsBtn');
const formArea = document.getElementById('formArea');
const supportPlanSelect = document.getElementById('supportPlan');
const supportPlanDetails = document.getElementById('supportPlanDetails');
const activityNameInput = document.getElementById('activityName');
const existingRecord = {$existingRecordJson};
const existingParticipants = {$existingParticipantsJson};

// 支援案選択時の処理
supportPlanSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];

    if (this.value === '') {
        supportPlanDetails.style.display = 'none';
        activityNameInput.value = '';
        activityNameInput.readOnly = false;
        activityNameInput.style.backgroundColor = '';
        return;
    }

    // 支援案の内容を表示
    const activityName = selectedOption.dataset.activityName || '';
    const purpose = selectedOption.dataset.purpose || '';
    const content = selectedOption.dataset.content || '';
    const domains = selectedOption.dataset.domains || '';
    const other = selectedOption.dataset.other || '';

    // 活動名を自動入力
    activityNameInput.value = activityName;
    activityNameInput.readOnly = true;
    activityNameInput.style.backgroundColor = 'var(--md-gray-6)';

    // 支援案の内容を表示
    document.getElementById('planPurpose').innerHTML = purpose ? '<div style="margin-bottom: 8px;"><strong style="color: var(--md-blue);">活動の目的:</strong><br>' + escapeHtml(purpose) + '</div>' : '';
    document.getElementById('planContent').innerHTML = content ? '<div style="margin-bottom: 8px;"><strong style="color: var(--md-blue);">活動の内容:</strong><br>' + escapeHtml(content) + '</div>' : '';
    document.getElementById('planDomains').innerHTML = domains ? '<div style="margin-bottom: 8px;"><strong style="color: var(--md-blue);">五領域への配慮:</strong><br>' + escapeHtml(domains) + '</div>' : '';
    document.getElementById('planOther').innerHTML = other ? '<div><strong style="color: var(--md-blue);">その他:</strong><br>' + escapeHtml(other) + '</div>' : '';

    supportPlanDetails.style.display = 'block';
});

// HTMLエスケープ関数
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\\n/g, '<br>');
}

addParticipantsBtn.addEventListener('click', function() {
    const activityName = activityNameInput.value.trim();
    const checkedBoxes = document.querySelectorAll('input[name="students[]"]:checked');

    if (activityName === '') {
        alert('活動名を入力してください');
        return;
    }

    if (checkedBoxes.length === 0) {
        alert('参加者を選択してください');
        return;
    }

    // 次のページ（フォーム入力）へ遷移
    const studentIds = Array.from(checkedBoxes).map(cb => cb.value);

    // フォーム入力ページへデータを渡して遷移
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'renrakucho_form.php';

    // 支援案IDを追加（選択されている場合）
    const supportPlanId = supportPlanSelect.value;
    if (supportPlanId) {
        const planInput = document.createElement('input');
        planInput.type = 'hidden';
        planInput.name = 'support_plan_id';
        planInput.value = supportPlanId;
        form.appendChild(planInput);
    }

    // 活動名を追加
    const activityInput = document.createElement('input');
    activityInput.type = 'hidden';
    activityInput.name = 'activity_name';
    activityInput.value = activityName;
    form.appendChild(activityInput);

    // 日付を追加
    const dateInput = document.createElement('input');
    dateInput.type = 'hidden';
    dateInput.name = 'record_date';
    dateInput.value = '{$today}';
    form.appendChild(dateInput);

    // 参加者IDを追加
    studentIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'student_ids[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>