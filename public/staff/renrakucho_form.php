<?php
/**
 * 連絡帳入力フォームページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// POSTデータまたはGETパラメータから取得
$studentIds = $_POST['student_ids'] ?? [];
$activityName = $_POST['activity_name'] ?? '';
$recordDate = $_POST['record_date'] ?? date('Y-m-d');
$activityId = $_GET['activity_id'] ?? null;
$supportPlanId = $_POST['support_plan_id'] ?? null;

// 支援案情報を取得（新規作成時に支援案が選択されている場合、自分の教室のみ）
$supportPlan = null;
if ($supportPlanId && !$activityId) {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT * FROM support_plans WHERE id = ? AND classroom_id = ?
        ");
        $stmt->execute([$supportPlanId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM support_plans WHERE id = ?
        ");
        $stmt->execute([$supportPlanId]);
    }
    $supportPlan = $stmt->fetch();
}

// 既存の活動を編集する場合（同じ教室のスタッフが作成した活動も編集可能）
if ($activityId) {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id,
                   u.full_name as staff_name
            FROM daily_records dr
            INNER JOIN users u ON dr.staff_id = u.id
            WHERE dr.id = ? AND u.classroom_id = ?
        ");
        $stmt->execute([$activityId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id,
                   u.full_name as staff_name
            FROM daily_records dr
            INNER JOIN users u ON dr.staff_id = u.id
            WHERE dr.id = ?
        ");
        $stmt->execute([$activityId]);
    }
    $existingRecord = $stmt->fetch();

    if (!$existingRecord) {
        $_SESSION['error'] = 'この活動にアクセスする権限がありません';
        header('Location: renrakucho_activities.php');
        exit;
    }

    $activityName = $existingRecord['activity_name'];

    // 既存の参加者を取得
    $stmt = $pdo->prepare("
        SELECT DISTINCT student_id FROM student_records WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);
    $studentIds = array_column($stmt->fetchAll(), 'student_id');
}

if (empty($studentIds)) {
    header('Location: renrakucho.php');
    exit;
}

// 参加者情報を取得（自分の教室の生徒のみ、セキュリティ対策）
$placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.id IN ($placeholders) AND s.is_active = 1 AND u.classroom_id = ?
        ORDER BY s.student_name
    ");
    $params = array_merge($studentIds, [$classroomId]);
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare("
        SELECT id, student_name
        FROM students
        WHERE id IN ($placeholders) AND is_active = 1
        ORDER BY student_name
    ");
    $stmt->execute($studentIds);
}
$students = $stmt->fetchAll();

// 既存の学生記録を取得
$existingStudentRecords = [];

if ($activityId) {
    $stmt = $pdo->prepare("
        SELECT student_id, daily_note, domain1, domain1_content, domain2, domain2_content
        FROM student_records
        WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);
    $records = $stmt->fetchAll();

    foreach ($records as $record) {
        $existingStudentRecords[$record['student_id']] = $record;
    }
}

// 追加可能な全生徒を取得（自分の教室の生徒から、すでに参加している生徒を除く）
$availableStudents = [];
if ($classroomId) {
    $currentStudentIds = array_column($students, 'id');
    if (!empty($currentStudentIds)) {
        $placeholders = str_repeat('?,', count($currentStudentIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_name, s.grade_level
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE s.is_active = 1 AND u.classroom_id = ? AND s.id NOT IN ($placeholders)
            ORDER BY s.grade_level, s.student_name
        ");
        $params = array_merge([$classroomId], $currentStudentIds);
        $stmt->execute($params);
    } else {
        // 参加生徒がいない場合は全員表示
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_name, s.grade_level
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            ORDER BY s.grade_level, s.student_name
        ");
        $stmt->execute([$classroomId]);
    }
    $availableStudents = $stmt->fetchAll();
} else {
    $currentStudentIds = array_column($students, 'id');
    if (!empty($currentStudentIds)) {
        $placeholders = str_repeat('?,', count($currentStudentIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT id, student_name, grade_level
            FROM students
            WHERE is_active = 1 AND id NOT IN ($placeholders)
            ORDER BY grade_level, student_name
        ");
        $stmt->execute($currentStudentIds);
    } else {
        $stmt = $pdo->query("SELECT id, student_name, grade_level FROM students WHERE is_active = 1 ORDER BY grade_level, student_name");
    }
    $availableStudents = $stmt->fetchAll();
}

// 本日参加予定の生徒を取得（曜日ベース）
$todayDayOfWeek = date('w', strtotime($recordDate)); // 0=日曜, 1=月曜, ...
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

$scheduledStudentIds = [];
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ? AND s.$todayColumn = 1
    ");
    $stmt->execute([$classroomId]);
    $scheduledStudentIds = array_column($stmt->fetchAll(), 'id');
} else {
    $stmt = $pdo->prepare("
        SELECT id
        FROM students
        WHERE is_active = 1 AND $todayColumn = 1
    ");
    $stmt->execute();
    $scheduledStudentIds = array_column($stmt->fetchAll(), 'id');
}

// 5領域の定義
$domains = [
    'health_life' => '健康・生活',
    'motor_sensory' => '運動・感覚',
    'cognitive_behavior' => '認知・行動',
    'language_communication' => '言語・コミュニケーション',
    'social_relations' => '人間関係・社会性'
];

// ページ開始
$currentPage = 'renrakucho_form';
$pageTitle = '連絡帳入力フォーム';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .common-activity-section {
            background: var(--md-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--md-orange);
        }

        .common-activity-section h2 {
            color: var(--cds-orange-50);
            font-size: 18px;
            margin-bottom: 15px;
        }

        .common-activity-section textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--md-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .student-card {
            background: var(--md-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .student-card h3 {
            color: var(--text-primary);
            font-size: 20px;
            margin-bottom: var(--spacing-lg);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-purple);
        }

        .domain-selection {
            margin-bottom: var(--spacing-lg);
        }

        .domain-selection h4 {
            color: var(--cds-text-secondary);
            font-size: var(--text-callout);
            margin-bottom: var(--spacing-md);
        }

        .domain-group {
            margin-bottom: 25px;
        }

        .domain-select {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--md-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            margin-bottom: var(--spacing-md);
            background: var(--md-bg-primary);
        }

        .domain-textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--md-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            background: var(--md-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: var(--spacing-md) 30px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: 600;
            cursor: pointer;
            transition: transform var(--duration-fast) var(--ease-out);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--md-green);
            color: white;
        }

        .btn-secondary {
            background: var(--md-blue);
            color: white;
        }

        .info-text {
            color: var(--text-secondary);
            font-size: var(--text-subhead);
            margin-bottom: var(--spacing-md);
        }

        .btn-add-student {
            background: var(--primary-purple);
            color: white;
            padding: var(--spacing-md) 30px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
            margin: var(--spacing-lg) 0;
        }

        .btn-add-student:hover {
            background: var(--primary-purple);
            transform: translateY(-2px);
        }

        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: var(--spacing-lg);
            color: var(--text-primary);
            border-bottom: 2px solid var(--primary-purple);
            padding-bottom: 10px;
        }

        /* 検索フィルタ */
        .search-filters {
            background: var(--md-gray-6);
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
        }

        .filter-group {
            margin-bottom: var(--spacing-md);
        }

        .filter-group:last-child {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-size: var(--text-subhead);
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--md-gray-5);
            border-radius: 6px;
            font-size: var(--text-subhead);
            transition: border-color 0.3s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .btn-scheduled {
            width: 100%;
            padding: var(--spacing-md);
            background: var(--md-green);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: var(--text-subhead);
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-scheduled:hover {
            background: var(--md-green);
        }

        .student-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: var(--spacing-lg);
            max-height: 400px;
            overflow-y: auto;
        }

        .student-item {
            padding: 15px;
            border: 2px solid var(--md-gray-5);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-item:hover {
            border-color: var(--primary-purple);
            background: var(--md-gray-6);
        }

        .student-item.selected {
            border-color: var(--primary-purple);
            background: rgba(25, 118, 210, 0.15);
        }

        .student-item-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .student-item-name {
            font-size: var(--text-callout);
            font-weight: 600;
            color: var(--text-primary);
        }

        .student-item-grade {
            font-size: var(--text-caption-1);
            background: var(--primary-purple);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .student-item-badge {
            font-size: 11px;
            background: var(--md-green);
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: 600;
        }

        .student-item-check {
            width: 24px;
            height: 24px;
            border: 2px solid var(--md-gray-5);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .student-item.selected .student-item-check {
            background: var(--primary-purple);
            border-color: var(--primary-purple);
        }

        .student-item.selected .student-item-check::after {
            content: '✓';
            color: white;
            font-weight: bold;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: var(--spacing-lg);
        }

        .btn-cancel {
            background: var(--md-gray);
            color: white;
        }

        .btn-cancel:hover {
            background: var(--md-gray);
        }

        .add-student-section {
            background: var(--md-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }

        .student-card.new {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .remove-student-btn {
            background: var(--md-red);
            color: white;
            border: none;
            padding: var(--spacing-sm) 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            margin-bottom: 15px;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .remove-student-btn:hover {
            background: var(--md-red);
        }

        .save-student-btn {
            background: var(--md-green);
            color: white;
            border: none;
            padding: var(--spacing-md) 24px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 15px;
            transition: all var(--duration-normal) var(--ease-out);
            display: none;
        }

        .save-student-btn:hover {
            background: var(--md-green);
        }

        .save-student-btn.visible {
            display: inline-block;
        }

        .save-student-btn.saved {
            background: var(--md-gray);
            cursor: default;
        }

        .save-student-btn.saved:hover {
            background: var(--md-gray);
            transform: none;
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">連絡帳入力フォーム</h1>
        <p class="page-subtitle">活動名: <?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="page-header-actions">
        <a href="renrakucho_activities.php" class="btn btn-secondary">← 活動一覧へ</a>
    </div>
</div>

        <div style="background: var(--md-bg-primary); padding: 15px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);">
            <div style="margin-bottom: var(--spacing-md);">
                <strong style="color: var(--primary-purple); font-size: 18px;">活動名:</strong>
                <span style="font-size: 18px; margin-left: 10px;"><?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if (isset($existingRecord) && $existingRecord): ?>
                <div style="font-size: var(--text-subhead); color: var(--text-secondary);">
                    作成者: <?php echo htmlspecialchars($existingRecord['staff_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($existingRecord['staff_id'] == $currentUser['id']): ?>
                        <span style="color: var(--primary-purple); font-weight: bold;">(自分)</span>
                    <?php else: ?>
                        <span style="color: var(--cds-orange-50); font-weight: bold;">(他のスタッフ)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($supportPlan): ?>
            <!-- 支援案情報の表示 -->
            <div style="background: var(--md-bg-primary); padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); border-left: 4px solid var(--primary-purple);">
                <h2 style="color: var(--primary-purple); font-size: 18px; margin-bottom: 15px;"><span class="material-symbols-outlined">edit_note</span> 選択された支援案</h2>
                <div style="font-size: var(--text-subhead); line-height: 1.8;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: var(--primary-purple);">活動名:</strong>
                        <?php echo htmlspecialchars($supportPlan['activity_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if (!empty($supportPlan['activity_purpose'])): ?>
                        <div style="margin-bottom: 12px;">
                            <strong style="color: var(--primary-purple);">活動の目的:</strong><br>
                            <?php echo nl2br(htmlspecialchars($supportPlan['activity_purpose'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($supportPlan['five_domains_consideration'])): ?>
                        <div style="margin-bottom: 12px;">
                            <strong style="color: var(--primary-purple);">五領域への配慮:</strong><br>
                            <?php echo nl2br(htmlspecialchars($supportPlan['five_domains_consideration'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($supportPlan['other_notes'])): ?>
                        <div style="margin-bottom: 12px;">
                            <strong style="color: var(--primary-purple);">その他:</strong><br>
                            <?php echo nl2br(htmlspecialchars($supportPlan['other_notes'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="renrakucho_save.php" id="renrakuchoForm">
            <input type="hidden" name="activity_name" value="<?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="record_date" value="<?php echo htmlspecialchars($recordDate, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($activityId): ?>
                <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">
            <?php endif; ?>
            <?php if ($supportPlanId): ?>
                <input type="hidden" name="support_plan_id" value="<?php echo $supportPlanId; ?>">
            <?php endif; ?>

            <!-- 共通活動入力欄 -->
            <div class="common-activity-section">
                <h2>本日の活動（共通）</h2>
                <p class="info-text">全ての参加者に反映される共通の活動内容を記入してください</p>
                <?php if ($supportPlan): ?>
                    <p class="info-text" style="background: rgba(25, 118, 210, 0.15); padding: var(--spacing-md); border-radius: var(--radius-sm); border-left: 4px solid var(--primary-purple); margin-bottom: var(--spacing-md);">
                        <span class="material-symbols-outlined">tips_and_updates</span> 支援案「<?php echo htmlspecialchars($supportPlan['activity_name'], ENT_QUOTES, 'UTF-8'); ?>」の活動内容が反映されています。必要に応じて編集してください。
                    </p>
                <?php endif; ?>
                <textarea
                    name="common_activity"
                    id="commonActivity"
                    placeholder="例: 公園で散歩、音楽活動、制作活動など"
                ><?php
                    // 既存の活動を編集する場合はその内容、新規作成で支援案がある場合は支援案の内容、それ以外は空
                    if (isset($existingRecord['common_activity'])) {
                        echo htmlspecialchars($existingRecord['common_activity'], ENT_QUOTES, 'UTF-8');
                    } elseif ($supportPlan && !empty($supportPlan['activity_content'])) {
                        echo htmlspecialchars($supportPlan['activity_content'], ENT_QUOTES, 'UTF-8');
                    }
                ?></textarea>
            </div>

            <!-- 個別の生徒記録 -->
            <?php foreach ($students as $student): ?>
                <?php
                $studentId = $student['id'];
                $existingData = $existingStudentRecords[$studentId] ?? null;
                ?>
                <div class="student-card" data-student-id="<?php echo $studentId; ?>">
                    <?php if ($activityId): ?>
                    <button type="button" class="save-student-btn" data-student-id="<?php echo $studentId; ?>" onclick="saveStudent(<?php echo $studentId; ?>)">この生徒の修正を保存</button>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?></h3>

                    <input type="hidden" name="students[<?php echo $studentId; ?>][id]" value="<?php echo $studentId; ?>">

                    <!-- 本日の様子 -->
                    <div class="domain-group" style="background: rgba(25, 118, 210, 0.15); padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid var(--cds-blue-60);">
                        <h4 style="color: var(--cds-blue-60);">本日の様子</h4>
                        <textarea
                            name="students[<?php echo $studentId; ?>][daily_note]"
                            class="domain-textarea"
                            placeholder="本日の全体的な様子を自由に記入してください"
                            style="background: var(--md-bg-primary);"
                        ><?php echo htmlspecialchars($existingData['daily_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 領域1 -->
                    <div class="domain-group">
                        <h4>気になったこと 1つ目</h4>
                        <select
                            name="students[<?php echo $studentId; ?>][domain1]"
                            class="domain-select"
                            required
                        >
                            <option value="">領域を選択してください</option>
                            <?php foreach ($domains as $key => $label): ?>
                                <option
                                    value="<?php echo $key; ?>"
                                    <?php echo ($existingData && $existingData['domain1'] === $key) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <textarea
                            name="students[<?php echo $studentId; ?>][domain1_content]"
                            class="domain-textarea"
                            placeholder="気になったことを記入してください"
                            required
                        ><?php echo htmlspecialchars($existingData['domain1_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 領域2 -->
                    <div class="domain-group">
                        <h4>気になったこと 2つ目</h4>
                        <select
                            name="students[<?php echo $studentId; ?>][domain2]"
                            class="domain-select"
                            required
                        >
                            <option value="">領域を選択してください</option>
                            <?php foreach ($domains as $key => $label): ?>
                                <option
                                    value="<?php echo $key; ?>"
                                    <?php echo ($existingData && $existingData['domain2'] === $key) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <textarea
                            name="students[<?php echo $studentId; ?>][domain2_content]"
                            class="domain-textarea"
                            placeholder="気になったことを記入してください"
                            required
                        ><?php echo htmlspecialchars($existingData['domain2_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 参加生徒を追加セクション -->
            <?php if (!empty($availableStudents)): ?>
            <div class="add-student-section">
                <button type="button" class="btn-add-student" onclick="openAddStudentModal()">
                    <span class="material-symbols-outlined">add</span> 参加生徒を追加
                </button>
                <p class="info-text">追加可能な生徒: <?php echo count($availableStudents); ?>名</p>
            </div>
            <?php endif; ?>

            <!-- 送信ボタン -->
            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <?php echo $activityId ? '全体をこの内容で保存' : '確定して保存'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- 生徒追加モーダル -->
    <div class="modal" id="addStudentModal">
        <div class="modal-content">
            <h3 class="modal-header">参加生徒を追加</h3>

            <!-- 検索フィルタ -->
            <div class="search-filters">
                <div class="filter-group">
                    <label>学年で絞り込み:</label>
                    <select id="gradeFilter" onchange="filterStudents()">
                        <option value="">すべての学年</option>
                        <option value="小1">小1</option>
                        <option value="小2">小2</option>
                        <option value="小3">小3</option>
                        <option value="小4">小4</option>
                        <option value="小5">小5</option>
                        <option value="小6">小6</option>
                        <option value="中1">中1</option>
                        <option value="中2">中2</option>
                        <option value="中3">中3</option>
                        <option value="高1">高1</option>
                        <option value="高2">高2</option>
                        <option value="高3">高3</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>氏名で検索:</label>
                    <input type="text" id="nameFilter" placeholder="氏名の一部を入力" oninput="filterStudents()">
                </div>
                <div class="filter-group">
                    <button type="button" class="btn btn-scheduled" onclick="showScheduledOnly()"><span class="material-symbols-outlined">event</span> 本日参加予定の生徒から選択</button>
                </div>
            </div>

            <div class="student-list" id="availableStudentList">
                <?php foreach ($availableStudents as $student): ?>
                <div class="student-item"
                     data-student-id="<?php echo $student['id']; ?>"
                     data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-student-grade="<?php echo htmlspecialchars($student['grade_level'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     data-is-scheduled="<?php echo in_array($student['id'], $scheduledStudentIds) ? '1' : '0'; ?>"
                     onclick="toggleStudentSelection(this)">
                    <div class="student-item-info">
                        <div class="student-item-name"><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($student['grade_level'])): ?>
                            <div class="student-item-grade"><?php echo htmlspecialchars($student['grade_level'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <?php if (in_array($student['id'], $scheduledStudentIds)): ?>
                            <div class="student-item-badge">本日予定</div>
                        <?php endif; ?>
                    </div>
                    <div class="student-item-check"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeAddStudentModal()">キャンセル</button>
                <button type="button" class="btn btn-secondary" onclick="addSelectedStudents()">選択した生徒を追加</button>
            </div>
        </div>
    </div>

    <script>
        // 5領域の定義（JavaScriptでも使用）
        const domains = <?php echo json_encode($domains); ?>;

        // 選択された生徒を管理
        let selectedStudentsForAdd = new Set();

        // モーダルを開く
        function openAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('active');
            selectedStudentsForAdd.clear();
            // 選択状態をリセット
            document.querySelectorAll('.student-item').forEach(item => {
                item.classList.remove('selected');
            });
            // フィルタをリセット
            document.getElementById('gradeFilter').value = '';
            document.getElementById('nameFilter').value = '';
            filterStudents();
        }

        // モーダルを閉じる
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('active');
            selectedStudentsForAdd.clear();
        }

        // 生徒リストをフィルタリング
        function filterStudents() {
            const gradeFilter = document.getElementById('gradeFilter').value;
            const nameFilter = document.getElementById('nameFilter').value.toLowerCase();
            const studentItems = document.querySelectorAll('#availableStudentList .student-item');

            let visibleCount = 0;
            studentItems.forEach(item => {
                const studentName = item.dataset.studentName.toLowerCase();
                const studentGrade = item.dataset.studentGrade || '';

                const gradeMatch = !gradeFilter || studentGrade === gradeFilter;
                const nameMatch = !nameFilter || studentName.includes(nameFilter);

                if (gradeMatch && nameMatch) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // 検索結果0件の場合にメッセージを表示（オプション）
            // console.log(`検索結果: ${visibleCount}名`);
        }

        // 本日参加予定の生徒のみ表示
        function showScheduledOnly() {
            const studentItems = document.querySelectorAll('#availableStudentList .student-item');

            let scheduledCount = 0;
            studentItems.forEach(item => {
                const isScheduled = item.dataset.isScheduled === '1';

                if (isScheduled) {
                    item.style.display = '';
                    scheduledCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // フィルタをリセット
            document.getElementById('gradeFilter').value = '';
            document.getElementById('nameFilter').value = '';

            if (scheduledCount === 0) {
                alert('本日参加予定の生徒はいません');
            }
        }

        // 生徒選択をトグル
        function toggleStudentSelection(element) {
            const studentId = element.dataset.studentId;

            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedStudentsForAdd.delete(studentId);
            } else {
                element.classList.add('selected');
                selectedStudentsForAdd.add(studentId);
            }
        }

        // 選択した生徒をフォームに追加
        function addSelectedStudents() {
            if (selectedStudentsForAdd.size === 0) {
                alert('追加する生徒を選択してください');
                return;
            }

            const form = document.getElementById('renrakuchoForm');
            const submitSection = document.querySelector('.form-actions');

            selectedStudentsForAdd.forEach(studentId => {
                const studentItem = document.querySelector(`.student-item[data-student-id="${studentId}"]`);
                const studentName = studentItem.dataset.studentName;

                // 生徒カードを作成
                const studentCard = createStudentCard(studentId, studentName);

                // 送信ボタンの前に挿入
                submitSection.parentNode.insertBefore(studentCard, submitSection);

                // モーダルから該当の生徒を削除
                studentItem.remove();
            });

            // 追加可能な生徒数を更新
            updateAvailableStudentCount();

            closeAddStudentModal();

            // スクロールして追加された生徒が見えるようにする
            setTimeout(() => {
                const newCards = document.querySelectorAll('.student-card.new');
                if (newCards.length > 0) {
                    newCards[newCards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }

        // 生徒カードを作成
        function createStudentCard(studentId, studentName) {
            const card = document.createElement('div');
            card.className = 'student-card new';
            card.dataset.studentId = studentId;

            const domainOptions = Object.entries(domains).map(([key, label]) =>
                `<option value="${key}">${escapeHtml(label)}</option>`
            ).join('');

            card.innerHTML = `
                <button type="button" class="remove-student-btn" onclick="removeStudentCard(${studentId})"><span class="material-symbols-outlined">close</span> この生徒を削除</button>
                <h3>${escapeHtml(studentName)}</h3>

                <input type="hidden" name="students[${studentId}][id]" value="${studentId}">

                <!-- 本日の様子 -->
                <div class="domain-group" style="background: rgba(25, 118, 210, 0.15); padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid var(--cds-blue-60);">
                    <h4 style="color: var(--cds-blue-60);">本日の様子</h4>
                    <textarea
                        name="students[${studentId}][daily_note]"
                        class="domain-textarea"
                        placeholder="本日の全体的な様子を自由に記入してください"
                        style="background: var(--md-bg-primary);"
                    ></textarea>
                </div>

                <!-- 領域1 -->
                <div class="domain-group">
                    <h4>気になったこと 1つ目</h4>
                    <select name="students[${studentId}][domain1]" class="domain-select" required>
                        <option value="">領域を選択してください</option>
                        ${domainOptions}
                    </select>
                    <textarea
                        name="students[${studentId}][domain1_content]"
                        class="domain-textarea"
                        placeholder="気になったことを記入してください"
                        required
                    ></textarea>
                </div>

                <!-- 領域2 -->
                <div class="domain-group">
                    <h4>気になったこと 2つ目</h4>
                    <select name="students[${studentId}][domain2]" class="domain-select" required>
                        <option value="">領域を選択してください</option>
                        ${domainOptions}
                    </select>
                    <textarea
                        name="students[${studentId}][domain2_content]"
                        class="domain-textarea"
                        placeholder="気になったことを記入してください"
                        required
                    ></textarea>
                </div>
            `;

            // アニメーションクラスを一定時間後に削除
            setTimeout(() => {
                card.classList.remove('new');
            }, 300);

            return card;
        }

        // 生徒カードを削除
        function removeStudentCard(studentId) {
            const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
            if (!card) return;

            const studentName = card.querySelector('h3').textContent;

            if (confirm(`「${studentName}」の入力を削除しますか？`)) {
                // モーダルのリストに戻す
                const studentList = document.getElementById('availableStudentList');
                const studentItem = document.createElement('div');
                studentItem.className = 'student-item';
                studentItem.dataset.studentId = studentId;
                studentItem.dataset.studentName = studentName;
                studentItem.onclick = function() { toggleStudentSelection(this); };
                studentItem.innerHTML = `
                    <div class="student-item-name">${escapeHtml(studentName)}</div>
                    <div class="student-item-check"></div>
                `;

                studentList.appendChild(studentItem);

                // カードを削除
                card.remove();

                // 追加可能な生徒数を更新
                updateAvailableStudentCount();
            }
        }

        // 追加可能な生徒数を更新
        function updateAvailableStudentCount() {
            const addSection = document.querySelector('.add-student-section');
            if (!addSection) return;

            const count = document.querySelectorAll('#availableStudentList .student-item').length;
            const infoText = addSection.querySelector('.info-text');

            if (count === 0) {
                addSection.style.display = 'none';
            } else {
                addSection.style.display = 'block';
                infoText.textContent = `追加可能な生徒: ${count}名`;
            }
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // モーダル外クリックで閉じる
        document.getElementById('addStudentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddStudentModal();
            }
        });

        // フォーム送信前のバリデーション
        document.getElementById('renrakuchoForm').addEventListener('submit', function(e) {
            const commonActivity = document.getElementById('commonActivity').value.trim();

            if (commonActivity === '') {
                alert('本日の活動（共通）を入力してください');
                e.preventDefault();
                return false;
            }

            // 各生徒の領域が重複していないかチェック
            const studentCards = document.querySelectorAll('.student-card');
            let hasError = false;

            studentCards.forEach(card => {
                const selects = card.querySelectorAll('.domain-select');
                const domain1 = selects[0].value;
                const domain2 = selects[1].value;

                if (domain1 === domain2 && domain1 !== '') {
                    alert('同じ領域を2回選択することはできません');
                    hasError = true;
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            return true;
        });

        // 各生徒カードの入力変更を監視
        function initializeChangeDetection() {
            const studentCards = document.querySelectorAll('.student-card');

            studentCards.forEach(card => {
                const studentId = card.dataset.studentId;
                const saveBtn = card.querySelector('.save-student-btn');

                if (!saveBtn) return; // 編集モード時のみ

                // 入力フィールドを取得
                const inputs = card.querySelectorAll('textarea, select');

                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        // 変更があったらボタンを表示
                        if (!saveBtn.classList.contains('saved')) {
                            saveBtn.classList.add('visible');
                        }
                    });

                    input.addEventListener('change', function() {
                        // 変更があったらボタンを表示
                        if (!saveBtn.classList.contains('saved')) {
                            saveBtn.classList.add('visible');
                        }
                    });
                });
            });
        }

        // 個別生徒の保存処理
        function saveStudent(studentId) {
            const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
            const saveBtn = card.querySelector('.save-student-btn');

            // ボタンを無効化
            saveBtn.disabled = true;
            const originalText = saveBtn.textContent;
            saveBtn.textContent = '保存中...';

            // フォームデータを収集
            const dailyNote = card.querySelector(`textarea[name="students[${studentId}][daily_note]"]`).value;
            const domain1 = card.querySelector(`select[name="students[${studentId}][domain1]"]`).value;
            const domain1Content = card.querySelector(`textarea[name="students[${studentId}][domain1_content]"]`).value;
            const domain2 = card.querySelector(`select[name="students[${studentId}][domain2]"]`).value;
            const domain2Content = card.querySelector(`textarea[name="students[${studentId}][domain2_content]"]`).value;

            // バリデーション
            if (!domain1 || !domain1Content.trim()) {
                alert('気になったこと1つ目の領域と内容を入力してください');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
                return;
            }

            if (!domain2 || !domain2Content.trim()) {
                alert('気になったこと2つ目の領域と内容を入力してください');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
                return;
            }

            if (domain1 === domain2) {
                alert('同じ領域を2回選択することはできません');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
                return;
            }

            // Ajaxリクエスト
            const activityId = document.querySelector('input[name="activity_id"]').value;
            const formData = new FormData();
            formData.append('action', 'save_student');
            formData.append('activity_id', activityId);
            formData.append('student_id', studentId);
            formData.append('daily_note', dailyNote);
            formData.append('domain1', domain1);
            formData.append('domain1_content', domain1Content);
            formData.append('domain2', domain2);
            formData.append('domain2_content', domain2Content);

            fetch('renrakucho_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 成功時
                    saveBtn.textContent = '修正が完了しました';
                    saveBtn.classList.add('saved');
                    saveBtn.disabled = true;

                    // 3秒後にボタンを元に戻す（再編集可能にする）
                    setTimeout(() => {
                        saveBtn.classList.remove('saved', 'visible');
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'この生徒の修正を保存';
                    }, 3000);
                } else {
                    alert('保存に失敗しました: ' + (data.error || '不明なエラー'));
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('保存中にエラーが発生しました');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            });
        }

        // ページ読み込み時に変更検知を初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeChangeDetection();
        });
    </script>

<?php renderPageEnd(); ?>