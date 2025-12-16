<?php
/**
 * 騾｣邨｡蟶ｳ蜈･蜉帙ヵ繧ｩ繝ｼ繝繝壹・繧ｸ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 繧ｹ繧ｿ繝・ヵ縺ｾ縺溘・邂｡逅・・・縺ｿ繧｢繧ｯ繧ｻ繧ｹ蜿ｯ閭ｽ
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 繧ｹ繧ｿ繝・ヵ縺ｮ謨吝ｮ､ID繧貞叙蠕・$classroomId = $_SESSION['classroom_id'] ?? null;

// POST繝・・繧ｿ縺ｾ縺溘・GET繝代Λ繝｡繝ｼ繧ｿ縺九ｉ蜿門ｾ・$studentIds = $_POST['student_ids'] ?? [];
$activityName = $_POST['activity_name'] ?? '';
$recordDate = $_POST['record_date'] ?? date('Y-m-d');
$activityId = $_GET['activity_id'] ?? null;
$supportPlanId = $_POST['support_plan_id'] ?? null;

// 謾ｯ謠ｴ譯域ュ蝣ｱ繧貞叙蠕暦ｼ域眠隕丈ｽ懈・譎ゅ↓謾ｯ謠ｴ譯医′驕ｸ謚槭＆繧後※縺・ｋ蝣ｴ蜷茨ｼ・$supportPlan = null;
if ($supportPlanId && !$activityId) {
    $stmt = $pdo->prepare("
        SELECT * FROM support_plans WHERE id = ?
    ");
    $stmt->execute([$supportPlanId]);
    $supportPlan = $stmt->fetch();
}

// 譌｢蟄倥・豢ｻ蜍輔ｒ邱ｨ髮・☆繧句ｴ蜷茨ｼ亥酔縺俶蕗螳､縺ｮ繧ｹ繧ｿ繝・ヵ縺御ｽ懈・縺励◆豢ｻ蜍輔ｂ邱ｨ髮・庄閭ｽ・・if ($activityId) {
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
        $_SESSION['error'] = '縺薙・豢ｻ蜍輔↓繧｢繧ｯ繧ｻ繧ｹ縺吶ｋ讓ｩ髯舌′縺ゅｊ縺ｾ縺帙ｓ';
        header('Location: renrakucho_activities.php');
        exit;
    }

    $activityName = $existingRecord['activity_name'];

    // 譌｢蟄倥・蜿ょ刈閠・ｒ蜿門ｾ・    $stmt = $pdo->prepare("
        SELECT DISTINCT student_id FROM student_records WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);
    $studentIds = array_column($stmt->fetchAll(), 'student_id');
}

if (empty($studentIds)) {
    header('Location: renrakucho.php');
    exit;
}

// 蜿ょ刈閠・ュ蝣ｱ繧貞叙蠕暦ｼ郁・蛻・・謨吝ｮ､縺ｮ逕溷ｾ偵・縺ｿ縲√そ繧ｭ繝･繝ｪ繝・ぅ蟇ｾ遲厄ｼ・$placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
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

// 譌｢蟄倥・蟄ｦ逕溯ｨ倬鹸繧貞叙蠕・$existingStudentRecords = [];

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

// 霑ｽ蜉蜿ｯ閭ｽ縺ｪ蜈ｨ逕溷ｾ偵ｒ蜿門ｾ暦ｼ郁・蛻・・謨吝ｮ､縺ｮ逕溷ｾ偵°繧峨√☆縺ｧ縺ｫ蜿ょ刈縺励※縺・ｋ逕溷ｾ偵ｒ髯､縺擾ｼ・$availableStudents = [];
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
        // 蜿ょ刈逕溷ｾ偵′縺・↑縺・ｴ蜷医・蜈ｨ蜩｡陦ｨ遉ｺ
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

// 譛ｬ譌･蜿ょ刈莠亥ｮ壹・逕溷ｾ偵ｒ蜿門ｾ暦ｼ域屆譌･繝吶・繧ｹ・・$todayDayOfWeek = date('w', strtotime($recordDate)); // 0=譌･譖・ 1=譛域屆, ...
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

// 5鬆伜沺縺ｮ螳夂ｾｩ
$domains = [
    'health_life' => '蛛･蠎ｷ繝ｻ逕滓ｴｻ',
    'motor_sensory' => '驕句虚繝ｻ諢溯ｦ・,
    'cognitive_behavior' => '隱咲衍繝ｻ陦悟虚',
    'language_communication' => '險隱槭・繧ｳ繝溘Η繝九こ繝ｼ繧ｷ繝ｧ繝ｳ',
    'social_relations' => '莠ｺ髢馴未菫ゅ・遉ｾ莨壽ｧ'
];

// 繝壹・繧ｸ髢句ｧ・$currentPage = 'renrakucho_form';
$pageTitle = '騾｣邨｡蟶ｳ蜈･蜉帙ヵ繧ｩ繝ｼ繝';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .common-activity-section {
            background: var(--apple-bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--apple-orange);
        }

        .common-activity-section h2 {
            color: #856404;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .common-activity-section textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .student-card {
            background: var(--apple-bg-primary);
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
            color: #555;
            font-size: var(--text-callout);
            margin-bottom: var(--spacing-md);
        }

        .domain-group {
            margin-bottom: 25px;
        }

        .domain-select {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            margin-bottom: var(--spacing-md);
            background: var(--apple-bg-primary);
        }

        .domain-textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            background: var(--apple-bg-primary);
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
            background: var(--apple-green);
            color: white;
        }

        .btn-secondary {
            background: var(--apple-blue);
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

        /* 繝｢繝ｼ繝繝ｫ */
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
            background: var(--apple-bg-primary);
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

        /* 讀懃ｴ｢繝輔ぅ繝ｫ繧ｿ */
        .search-filters {
            background: var(--apple-gray-6);
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
            border: 2px solid var(--apple-gray-5);
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
            background: var(--apple-green);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: var(--text-subhead);
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-scheduled:hover {
            background: var(--apple-green);
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
            border: 2px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-item:hover {
            border-color: var(--primary-purple);
            background: var(--apple-gray-6);
        }

        .student-item.selected {
            border-color: var(--primary-purple);
            background: #e3f2fd;
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
            background: var(--apple-green);
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: 600;
        }

        .student-item-check {
            width: 24px;
            height: 24px;
            border: 2px solid var(--apple-gray-5);
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
            content: '笨・;
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
            background: var(--apple-gray);
            color: white;
        }

        .btn-cancel:hover {
            background: var(--apple-gray);
        }

        .add-student-section {
            background: var(--apple-bg-primary);
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
            background: var(--apple-red);
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
            background: var(--apple-red);
        }

        .save-student-btn {
            background: var(--apple-green);
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
            background: var(--apple-green);
        }

        .save-student-btn.visible {
            display: inline-block;
        }

        .save-student-btn.saved {
            background: var(--apple-gray);
            cursor: default;
        }

        .save-student-btn.saved:hover {
            background: var(--apple-gray);
            transform: none;
        }
    </style>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">騾｣邨｡蟶ｳ蜈･蜉帙ヵ繧ｩ繝ｼ繝</h1>
        <p class="page-subtitle">豢ｻ蜍募錐: <?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="page-header-actions">
        <a href="renrakucho_activities.php" class="btn btn-secondary">竊・豢ｻ蜍穂ｸ隕ｧ縺ｸ</a>
    </div>
</div>

        <div style="background: var(--apple-bg-primary); padding: 15px 20px; border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);">
            <div style="margin-bottom: var(--spacing-md);">
                <strong style="color: var(--primary-purple); font-size: 18px;">豢ｻ蜍募錐:</strong>
                <span style="font-size: 18px; margin-left: 10px;"><?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if (isset($existingRecord) && $existingRecord): ?>
                <div style="font-size: var(--text-subhead); color: var(--text-secondary);">
                    菴懈・閠・ <?php echo htmlspecialchars($existingRecord['staff_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($existingRecord['staff_id'] == $currentUser['id']): ?>
                        <span style="color: var(--primary-purple); font-weight: bold;">(閾ｪ蛻・</span>
                    <?php else: ?>
                        <span style="color: #ff9800; font-weight: bold;">(莉悶・繧ｹ繧ｿ繝・ヵ)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($supportPlan): ?>
            <!-- 謾ｯ謠ｴ譯域ュ蝣ｱ縺ｮ陦ｨ遉ｺ -->
            <div style="background: var(--apple-bg-primary); padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); border-left: 4px solid var(--primary-purple);">
                <h2 style="color: var(--primary-purple); font-size: 18px; margin-bottom: 15px;">統 驕ｸ謚槭＆繧後◆謾ｯ謠ｴ譯・/h2>
                <div style="font-size: var(--text-subhead); line-height: 1.8;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: var(--primary-purple);">豢ｻ蜍募錐:</strong>
                        <?php echo htmlspecialchars($supportPlan['activity_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if (!empty($supportPlan['activity_purpose'])): ?>
                        <div style="margin-bottom: 12px;">
                            <strong style="color: var(--primary-purple);">豢ｻ蜍輔・逶ｮ逧・</strong><br>
                            <?php echo nl2br(htmlspecialchars($supportPlan['activity_purpose'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($supportPlan['five_domains_consideration'])): ?>
                        <div style="margin-bottom: 12px;">
                            <strong style="color: var(--primary-purple);">莠秘伜沺縺ｸ縺ｮ驟肴・:</strong><br>
                            <?php echo nl2br(htmlspecialchars($supportPlan['five_domains_consideration'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($supportPlan['other_notes'])): ?>
                        <div style="margin-bottom: 12px;">
                            <strong style="color: var(--primary-purple);">縺昴・莉・</strong><br>
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

            <!-- 蜈ｱ騾壽ｴｻ蜍募・蜉帶ｬ・-->
            <div class="common-activity-section">
                <h2>譛ｬ譌･縺ｮ豢ｻ蜍包ｼ亥・騾夲ｼ・/h2>
                <p class="info-text">蜈ｨ縺ｦ縺ｮ蜿ょ刈閠・↓蜿肴丐縺輔ｌ繧句・騾壹・豢ｻ蜍募・螳ｹ繧定ｨ伜・縺励※縺上□縺輔＞</p>
                <?php if ($supportPlan): ?>
                    <p class="info-text" style="background: #e7f3ff; padding: var(--spacing-md); border-radius: var(--radius-sm); border-left: 4px solid var(--primary-purple); margin-bottom: var(--spacing-md);">
                        庁 謾ｯ謠ｴ譯医・?php echo htmlspecialchars($supportPlan['activity_name'], ENT_QUOTES, 'UTF-8'); ?>縲阪・豢ｻ蜍募・螳ｹ縺悟渚譏縺輔ｌ縺ｦ縺・∪縺吶ょｿ・ｦ√↓蠢懊§縺ｦ邱ｨ髮・＠縺ｦ縺上□縺輔＞縲・                    </p>
                <?php endif; ?>
                <textarea
                    name="common_activity"
                    id="commonActivity"
                    placeholder="萓・ 蜈ｬ蝨偵〒謨｣豁ｩ縲・浹讌ｽ豢ｻ蜍輔∝宛菴懈ｴｻ蜍輔↑縺ｩ"
                ><?php
                    // 譌｢蟄倥・豢ｻ蜍輔ｒ邱ｨ髮・☆繧句ｴ蜷医・縺昴・蜀・ｮｹ縲∵眠隕丈ｽ懈・縺ｧ謾ｯ謠ｴ譯医′縺ゅｋ蝣ｴ蜷医・謾ｯ謠ｴ譯医・蜀・ｮｹ縲√◎繧御ｻ･螟悶・遨ｺ
                    if (isset($existingRecord['common_activity'])) {
                        echo htmlspecialchars($existingRecord['common_activity'], ENT_QUOTES, 'UTF-8');
                    } elseif ($supportPlan && !empty($supportPlan['activity_content'])) {
                        echo htmlspecialchars($supportPlan['activity_content'], ENT_QUOTES, 'UTF-8');
                    }
                ?></textarea>
            </div>

            <!-- 蛟句挨縺ｮ逕溷ｾ定ｨ倬鹸 -->
            <?php foreach ($students as $student): ?>
                <?php
                $studentId = $student['id'];
                $existingData = $existingStudentRecords[$studentId] ?? null;
                ?>
                <div class="student-card" data-student-id="<?php echo $studentId; ?>">
                    <?php if ($activityId): ?>
                    <button type="button" class="save-student-btn" data-student-id="<?php echo $studentId; ?>" onclick="saveStudent(<?php echo $studentId; ?>)">縺薙・逕溷ｾ偵・菫ｮ豁｣繧剃ｿ晏ｭ・/button>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?></h3>

                    <input type="hidden" name="students[<?php echo $studentId; ?>][id]" value="<?php echo $studentId; ?>">

                    <!-- 譛ｬ譌･縺ｮ讒伜ｭ・-->
                    <div class="domain-group" style="background: #e3f2fd; padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid #2196f3;">
                        <h4 style="color: #1976d2;">譛ｬ譌･縺ｮ讒伜ｭ・/h4>
                        <textarea
                            name="students[<?php echo $studentId; ?>][daily_note]"
                            class="domain-textarea"
                            placeholder="譛ｬ譌･縺ｮ蜈ｨ菴鍋噪縺ｪ讒伜ｭ舌ｒ閾ｪ逕ｱ縺ｫ險伜・縺励※縺上□縺輔＞"
                            style="background: var(--apple-bg-primary);"
                        ><?php echo htmlspecialchars($existingData['daily_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 鬆伜沺1 -->
                    <div class="domain-group">
                        <h4>豌励↓縺ｪ縺｣縺溘％縺ｨ 1縺､逶ｮ</h4>
                        <select
                            name="students[<?php echo $studentId; ?>][domain1]"
                            class="domain-select"
                            required
                        >
                            <option value="">鬆伜沺繧帝∈謚槭＠縺ｦ縺上□縺輔＞</option>
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
                            placeholder="豌励↓縺ｪ縺｣縺溘％縺ｨ繧定ｨ伜・縺励※縺上□縺輔＞"
                            required
                        ><?php echo htmlspecialchars($existingData['domain1_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 鬆伜沺2 -->
                    <div class="domain-group">
                        <h4>豌励↓縺ｪ縺｣縺溘％縺ｨ 2縺､逶ｮ</h4>
                        <select
                            name="students[<?php echo $studentId; ?>][domain2]"
                            class="domain-select"
                            required
                        >
                            <option value="">鬆伜沺繧帝∈謚槭＠縺ｦ縺上□縺輔＞</option>
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
                            placeholder="豌励↓縺ｪ縺｣縺溘％縺ｨ繧定ｨ伜・縺励※縺上□縺輔＞"
                            required
                        ><?php echo htmlspecialchars($existingData['domain2_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 蜿ょ刈逕溷ｾ偵ｒ霑ｽ蜉繧ｻ繧ｯ繧ｷ繝ｧ繝ｳ -->
            <?php if (!empty($availableStudents)): ?>
            <div class="add-student-section">
                <button type="button" class="btn-add-student" onclick="openAddStudentModal()">
                    筐・蜿ょ刈逕溷ｾ偵ｒ霑ｽ蜉
                </button>
                <p class="info-text">霑ｽ蜉蜿ｯ閭ｽ縺ｪ逕溷ｾ・ <?php echo count($availableStudents); ?>蜷・/p>
            </div>
            <?php endif; ?>

            <!-- 騾∽ｿ｡繝懊ち繝ｳ -->
            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <?php echo $activityId ? '蜈ｨ菴薙ｒ縺薙・蜀・ｮｹ縺ｧ菫晏ｭ・ : '遒ｺ螳壹＠縺ｦ菫晏ｭ・; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- 逕溷ｾ定ｿｽ蜉繝｢繝ｼ繝繝ｫ -->
    <div class="modal" id="addStudentModal">
        <div class="modal-content">
            <h3 class="modal-header">蜿ょ刈逕溷ｾ偵ｒ霑ｽ蜉</h3>

            <!-- 讀懃ｴ｢繝輔ぅ繝ｫ繧ｿ -->
            <div class="search-filters">
                <div class="filter-group">
                    <label>蟄ｦ蟷ｴ縺ｧ邨槭ｊ霎ｼ縺ｿ:</label>
                    <select id="gradeFilter" onchange="filterStudents()">
                        <option value="">縺吶∋縺ｦ縺ｮ蟄ｦ蟷ｴ</option>
                        <option value="蟆・">蟆・</option>
                        <option value="蟆・">蟆・</option>
                        <option value="蟆・">蟆・</option>
                        <option value="蟆・">蟆・</option>
                        <option value="蟆・">蟆・</option>
                        <option value="蟆・">蟆・</option>
                        <option value="荳ｭ1">荳ｭ1</option>
                        <option value="荳ｭ2">荳ｭ2</option>
                        <option value="荳ｭ3">荳ｭ3</option>
                        <option value="鬮・">鬮・</option>
                        <option value="鬮・">鬮・</option>
                        <option value="鬮・">鬮・</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>豌丞錐縺ｧ讀懃ｴ｢:</label>
                    <input type="text" id="nameFilter" placeholder="豌丞錐縺ｮ荳驛ｨ繧貞・蜉・ oninput="filterStudents()">
                </div>
                <div class="filter-group">
                    <button type="button" class="btn btn-scheduled" onclick="showScheduledOnly()">套 譛ｬ譌･蜿ょ刈莠亥ｮ壹・逕溷ｾ偵°繧蛾∈謚・/button>
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
                            <div class="student-item-badge">譛ｬ譌･莠亥ｮ・/div>
                        <?php endif; ?>
                    </div>
                    <div class="student-item-check"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeAddStudentModal()">繧ｭ繝｣繝ｳ繧ｻ繝ｫ</button>
                <button type="button" class="btn btn-secondary" onclick="addSelectedStudents()">驕ｸ謚槭＠縺溽函蠕偵ｒ霑ｽ蜉</button>
            </div>
        </div>
    </div>

    <script>
        // 5鬆伜沺縺ｮ螳夂ｾｩ・・avaScript縺ｧ繧ゆｽｿ逕ｨ・・        const domains = <?php echo json_encode($domains); ?>;

        // 驕ｸ謚槭＆繧後◆逕溷ｾ偵ｒ邂｡逅・        let selectedStudentsForAdd = new Set();

        // 繝｢繝ｼ繝繝ｫ繧帝幕縺・        function openAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('active');
            selectedStudentsForAdd.clear();
            // 驕ｸ謚樒憾諷九ｒ繝ｪ繧ｻ繝・ヨ
            document.querySelectorAll('.student-item').forEach(item => {
                item.classList.remove('selected');
            });
            // 繝輔ぅ繝ｫ繧ｿ繧偵Μ繧ｻ繝・ヨ
            document.getElementById('gradeFilter').value = '';
            document.getElementById('nameFilter').value = '';
            filterStudents();
        }

        // 繝｢繝ｼ繝繝ｫ繧帝哩縺倥ｋ
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('active');
            selectedStudentsForAdd.clear();
        }

        // 逕溷ｾ偵Μ繧ｹ繝医ｒ繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ
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

            // 讀懃ｴ｢邨先棡縺・莉ｶ縺ｮ蝣ｴ蜷医↓繝｡繝・そ繝ｼ繧ｸ繧定｡ｨ遉ｺ・医が繝励す繝ｧ繝ｳ・・            // console.log(`讀懃ｴ｢邨先棡: ${visibleCount}蜷港);
        }

        // 譛ｬ譌･蜿ょ刈莠亥ｮ壹・逕溷ｾ偵・縺ｿ陦ｨ遉ｺ
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

            // 繝輔ぅ繝ｫ繧ｿ繧偵Μ繧ｻ繝・ヨ
            document.getElementById('gradeFilter').value = '';
            document.getElementById('nameFilter').value = '';

            if (scheduledCount === 0) {
                alert('譛ｬ譌･蜿ょ刈莠亥ｮ壹・逕溷ｾ偵・縺・∪縺帙ｓ');
            }
        }

        // 逕溷ｾ帝∈謚槭ｒ繝医げ繝ｫ
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

        // 驕ｸ謚槭＠縺溽函蠕偵ｒ繝輔か繝ｼ繝縺ｫ霑ｽ蜉
        function addSelectedStudents() {
            if (selectedStudentsForAdd.size === 0) {
                alert('霑ｽ蜉縺吶ｋ逕溷ｾ偵ｒ驕ｸ謚槭＠縺ｦ縺上□縺輔＞');
                return;
            }

            const form = document.getElementById('renrakuchoForm');
            const submitSection = document.querySelector('.form-actions');

            selectedStudentsForAdd.forEach(studentId => {
                const studentItem = document.querySelector(`.student-item[data-student-id="${studentId}"]`);
                const studentName = studentItem.dataset.studentName;

                // 逕溷ｾ偵き繝ｼ繝峨ｒ菴懈・
                const studentCard = createStudentCard(studentId, studentName);

                // 騾∽ｿ｡繝懊ち繝ｳ縺ｮ蜑阪↓謖ｿ蜈･
                submitSection.parentNode.insertBefore(studentCard, submitSection);

                // 繝｢繝ｼ繝繝ｫ縺九ｉ隧ｲ蠖薙・逕溷ｾ偵ｒ蜑企勁
                studentItem.remove();
            });

            // 霑ｽ蜉蜿ｯ閭ｽ縺ｪ逕溷ｾ呈焚繧呈峩譁ｰ
            updateAvailableStudentCount();

            closeAddStudentModal();

            // 繧ｹ繧ｯ繝ｭ繝ｼ繝ｫ縺励※霑ｽ蜉縺輔ｌ縺溽函蠕偵′隕九∴繧九ｈ縺・↓縺吶ｋ
            setTimeout(() => {
                const newCards = document.querySelectorAll('.student-card.new');
                if (newCards.length > 0) {
                    newCards[newCards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }

        // 逕溷ｾ偵き繝ｼ繝峨ｒ菴懈・
        function createStudentCard(studentId, studentName) {
            const card = document.createElement('div');
            card.className = 'student-card new';
            card.dataset.studentId = studentId;

            const domainOptions = Object.entries(domains).map(([key, label]) =>
                `<option value="${key}">${escapeHtml(label)}</option>`
            ).join('');

            card.innerHTML = `
                <button type="button" class="remove-student-btn" onclick="removeStudentCard(${studentId})">笨・縺薙・逕溷ｾ偵ｒ蜑企勁</button>
                <h3>${escapeHtml(studentName)}</h3>

                <input type="hidden" name="students[${studentId}][id]" value="${studentId}">

                <!-- 譛ｬ譌･縺ｮ讒伜ｭ・-->
                <div class="domain-group" style="background: #e3f2fd; padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid #2196f3;">
                    <h4 style="color: #1976d2;">譛ｬ譌･縺ｮ讒伜ｭ・/h4>
                    <textarea
                        name="students[${studentId}][daily_note]"
                        class="domain-textarea"
                        placeholder="譛ｬ譌･縺ｮ蜈ｨ菴鍋噪縺ｪ讒伜ｭ舌ｒ閾ｪ逕ｱ縺ｫ險伜・縺励※縺上□縺輔＞"
                        style="background: var(--apple-bg-primary);"
                    ></textarea>
                </div>

                <!-- 鬆伜沺1 -->
                <div class="domain-group">
                    <h4>豌励↓縺ｪ縺｣縺溘％縺ｨ 1縺､逶ｮ</h4>
                    <select name="students[${studentId}][domain1]" class="domain-select" required>
                        <option value="">鬆伜沺繧帝∈謚槭＠縺ｦ縺上□縺輔＞</option>
                        ${domainOptions}
                    </select>
                    <textarea
                        name="students[${studentId}][domain1_content]"
                        class="domain-textarea"
                        placeholder="豌励↓縺ｪ縺｣縺溘％縺ｨ繧定ｨ伜・縺励※縺上□縺輔＞"
                        required
                    ></textarea>
                </div>

                <!-- 鬆伜沺2 -->
                <div class="domain-group">
                    <h4>豌励↓縺ｪ縺｣縺溘％縺ｨ 2縺､逶ｮ</h4>
                    <select name="students[${studentId}][domain2]" class="domain-select" required>
                        <option value="">鬆伜沺繧帝∈謚槭＠縺ｦ縺上□縺輔＞</option>
                        ${domainOptions}
                    </select>
                    <textarea
                        name="students[${studentId}][domain2_content]"
                        class="domain-textarea"
                        placeholder="豌励↓縺ｪ縺｣縺溘％縺ｨ繧定ｨ伜・縺励※縺上□縺輔＞"
                        required
                    ></textarea>
                </div>
            `;

            // 繧｢繝九Γ繝ｼ繧ｷ繝ｧ繝ｳ繧ｯ繝ｩ繧ｹ繧剃ｸ螳壽凾髢灘ｾ後↓蜑企勁
            setTimeout(() => {
                card.classList.remove('new');
            }, 300);

            return card;
        }

        // 逕溷ｾ偵き繝ｼ繝峨ｒ蜑企勁
        function removeStudentCard(studentId) {
            const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
            if (!card) return;

            const studentName = card.querySelector('h3').textContent;

            if (confirm(`縲・{studentName}縲阪・蜈･蜉帙ｒ蜑企勁縺励∪縺吶°・歔)) {
                // 繝｢繝ｼ繝繝ｫ縺ｮ繝ｪ繧ｹ繝医↓謌ｻ縺・                const studentList = document.getElementById('availableStudentList');
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

                // 繧ｫ繝ｼ繝峨ｒ蜑企勁
                card.remove();

                // 霑ｽ蜉蜿ｯ閭ｽ縺ｪ逕溷ｾ呈焚繧呈峩譁ｰ
                updateAvailableStudentCount();
            }
        }

        // 霑ｽ蜉蜿ｯ閭ｽ縺ｪ逕溷ｾ呈焚繧呈峩譁ｰ
        function updateAvailableStudentCount() {
            const addSection = document.querySelector('.add-student-section');
            if (!addSection) return;

            const count = document.querySelectorAll('#availableStudentList .student-item').length;
            const infoText = addSection.querySelector('.info-text');

            if (count === 0) {
                addSection.style.display = 'none';
            } else {
                addSection.style.display = 'block';
                infoText.textContent = `霑ｽ蜉蜿ｯ閭ｽ縺ｪ逕溷ｾ・ ${count}蜷港;
            }
        }

        // HTML繧ｨ繧ｹ繧ｱ繝ｼ繝・        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // 繝｢繝ｼ繝繝ｫ螟悶け繝ｪ繝・け縺ｧ髢峨§繧・        document.getElementById('addStudentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddStudentModal();
            }
        });

        // 繝輔か繝ｼ繝騾∽ｿ｡蜑阪・繝舌Μ繝・・繧ｷ繝ｧ繝ｳ
        document.getElementById('renrakuchoForm').addEventListener('submit', function(e) {
            const commonActivity = document.getElementById('commonActivity').value.trim();

            if (commonActivity === '') {
                alert('譛ｬ譌･縺ｮ豢ｻ蜍包ｼ亥・騾夲ｼ峨ｒ蜈･蜉帙＠縺ｦ縺上□縺輔＞');
                e.preventDefault();
                return false;
            }

            // 蜷・函蠕偵・鬆伜沺縺碁㍾隍・＠縺ｦ縺・↑縺・°繝√ぉ繝・け
            const studentCards = document.querySelectorAll('.student-card');
            let hasError = false;

            studentCards.forEach(card => {
                const selects = card.querySelectorAll('.domain-select');
                const domain1 = selects[0].value;
                const domain2 = selects[1].value;

                if (domain1 === domain2 && domain1 !== '') {
                    alert('蜷後§鬆伜沺繧・蝗樣∈謚槭☆繧九％縺ｨ縺ｯ縺ｧ縺阪∪縺帙ｓ');
                    hasError = true;
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            return true;
        });

        // 蜷・函蠕偵き繝ｼ繝峨・蜈･蜉帛､画峩繧堤屮隕・        function initializeChangeDetection() {
            const studentCards = document.querySelectorAll('.student-card');

            studentCards.forEach(card => {
                const studentId = card.dataset.studentId;
                const saveBtn = card.querySelector('.save-student-btn');

                if (!saveBtn) return; // 邱ｨ髮・Δ繝ｼ繝画凾縺ｮ縺ｿ

                // 蜈･蜉帙ヵ繧｣繝ｼ繝ｫ繝峨ｒ蜿門ｾ・                const inputs = card.querySelectorAll('textarea, select');

                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        // 螟画峩縺後≠縺｣縺溘ｉ繝懊ち繝ｳ繧定｡ｨ遉ｺ
                        if (!saveBtn.classList.contains('saved')) {
                            saveBtn.classList.add('visible');
                        }
                    });

                    input.addEventListener('change', function() {
                        // 螟画峩縺後≠縺｣縺溘ｉ繝懊ち繝ｳ繧定｡ｨ遉ｺ
                        if (!saveBtn.classList.contains('saved')) {
                            saveBtn.classList.add('visible');
                        }
                    });
                });
            });
        }

        // 蛟句挨逕溷ｾ偵・菫晏ｭ伜・逅・        function saveStudent(studentId) {
            const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
            const saveBtn = card.querySelector('.save-student-btn');

            // 繝懊ち繝ｳ繧堤┌蜉ｹ蛹・            saveBtn.disabled = true;
            const originalText = saveBtn.textContent;
            saveBtn.textContent = '菫晏ｭ倅ｸｭ...';

            // 繝輔か繝ｼ繝繝・・繧ｿ繧貞庶髮・            const dailyNote = card.querySelector(`textarea[name="students[${studentId}][daily_note]"]`).value;
            const domain1 = card.querySelector(`select[name="students[${studentId}][domain1]"]`).value;
            const domain1Content = card.querySelector(`textarea[name="students[${studentId}][domain1_content]"]`).value;
            const domain2 = card.querySelector(`select[name="students[${studentId}][domain2]"]`).value;
            const domain2Content = card.querySelector(`textarea[name="students[${studentId}][domain2_content]"]`).value;

            // 繝舌Μ繝・・繧ｷ繝ｧ繝ｳ
            if (!domain1 || !domain1Content.trim()) {
                alert('豌励↓縺ｪ縺｣縺溘％縺ｨ1縺､逶ｮ縺ｮ鬆伜沺縺ｨ蜀・ｮｹ繧貞・蜉帙＠縺ｦ縺上□縺輔＞');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
                return;
            }

            if (!domain2 || !domain2Content.trim()) {
                alert('豌励↓縺ｪ縺｣縺溘％縺ｨ2縺､逶ｮ縺ｮ鬆伜沺縺ｨ蜀・ｮｹ繧貞・蜉帙＠縺ｦ縺上□縺輔＞');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
                return;
            }

            if (domain1 === domain2) {
                alert('蜷後§鬆伜沺繧・蝗樣∈謚槭☆繧九％縺ｨ縺ｯ縺ｧ縺阪∪縺帙ｓ');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
                return;
            }

            // Ajax繝ｪ繧ｯ繧ｨ繧ｹ繝・            const activityId = document.querySelector('input[name="activity_id"]').value;
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
                    // 謌仙粥譎・                    saveBtn.textContent = '菫ｮ豁｣縺悟ｮ御ｺ・＠縺ｾ縺励◆';
                    saveBtn.classList.add('saved');
                    saveBtn.disabled = true;

                    // 3遘貞ｾ後↓繝懊ち繝ｳ繧貞・縺ｫ謌ｻ縺呻ｼ亥・邱ｨ髮・庄閭ｽ縺ｫ縺吶ｋ・・                    setTimeout(() => {
                        saveBtn.classList.remove('saved', 'visible');
                        saveBtn.disabled = false;
                        saveBtn.textContent = '縺薙・逕溷ｾ偵・菫ｮ豁｣繧剃ｿ晏ｭ・;
                    }, 3000);
                } else {
                    alert('菫晏ｭ倥↓螟ｱ謨励＠縺ｾ縺励◆: ' + (data.error || '荳肴・縺ｪ繧ｨ繝ｩ繝ｼ'));
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('菫晏ｭ倅ｸｭ縺ｫ繧ｨ繝ｩ繝ｼ縺檎匱逕溘＠縺ｾ縺励◆');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            });
        }

        // 繝壹・繧ｸ隱ｭ縺ｿ霎ｼ縺ｿ譎ゅ↓螟画峩讀懃衍繧貞・譛溷喧
        document.addEventListener('DOMContentLoaded', function() {
            initializeChangeDetection();
        });
    </script>

<?php renderPageEnd(); ?>
