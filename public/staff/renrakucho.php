<?php
/**
 * 騾｣邨｡蟶ｳ蜈･蜉帙・繝ｼ繧ｸ・医せ繧ｿ繝・ヵ逕ｨ・・ */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 繧ｹ繧ｿ繝・ヵ縺ｾ縺溘・邂｡逅・・・縺ｿ繧｢繧ｯ繧ｻ繧ｹ蜿ｯ閭ｽ
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 繧ｹ繧ｿ繝・ヵ縺ｮ謨吝ｮ､ID繧貞叙蠕・$classroomId = $_SESSION['classroom_id'] ?? null;

// 蟄ｦ蟷ｴ繝輔ぅ繝ｫ繧ｿ繝ｼ蜿門ｾ・$gradeFilter = $_GET['grade'] ?? 'all';

// 譌･莉倥ｒ蜿門ｾ暦ｼ・RL繝代Λ繝｡繝ｼ繧ｿ縺九ｉ縲√∪縺溘・譛ｬ譌･・・$today = $_GET['date'] ?? date('Y-m-d');

// 譛ｬ譌･縺ｮ譖懈律繧貞叙蠕・$todayDayOfWeek = date('w', strtotime($today));
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

// 譛ｬ譌･縺御ｼ第律縺九メ繧ｧ繝・け・郁・蛻・・謨吝ｮ､縺ｮ莨第律縺ｮ縺ｿ・・$stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ? AND classroom_id = ?");
$stmt->execute([$today, $classroomId]);
$isTodayHoliday = $stmt->fetchColumn() > 0;

// 譛ｬ譌･縺ｮ莠亥ｮ壼盾蜉閠・D繧貞叙蠕暦ｼ郁・蛻・・謨吝ｮ､縺ｮ逕溷ｾ偵・縺ｿ・・$scheduledStudentIds = [];
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

// 逕溷ｾ偵ｒ蜿門ｾ暦ｼ亥ｭｦ蟷ｴ繝輔ぅ繝ｫ繧ｿ繝ｼ縺ｨ譛ｬ譌･縺ｮ莠亥ｮ壼盾蜉閠・ヵ繧｣繝ｫ繧ｿ繝ｼ蟇ｾ蠢懊∵蕗螳､繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ・・if ($classroomId) {
    $sql = "
        SELECT s.id, s.student_name, s.grade_level
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = :classroom_id
    ";
} else {
    $sql = "
        SELECT id, student_name, grade_level
        FROM students
        WHERE is_active = 1
    ";
}

if ($gradeFilter === 'scheduled') {
    // 譛ｬ譌･縺ｮ莠亥ｮ壼盾蜉閠・ヵ繧｣繝ｫ繧ｿ繝ｼ
    if (empty($scheduledStudentIds)) {
        $allStudents = [];
    } else {
        // 蜷榊燕莉倥″繝励Ξ繝ｼ繧ｹ繝帙Ν繝繝ｼ繧堤函謌・        $placeholders = [];
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

// 譌｢蟄倥・譛ｬ譌･縺ｮ險倬鹸縺後≠繧九°繝√ぉ繝・け
$stmt = $pdo->prepare("
    SELECT dr.id, dr.common_activity, dr.record_date
    FROM daily_records dr
    WHERE dr.record_date = ? AND dr.staff_id = ?
");
$stmt->execute([$today, $currentUser['id']]);
$existingRecord = $stmt->fetch();

// 譌｢蟄倥・險倬鹸縺後≠繧句ｴ蜷医∝盾蜉閠・ｒ蜿門ｾ・$existingParticipants = [];
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

// 謾ｯ謠ｴ譯域､懃ｴ｢繝代Λ繝｡繝ｼ繧ｿ
$searchTag = $_GET['plan_tag'] ?? '';
$searchDayOfWeek = $_GET['plan_day'] ?? '';

// 莉頑律縺ｮ譖懈律繧貞叙蠕・$todayDayOfWeek = date('w', strtotime($today));
$dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$todayDayName = $dayNames[$todayDayOfWeek];

// 謾ｯ謠ｴ譯医ｒ蜿門ｾ暦ｼ域､懃ｴ｢譚｡莉ｶ莉倥″・・$planWhere = [];
$planParams = [];

if ($classroomId) {
    $planWhere[] = "sp.classroom_id = ?";
    $planParams[] = $classroomId;
}

// 譌･莉倥∪縺溘・繧ｿ繧ｰ繝ｻ譖懈律縺ｧ邨槭ｊ霎ｼ縺ｿ
if (empty($searchTag) && empty($searchDayOfWeek)) {
    // 讀懃ｴ｢譚｡莉ｶ縺後↑縺・ｴ蜷医・縲√◎縺ｮ譌･縺ｮ謾ｯ謠ｴ譯医・縺ｿ
    $planWhere[] = "sp.activity_date = ?";
    $planParams[] = $today;
} else {
    // 讀懃ｴ｢譚｡莉ｶ縺後≠繧句ｴ蜷・    if (!empty($searchTag)) {
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

// 譛ｪ隱ｭ繝√Ε繝・ヨ繝｡繝・そ繝ｼ繧ｸ繧貞叙蠕暦ｼ医せ繧ｿ繝・ヵ逕ｨ・壻ｿ晁ｭｷ閠・°繧峨・譛ｪ隱ｭ繝｡繝・そ繝ｼ繧ｸ・・$unreadChatMessages = [];
try {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT
                cr.id as room_id,
                s.student_name,
                u.full_name as guardian_name,
                COUNT(cm.id) as unread_count,
                MAX(cm.created_at) as last_message_at
            FROM chat_rooms cr
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN users u ON cr.guardian_id = u.id
            INNER JOIN chat_messages cm ON cr.id = cm.room_id
            WHERE u.classroom_id = ?
            AND cm.sender_type = 'guardian'
            AND cm.is_read = 0
            GROUP BY cr.id, s.student_name, u.full_name
            ORDER BY last_message_at DESC
        ");
        $stmt->execute([$classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                cr.id as room_id,
                s.student_name,
                u.full_name as guardian_name,
                COUNT(cm.id) as unread_count,
                MAX(cm.created_at) as last_message_at
            FROM chat_rooms cr
            INNER JOIN students s ON cr.student_id = s.id
            INNER JOIN users u ON cr.guardian_id = u.id
            INNER JOIN chat_messages cm ON cr.id = cm.room_id
            WHERE cm.sender_type = 'guardian'
            AND cm.is_read = 0
            GROUP BY cr.id, s.student_name, u.full_name
            ORDER BY last_message_at DESC
        ");
        $stmt->execute();
    }
    $unreadChatMessages = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching unread chat messages: " . $e->getMessage());
}
$totalUnreadMessages = array_sum(array_column($unreadChatMessages, 'unread_count'));

// 繝壹・繧ｸ髢句ｧ・$currentPage = 'renrakucho';
renderPageStart('staff', $currentPage, '騾｣邨｡蟶ｳ蜈･蜉・);
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
    background: var(--apple-gray-6);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background 0.3s;
}

.student-checkbox:hover {
    background: var(--apple-gray-5);
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
    border-radius: 3px;
    margin-left: 5px;
}

.badge-elementary { background: rgba(255, 59, 48, 0.15); color: var(--apple-red); }
.badge-junior-high { background: rgba(0, 122, 255, 0.15); color: var(--apple-blue); }
.badge-high-school { background: rgba(175, 82, 222, 0.15); color: var(--apple-purple); }

.grade-filter {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
}

.grade-btn {
    padding: var(--spacing-sm) 16px;
    border: 2px solid var(--apple-blue);
    background: var(--apple-bg-primary);
    color: var(--apple-blue);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
    text-decoration: none;
    font-size: var(--text-subhead);
}

.grade-btn:hover { background: var(--apple-bg-secondary); }
.grade-btn.active { background: var(--apple-blue); color: white; }

.grade-btn-scheduled {
    border-color: var(--apple-green);
    color: var(--apple-green);
}
.grade-btn-scheduled:hover { background: rgba(52, 199, 89, 0.15); }
.grade-btn-scheduled.active { background: var(--apple-green); color: white; }

.quick-links {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
}
.quick-link:hover { background: var(--apple-gray-5); }

.unread-notification {
    background: rgba(0, 122, 255, 0.1);
    border-left: 5px solid var(--apple-blue);
    padding: 15px 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.unread-notification-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: bold;
    color: var(--apple-blue);
}

.unread-chat-item {
    background: var(--apple-bg-primary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--apple-gray-5);
}

.plan-search-box {
    background: var(--apple-gray-6);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-green);
}

.plan-search-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.plan-info-box {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    border-left: 4px solid var(--apple-orange);
    font-size: var(--text-subhead);
    margin-bottom: var(--spacing-md);
}

.plan-details-box {
    display: none;
    background: var(--apple-gray-6);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-blue);
}

@media (max-width: 768px) {
    .plan-search-form {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">騾｣邨｡蟶ｳ蜈･蜉・/h1>
        <p class="page-subtitle">險倬鹸譌･: <?= date('Y蟷ｴm譛・譌･・・ . ['譌･', '譛・, '轣ｫ', '豌ｴ', '譛ｨ', '驥・, '蝨・][date('w', strtotime($today))] . '・・, strtotime($today)) ?></p>
    </div>
</div>

<!-- 繧ｯ繧､繝・け繝ｪ繝ｳ繧ｯ -->
<div class="quick-links">
    <a href="kakehashi_staff.php" class="quick-link">潔 繧ｹ繧ｿ繝・ヵ縺九￠縺ｯ縺・/a>
    <a href="kakehashi_guardian_view.php" class="quick-link">搭 菫晁ｭｷ閠・°縺代・縺礼｢ｺ隱・/a>
    <a href="renrakucho_activities.php" class="quick-link">統 豢ｻ蜍穂ｸ隕ｧ</a>
</div>

<!-- 譁ｰ逹繝√Ε繝・ヨ繝｡繝・そ繝ｼ繧ｸ騾夂衍 -->
<?php if ($totalUnreadMessages > 0): ?>
    <div class="unread-notification">
        <div class="unread-notification-header">
            町 譁ｰ逹繝｡繝・そ繝ｼ繧ｸ縺後≠繧翫∪縺呻ｼ・?= $totalUnreadMessages ?>莉ｶ・・        </div>
        <?php foreach ($unreadChatMessages as $chatRoom): ?>
            <div class="unread-chat-item">
                <div>
                    <div style="font-weight: bold; color: var(--text-primary); margin-bottom: 5px;">
                        <?= htmlspecialchars($chatRoom['student_name']) ?>縺輔ｓ・・?= htmlspecialchars($chatRoom['guardian_name']) ?>讒假ｼ・                    </div>
                    <div style="font-size: var(--text-subhead); color: var(--text-secondary); margin-bottom: 3px;">
                        譛ｪ隱ｭ繝｡繝・そ繝ｼ繧ｸ: <?= $chatRoom['unread_count'] ?>莉ｶ
                    </div>
                    <div style="font-size: var(--text-subhead); font-weight: bold; color: var(--apple-blue);">
                        譛譁ｰ: <?= date('Y蟷ｴn譛・譌･ H:i', strtotime($chatRoom['last_message_at'])) ?>
                    </div>
                </div>
                <a href="chat.php?room_id=<?= $chatRoom['room_id'] ?>" class="btn btn-primary btn-sm">繝√Ε繝・ヨ繧帝幕縺・/a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if ($isTodayHoliday): ?>
    <div class="alert alert-danger">譛ｬ譌･縺ｯ莨第律縺ｧ縺吶・/div>
<?php endif; ?>

<?php if ($existingRecord): ?>
    <div class="alert alert-success">譛ｬ譌･縺ｮ險倬鹸縺梧里縺ｫ蟄伜惠縺励∪縺吶ゆｿｮ豁｣縺吶ｋ蝣ｴ蜷医・荳玖ｨ倥・繝輔か繝ｼ繝縺九ｉ邱ｨ髮・＠縺ｦ縺上□縺輔＞縲・/div>
<?php endif; ?>

<!-- 蟄ｦ蟷ｴ繝輔ぅ繝ｫ繧ｿ繝ｼ -->
<div class="grade-filter">
    <label style="font-weight: 600; color: var(--text-primary);">繝輔ぅ繝ｫ繧ｿ繝ｼ:</label>
    <a href="?date=<?= urlencode($today) ?>&grade=all" class="grade-btn <?= $gradeFilter === 'all' ? 'active' : '' ?>">縺吶∋縺ｦ</a>
    <a href="?date=<?= urlencode($today) ?>&grade=scheduled" class="grade-btn grade-btn-scheduled <?= $gradeFilter === 'scheduled' ? 'active' : '' ?>">
        譛ｬ譌･縺ｮ莠亥ｮ壼盾蜉閠・?php if (!$isTodayHoliday && !empty($scheduledStudentIds)): ?> (<?= count($scheduledStudentIds) ?>蜷・<?php endif; ?>
    </a>
    <a href="?date=<?= urlencode($today) ?>&grade=elementary" class="grade-btn <?= $gradeFilter === 'elementary' ? 'active' : '' ?>">蟆丞ｭｦ逕・/a>
    <a href="?date=<?= urlencode($today) ?>&grade=junior_high" class="grade-btn <?= $gradeFilter === 'junior_high' ? 'active' : '' ?>">荳ｭ蟄ｦ逕・/a>
    <a href="?date=<?= urlencode($today) ?>&grade=high_school" class="grade-btn <?= $gradeFilter === 'high_school' ? 'active' : '' ?>">鬮俶｡逕・/a>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">譁ｰ縺励＞豢ｻ蜍輔・霑ｽ蜉</h2>

        <!-- 謾ｯ謠ｴ譯域､懃ｴ｢ -->
        <div class="plan-search-box">
            <h3 style="margin-bottom: var(--spacing-md); color: var(--text-primary); font-size: var(--text-callout);">剥 謾ｯ謠ｴ譯医ｒ讀懃ｴ｢</h3>
            <form method="GET" class="plan-search-form">
                <input type="hidden" name="date" value="<?= htmlspecialchars($today) ?>">
                <input type="hidden" name="grade" value="<?= htmlspecialchars($gradeFilter) ?>">

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: var(--text-footnote);">繧ｿ繧ｰ</label>
                    <select name="plan_tag" class="form-control">
                        <option value="">縺吶∋縺ｦ</option>
                        <?php
                        $tags = ['繝励Ο繧ｰ繝ｩ繝溘Φ繧ｰ', '繝・く繧ｹ繧ｿ繧､繝ｫ', 'CAD', '蜍慕判', '繧､繝ｩ繧ｹ繝・, '莨∵･ｭ謾ｯ謠ｴ', '霎ｲ讌ｭ', '髻ｳ讌ｽ', '鬟・, '蟄ｦ鄙・, '閾ｪ蛻・叙謇ｱ隱ｬ譏取嶌', '蠢・炊', '險隱・, '謨呵ご', '繧､繝吶Φ繝・, '縺昴・莉・];
                        foreach ($tags as $tag):
                        ?>
                            <option value="<?= htmlspecialchars($tag) ?>" <?= $searchTag === $tag ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: var(--text-footnote);">譖懈律</label>
                    <select name="plan_day" class="form-control">
                        <option value="">縺吶∋縺ｦ</option>
                        <?php
                        $days = ['monday' => '譛域屆譌･', 'tuesday' => '轣ｫ譖懈律', 'wednesday' => '豌ｴ譖懈律', 'thursday' => '譛ｨ譖懈律', 'friday' => '驥第屆譌･', 'saturday' => '蝨滓屆譌･', 'sunday' => '譌･譖懈律'];
                        foreach ($days as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= $searchDayOfWeek === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">讀懃ｴ｢</button>
                <?php if (!empty($searchTag) || !empty($searchDayOfWeek)): ?>
                    <a href="?date=<?= htmlspecialchars($today) ?>&grade=<?= htmlspecialchars($gradeFilter) ?>" class="btn btn-secondary">繧ｯ繝ｪ繧｢</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- 謾ｯ謠ｴ譯磯∈謚・-->
        <div class="form-group">
            <label class="form-label">
                謾ｯ謠ｴ譯医ｒ驕ｸ謚・                <span style="font-size: var(--text-caption-1); color: var(--text-secondary); font-weight: normal;">(莉ｻ諢・</span>
                <a href="support_plan_form.php" style="font-size: var(--text-caption-1); margin-left: 10px;">統 縺薙・譌･縺ｮ謾ｯ謠ｴ譯医ｒ菴懈・</a>
            </label>
            <?php if (empty($supportPlans)): ?>
                <div class="plan-info-box">
                    庁 縺薙・譌･・・?= date('Y蟷ｴm譛・譌･', strtotime($today)) ?>・峨・謾ｯ謠ｴ譯医′縺ｾ縺菴懈・縺輔ｌ縺ｦ縺・∪縺帙ｓ縲・                    <a href="support_plan_form.php" style="color: var(--apple-blue); text-decoration: underline;">謾ｯ謠ｴ譯医ｒ菴懈・</a>縺励※縺九ｉ豢ｻ蜍輔ｒ霑ｽ蜉縺吶ｋ縺ｨ縲√ｈ繧雁柑邇・噪縺ｫ險倬鹸縺ｧ縺阪∪縺吶・                </div>
            <?php endif; ?>
            <select id="supportPlan" class="form-control">
                <option value="">謾ｯ謠ｴ譯医ｒ驕ｸ謚槭＠縺ｪ縺・ｼ域焔蜍募・蜉幢ｼ・/option>
                <?php foreach ($supportPlans as $plan): ?>
                    <option value="<?= $plan['id'] ?>"
                            data-activity-name="<?= htmlspecialchars($plan['activity_name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-purpose="<?= htmlspecialchars($plan['activity_purpose'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-content="<?= htmlspecialchars($plan['activity_content'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-domains="<?= htmlspecialchars($plan['five_domains_consideration'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-other="<?= htmlspecialchars($plan['other_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($plan['activity_name']) ?>
                        <span style="color: var(--text-secondary);">(菴懈・閠・ <?= htmlspecialchars($plan['staff_name']) ?>)</span>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 謾ｯ謠ｴ譯医・蜀・ｮｹ陦ｨ遉ｺ -->
        <div id="supportPlanDetails" class="plan-details-box">
            <h3 style="color: var(--apple-blue); font-size: var(--text-callout); margin-bottom: var(--spacing-md);">驕ｸ謚槭＠縺滓髪謠ｴ譯医・蜀・ｮｹ</h3>
            <div id="planPurpose"></div>
            <div id="planContent"></div>
            <div id="planDomains"></div>
            <div id="planOther"></div>
        </div>

        <div class="form-group">
            <label class="form-label">豢ｻ蜍募錐 <span style="color: var(--apple-red);">*</span></label>
            <input type="text" id="activityName" class="form-control" placeholder="萓・ 蜊亥燕縺ｮ豢ｻ蜍輔∝､門・豢ｻ蜍輔∝宛菴懈ｴｻ蜍輔↑縺ｩ" required>
        </div>

        <h3 style="margin-top: var(--spacing-lg); margin-bottom: var(--spacing-md); font-size: var(--text-headline); color: var(--text-primary);">蜿ょ刈閠・∈謚・/h3>
        <div class="student-selection">
            <?php
            $gradeLabelMap = [
                'elementary' => ['蟆・, 'badge-elementary'],
                'junior_high' => ['荳ｭ', 'badge-junior-high'],
                'high_school' => ['鬮・, 'badge-high-school']
            ];

            foreach ($allStudents as $student):
                $gradeInfo = $gradeLabelMap[$student['grade_level']] ?? ['?', ''];
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
        <button type="button" class="btn btn-success" id="addParticipantsBtn">蜿ょ刈閠・ｒ霑ｽ蜉</button>
    </div>
</div>

<div id="formArea" style="display: none;">
    <!-- 繝輔か繝ｼ繝縺ｯJavaScript縺ｧ蜍慕噪縺ｫ逕滓・ -->
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

// 謾ｯ謠ｴ譯磯∈謚樊凾縺ｮ蜃ｦ逅・supportPlanSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];

    if (this.value === '') {
        supportPlanDetails.style.display = 'none';
        activityNameInput.value = '';
        activityNameInput.readOnly = false;
        activityNameInput.style.backgroundColor = '';
        return;
    }

    // 謾ｯ謠ｴ譯医・蜀・ｮｹ繧定｡ｨ遉ｺ
    const activityName = selectedOption.dataset.activityName || '';
    const purpose = selectedOption.dataset.purpose || '';
    const content = selectedOption.dataset.content || '';
    const domains = selectedOption.dataset.domains || '';
    const other = selectedOption.dataset.other || '';

    // 豢ｻ蜍募錐繧定・蜍募・蜉・    activityNameInput.value = activityName;
    activityNameInput.readOnly = true;
    activityNameInput.style.backgroundColor = 'var(--apple-gray-6)';

    // 謾ｯ謠ｴ譯医・蜀・ｮｹ繧定｡ｨ遉ｺ
    document.getElementById('planPurpose').innerHTML = purpose ? '<div style="margin-bottom: 8px;"><strong style="color: var(--apple-blue);">豢ｻ蜍輔・逶ｮ逧・</strong><br>' + escapeHtml(purpose) + '</div>' : '';
    document.getElementById('planContent').innerHTML = content ? '<div style="margin-bottom: 8px;"><strong style="color: var(--apple-blue);">豢ｻ蜍輔・蜀・ｮｹ:</strong><br>' + escapeHtml(content) + '</div>' : '';
    document.getElementById('planDomains').innerHTML = domains ? '<div style="margin-bottom: 8px;"><strong style="color: var(--apple-blue);">莠秘伜沺縺ｸ縺ｮ驟肴・:</strong><br>' + escapeHtml(domains) + '</div>' : '';
    document.getElementById('planOther').innerHTML = other ? '<div><strong style="color: var(--apple-blue);">縺昴・莉・</strong><br>' + escapeHtml(other) + '</div>' : '';

    supportPlanDetails.style.display = 'block';
});

// HTML繧ｨ繧ｹ繧ｱ繝ｼ繝鈴未謨ｰ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\\n/g, '<br>');
}

addParticipantsBtn.addEventListener('click', function() {
    const activityName = activityNameInput.value.trim();
    const checkedBoxes = document.querySelectorAll('input[name="students[]"]:checked');

    if (activityName === '') {
        alert('豢ｻ蜍募錐繧貞・蜉帙＠縺ｦ縺上□縺輔＞');
        return;
    }

    if (checkedBoxes.length === 0) {
        alert('蜿ょ刈閠・ｒ驕ｸ謚槭＠縺ｦ縺上□縺輔＞');
        return;
    }

    // 谺｡縺ｮ繝壹・繧ｸ・医ヵ繧ｩ繝ｼ繝蜈･蜉幢ｼ峨∈驕ｷ遘ｻ
    const studentIds = Array.from(checkedBoxes).map(cb => cb.value);

    // 繝輔か繝ｼ繝蜈･蜉帙・繝ｼ繧ｸ縺ｸ繝・・繧ｿ繧呈ｸ｡縺励※驕ｷ遘ｻ
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'renrakucho_form.php';

    // 謾ｯ謠ｴ譯・D繧定ｿｽ蜉・磯∈謚槭＆繧後※縺・ｋ蝣ｴ蜷茨ｼ・    const supportPlanId = supportPlanSelect.value;
    if (supportPlanId) {
        const planInput = document.createElement('input');
        planInput.type = 'hidden';
        planInput.name = 'support_plan_id';
        planInput.value = supportPlanId;
        form.appendChild(planInput);
    }

    // 豢ｻ蜍募錐繧定ｿｽ蜉
    const activityInput = document.createElement('input');
    activityInput.type = 'hidden';
    activityInput.name = 'activity_name';
    activityInput.value = activityName;
    form.appendChild(activityInput);

    // 譌･莉倥ｒ霑ｽ蜉
    const dateInput = document.createElement('input');
    dateInput.type = 'hidden';
    dateInput.name = 'record_date';
    dateInput.value = '{$today}';
    form.appendChild(dateInput);

    // 蜿ょ刈閠・D繧定ｿｽ蜉
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
