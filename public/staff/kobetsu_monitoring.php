<?php
/**
 * 繧ｹ繧ｿ繝・ヵ逕ｨ 繝｢繝九ち繝ｪ繝ｳ繧ｰ陦ｨ菴懈・繝壹・繧ｸ
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 繧ｹ繧ｿ繝・ヵ縺ｾ縺溘・邂｡逅・・・縺ｿ繧｢繧ｯ繧ｻ繧ｹ蜿ｯ閭ｽ
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// 繧ｹ繧ｿ繝・ヵ縺ｮ謨吝ｮ､ID繧貞叙蠕・$classroomId = $_SESSION['classroom_id'] ?? null;

// 蜑企勁蜃ｦ逅・if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_monitoring_id'])) {
    $deleteId = $_POST['delete_monitoring_id'];

    try {
        // 繝｢繝九ち繝ｪ繝ｳ繧ｰ譏守ｴｰ繧ょ炎髯､
        $stmt = $pdo->prepare("DELETE FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$deleteId]);

        // 繝｢繝九ち繝ｪ繝ｳ繧ｰ譛ｬ菴薙ｒ蜑企勁
        $stmt = $pdo->prepare("DELETE FROM monitoring_records WHERE id = ?");
        $stmt->execute([$deleteId]);

        $_SESSION['success'] = '繝｢繝九ち繝ｪ繝ｳ繧ｰ陦ｨ繧貞炎髯､縺励∪縺励◆縲・;

        // 繝ｪ繝繧､繝ｬ繧ｯ繝亥・繧呈ｱｺ螳・        $studentId = $_POST['student_id'] ?? null;
        $planId = $_POST['plan_id'] ?? null;

        if ($studentId && $planId) {
            header("Location: kobetsu_monitoring.php?student_id=$studentId&plan_id=$planId");
        } else {
            header("Location: kobetsu_monitoring.php");
        }
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = '蜑企勁縺ｫ螟ｱ謨励＠縺ｾ縺励◆: ' . $e->getMessage();
    }
}

// 閾ｪ蛻・・謨吝ｮ､縺ｮ逕溷ｾ偵ｒ蜿門ｾ・if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 ORDER BY student_name");
}
$students = $stmt->fetchAll();

// 驕ｸ謚槭＆繧後◆逕溷ｾ・$selectedStudentId = $_GET['student_id'] ?? null;
$selectedPlanId = $_GET['plan_id'] ?? null;
$selectedMonitoringId = $_GET['monitoring_id'] ?? null;

// 驕ｸ謚槭＆繧後◆逕溷ｾ偵・蛟句挨謾ｯ謠ｴ險育判荳隕ｧ・医Δ繝九ち繝ｪ繝ｳ繧ｰ譛滄剞縺御ｻ頑律縺九ｉ1繝ｶ譛井ｻ･蜀・・繧ゅ・縲√∪縺溘・譌｢蟄倥Δ繝九ち繝ｪ繝ｳ繧ｰ縺後≠繧九ｂ縺ｮ・・// 繝｢繝九ち繝ｪ繝ｳ繧ｰ譛滄剞 = 蛟句挨謾ｯ謠ｴ險育判譖ｸ縺ｮcreated_date + 5繝ｶ譛・// 縺､縺ｾ繧翫…reated_date + 5繝ｶ譛・<= 莉頑律 + 1繝ｶ譛・竊・created_date <= 莉頑律 - 4繝ｶ譛・$studentPlans = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT isp.*
        FROM individual_support_plans isp
        LEFT JOIN monitoring_records mr ON isp.id = mr.plan_id
        WHERE isp.student_id = ?
        AND (
            DATE_ADD(isp.created_date, INTERVAL 5 MONTH) <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            OR mr.id IS NOT NULL
        )
        ORDER BY isp.created_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $studentPlans = $stmt->fetchAll();
}

// 驕ｸ謚槭＆繧後◆險育判縺ｮ隧ｳ邏ｰ
$planData = null;
$planDetails = [];
if ($selectedPlanId) {
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
    $stmt->execute([$selectedPlanId]);
    $planData = $stmt->fetch();

    if ($planData) {
        $selectedStudentId = $planData['student_id'];

        // 譏守ｴｰ繧貞叙蠕・        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// 譌｢蟄倥・繝｢繝九ち繝ｪ繝ｳ繧ｰ繝・・繧ｿ繧貞叙蠕・$monitoringData = null;
$monitoringDetails = [];
if ($selectedMonitoringId) {
    $stmt = $pdo->prepare("SELECT * FROM monitoring_records WHERE id = ?");
    $stmt->execute([$selectedMonitoringId]);
    $monitoringData = $stmt->fetch();

    if ($monitoringData) {
        $selectedPlanId = $monitoringData['plan_id'];
        $selectedStudentId = $monitoringData['student_id'];

        // 繝｢繝九ち繝ｪ繝ｳ繧ｰ譏守ｴｰ繧貞叙蠕・        $stmt = $pdo->prepare("SELECT * FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$selectedMonitoringId]);
        $monitoringDetailsRaw = $stmt->fetchAll();

        // plan_detail_id繧偵く繝ｼ縺ｫ縺励◆驟榊・縺ｫ螟画鋤
        foreach ($monitoringDetailsRaw as $detail) {
            $monitoringDetails[$detail['plan_detail_id']] = $detail;
        }

        // 險育判繝・・繧ｿ繧ょ叙蠕・        $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
        $stmt->execute([$selectedPlanId]);
        $planData = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// 驕ｸ謚槭＆繧後◆險育判縺ｮ譌｢蟄倥Δ繝九ち繝ｪ繝ｳ繧ｰ荳隕ｧ
$existingMonitorings = [];
if ($selectedPlanId) {
    $stmt = $pdo->prepare("SELECT * FROM monitoring_records WHERE plan_id = ? ORDER BY monitoring_date DESC");
    $stmt->execute([$selectedPlanId]);
    $existingMonitorings = $stmt->fetchAll();
}
// 繝壹・繧ｸ髢句ｧ・$currentPage = 'kobetsu_monitoring';
renderPageStart('staff', $currentPage, '繝｢繝九ち繝ｪ繝ｳ繧ｰ陦ｨ菴懈・');
?>

<style>
.selection-area {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-2xl);
    padding: var(--spacing-lg);
    background: var(--apple-gray-6);
    border-radius: var(--radius-md);
    flex-wrap: wrap;
}

.plan-info {
    background: rgba(0, 122, 255, 0.1);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-2xl);
    border-left: 4px solid var(--apple-blue);
}

.info-row {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
}

.info-item { flex: 1; min-width: 200px; }

.info-label {
    font-weight: 600;
    color: var(--apple-blue);
    font-size: var(--text-subhead);
}

.info-value {
    color: var(--text-primary);
    margin-top: 5px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--apple-blue);
    margin: var(--spacing-2xl) 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.table-wrapper {
    overflow-x: auto;
    margin-top: var(--spacing-lg);
}

.monitoring-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--apple-bg-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.monitoring-table th {
    background: var(--apple-blue);
    color: white;
    padding: var(--spacing-md) 8px;
    text-align: left;
    font-size: var(--text-footnote);
    font-weight: 600;
    border: 1px solid var(--apple-blue);
}

.monitoring-table td {
    padding: var(--spacing-md) 8px;
    border: 1px solid var(--apple-gray-5);
    vertical-align: top;
    text-align: left;
}

.monitoring-table .plan-content {
    background: var(--apple-gray-6);
    padding: var(--spacing-sm);
    border-radius: 4px;
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    white-space: normal;
    text-align: left;
}

.monitoring-table input,
.monitoring-table textarea,
.monitoring-table select {
    width: 100%;
    padding: var(--spacing-sm);
    border: 1px solid var(--apple-gray-5);
    border-radius: 4px;
    font-size: var(--text-subhead);
    font-family: inherit;
}

.monitoring-table textarea { min-height: 80px; resize: vertical; }
.monitoring-table select { padding: 6px 8px; }

.button-group {
    display: flex;
    gap: 15px;
    margin-top: var(--spacing-2xl);
    justify-content: flex-end;
    flex-wrap: wrap;
}

.monitoring-list { margin-bottom: var(--spacing-lg); }

.monitoring-item {
    display: inline-block;
    padding: var(--spacing-sm) 15px;
    margin: 5px;
    background: rgba(255,149,0,0.15);
    border-radius: 6px;
    text-decoration: none;
    color: var(--apple-orange);
    transition: all var(--duration-normal) var(--ease-out);
}

.monitoring-item:hover,
.monitoring-item.active {
    background: var(--apple-orange);
    color: white;
}

.btn-ai-generate {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: var(--radius-md);
    font-size: var(--text-subhead);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
}

.btn-ai-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-ai-generate:disabled {
    background: var(--apple-gray);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.generate-progress {
    background: var(--apple-gray-6);
    padding: 15px 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--apple-gray-5);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 4px;
    transition: width 0.3s ease;
    width: 0%;
}

.progress-text {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    text-align: center;
}

.btn-ai-single {
    background: var(--apple-bg-secondary);
    color: var(--apple-blue);
    border: 1px solid var(--apple-blue);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: var(--text-caption-1);
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 5px;
    display: block;
    width: 100%;
}

.btn-ai-single:hover {
    background: var(--apple-blue);
    color: white;
}

.btn-ai-single:disabled {
    background: var(--apple-gray);
    border-color: var(--apple-gray);
    color: white;
    cursor: not-allowed;
}

.generating {
    border-color: var(--apple-blue) !important;
    background: rgba(0,122,255,0.05) !important;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.generating-indicator { animation: pulse 1.5s infinite; }

.guardian-confirmed-badge {
    display: inline-block;
    background: var(--apple-green);
    color: white;
    padding: 6px 15px;
    border-radius: var(--radius-xl);
    font-size: var(--text-footnote);
    font-weight: 600;
    margin-left: var(--spacing-md);
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
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--apple-gray-5); }

@media (max-width: 768px) {
    .selection-area { flex-direction: column; }
    .button-group { flex-direction: column; }
}
</style>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">繝｢繝九ち繝ｪ繝ｳ繧ｰ陦ｨ菴懈・</h1>
        <p class="page-subtitle">
            謾ｯ謠ｴ逶ｮ讓吶・驕疲・迥ｶ豕√ｒ隧穂ｾ｡
            <?php if ($monitoringData && ($monitoringData['guardian_confirmed'] ?? 0)): ?>
                <span class="guardian-confirmed-badge">
                    笨・菫晁ｭｷ閠・｢ｺ隱肴ｸ医∩・・?= date('Y/m/d H:i', strtotime($monitoringData['guardian_confirmed_at'])) ?>・・                </span>
            <?php endif; ?>
        </p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">竊・豢ｻ蜍慕ｮ｡逅・∈謌ｻ繧・/a>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- 逕溷ｾ偵・險育判驕ｸ謚槭お繝ｪ繧｢ -->
            <div class="selection-area">
                <div class="form-group">
                    <label>逕溷ｾ偵ｒ驕ｸ謚・*</label>
                    <select id="studentSelect" onchange="changeStudent()">
                        <option value="">-- 逕溷ｾ偵ｒ驕ｸ謚槭＠縺ｦ縺上□縺輔＞ --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($studentPlans)): ?>
                    <div class="form-group">
                        <label>蛟句挨謾ｯ謠ｴ險育判譖ｸ繧帝∈謚・*</label>
                        <select id="planSelect" onchange="changePlan()">
                            <option value="">-- 險育判譖ｸ繧帝∈謚槭＠縺ｦ縺上□縺輔＞ --</option>
                            <?php foreach ($studentPlans as $plan): ?>
                                <option value="<?= $plan['id'] ?>" <?= $plan['id'] == $selectedPlanId ? 'selected' : '' ?>>
                                    <?= date('Y蟷ｴm譛・譌･', strtotime($plan['created_date'])) ?> 菴懈・
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selectedPlanId && $planData): ?>
                <!-- 譌｢蟄倥・繝｢繝九ち繝ｪ繝ｳ繧ｰ荳隕ｧ -->
                <?php if (!empty($existingMonitorings)): ?>
                    <div class="monitoring-list">
                        <strong>譌｢蟄倥・繝｢繝九ち繝ｪ繝ｳ繧ｰ:</strong>
                        <?php foreach ($existingMonitorings as $monitoring): ?>
                            <div style="display: inline-flex; align-items: center; gap: 5px;">
                                <a href="kobetsu_monitoring.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $selectedPlanId ?>&monitoring_id=<?= $monitoring['id'] ?>"
                                   class="monitoring-item <?= $monitoring['id'] == $selectedMonitoringId ? 'active' : '' ?>">
                                    <?= date('Y/m/d', strtotime($monitoring['monitoring_date'])) ?>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('縺薙・繝｢繝九ち繝ｪ繝ｳ繧ｰ陦ｨ繧貞炎髯､縺励※繧ゅｈ繧阪＠縺・〒縺吶°・・);">
                                    <input type="hidden" name="delete_monitoring_id" value="<?= $monitoring['id'] ?>">
                                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
                                    <button type="submit" style="background: var(--apple-red); color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: var(--text-caption-1);">卵・・/button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <a href="kobetsu_monitoring.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $selectedPlanId ?>" class="monitoring-item">+ 譁ｰ隕丈ｽ懈・</a>
                    </div>
                <?php endif; ?>

                <!-- 險育判諠・ｱ陦ｨ遉ｺ -->
                <div class="plan-info">
                    <h3 style="margin-bottom: 15px; color: #1976d2;">蟇ｾ雎｡縺ｮ蛟句挨謾ｯ謠ｴ險育判譖ｸ</h3>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">逕溷ｾ呈ｰ丞錐</div>
                            <div class="info-value"><?= htmlspecialchars($planData['student_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">菴懈・蟷ｴ譛域律</div>
                            <div class="info-value"><?= date('Y蟷ｴm譛・譌･', strtotime($planData['created_date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">髟ｷ譛溽岼讓咎＃謌先凾譛・/div>
                            <div class="info-value"><?= $planData['long_term_goal_date'] ? date('Y蟷ｴm譛・譌･', strtotime($planData['long_term_goal_date'])) : '譛ｪ險ｭ螳・ ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">遏ｭ譛溽岼讓咎＃謌先凾譛・/div>
                            <div class="info-value"><?= $planData['short_term_goal_date'] ? date('Y蟷ｴm譛・譌･', strtotime($planData['short_term_goal_date'])) : '譛ｪ險ｭ螳・ ?></div>
                        </div>
                    </div>
                </div>

                <!-- 繝｢繝九ち繝ｪ繝ｳ繧ｰ蜈･蜉帙ヵ繧ｩ繝ｼ繝 -->
                <form method="POST" action="kobetsu_monitoring_save.php" id="monitoringForm">
                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    <input type="hidden" name="monitoring_id" value="<?= $selectedMonitoringId ?? '' ?>">

                    <!-- 繝｢繝九ち繝ｪ繝ｳ繧ｰ螳滓命譌･ -->
                    <div class="selection-area">
                        <div class="form-group">
                            <label>繝｢繝九ち繝ｪ繝ｳ繧ｰ螳滓命譌･ *</label>
                            <input type="date" name="monitoring_date" value="<?= $monitoringData['monitoring_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- 繝｢繝九ち繝ｪ繝ｳ繧ｰ陦ｨ -->
                    <div class="section-title">
                        謾ｯ謠ｴ逶ｮ讓吶・驕疲・迥ｶ豕・                        <button type="button" class="btn-ai-generate" onclick="generateAllEvaluations()">
                            ､・AI縺ｧ隧穂ｾ｡繧定・蜍慕函謌・                        </button>
                    </div>

                    <div id="generateProgress" class="generate-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div class="progress-text" id="progressText">逕滓・荳ｭ...</div>
                    </div>

                    <div class="table-wrapper">
                        <table class="monitoring-table">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">鬆・岼</th>
                                    <th style="width: 180px;">謾ｯ謠ｴ逶ｮ讓・/th>
                                    <th style="width: 200px;">謾ｯ謠ｴ蜀・ｮｹ</th>
                                    <th style="width: 100px;">驕疲・譎よ悄</th>
                                    <th style="width: 120px;">驕疲・迥ｶ豕・/th>
                                    <th style="width: 300px;">繝｢繝九ち繝ｪ繝ｳ繧ｰ繧ｳ繝｡繝ｳ繝・/th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planDetails as $detail): ?>
                                    <?php
                                    $monitoringDetail = $monitoringDetails[$detail['id']] ?? null;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="plan-content">
                                                <?= htmlspecialchars($detail['category']) ?>
                                                <?php if ($detail['sub_category']): ?>
                                                    <br><?= htmlspecialchars($detail['sub_category']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= nl2br(htmlspecialchars($detail['support_goal'] ?: '・域悴險ｭ螳夲ｼ・)) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= nl2br(htmlspecialchars($detail['support_content'] ?: '・域悴險ｭ螳夲ｼ・)) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= $detail['achievement_date'] ? date('Y/m/d', strtotime($detail['achievement_date'])) : '・域悴險ｭ螳夲ｼ・ ?>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="hidden" name="details[<?= $detail['id'] ?>][plan_detail_id]" value="<?= $detail['id'] ?>">
                                            <select name="details[<?= $detail['id'] ?>][achievement_status]" id="status_<?= $detail['id'] ?>">
                                                <option value="">-- 驕ｸ謚・--</option>
                                                <option value="譛ｪ逹謇・ <?= ($monitoringDetail['achievement_status'] ?? '') == '譛ｪ逹謇・ ? 'selected' : '' ?>>譛ｪ逹謇・/option>
                                                <option value="騾ｲ陦御ｸｭ" <?= ($monitoringDetail['achievement_status'] ?? '') == '騾ｲ陦御ｸｭ' ? 'selected' : '' ?>>騾ｲ陦御ｸｭ</option>
                                                <option value="驕疲・" <?= ($monitoringDetail['achievement_status'] ?? '') == '驕疲・' ? 'selected' : '' ?>>驕疲・</option>
                                                <option value="邯咏ｶ壻ｸｭ" <?= ($monitoringDetail['achievement_status'] ?? '') == '邯咏ｶ壻ｸｭ' ? 'selected' : '' ?>>邯咏ｶ壻ｸｭ</option>
                                                <option value="隕狗峩縺怜ｿ・ｦ・ <?= ($monitoringDetail['achievement_status'] ?? '') == '隕狗峩縺怜ｿ・ｦ・ ? 'selected' : '' ?>>隕狗峩縺怜ｿ・ｦ・/option>
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $detail['id'] ?>][monitoring_comment]" rows="3" id="comment_<?= $detail['id'] ?>"><?= htmlspecialchars($monitoringDetail['monitoring_comment'] ?? '') ?></textarea>
                                            <button type="button" class="btn-ai-single" onclick="generateSingleEvaluation(<?= $detail['id'] ?>)" id="btn_single_<?= $detail['id'] ?>">
                                                ､・AI逕滓・
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 遏ｭ譛溽岼讓吶・髟ｷ譛溽岼讓吶・謖ｯ繧願ｿ斐ｊ -->
                    <div class="section-title" style="margin-top: var(--spacing-2xl);">逶ｮ讓吶・驕疲・迥ｶ豕・/div>

                    <!-- 髟ｷ譛溽岼讓・-->
                    <div style="margin-bottom: 25px; padding: var(--spacing-lg); background: var(--apple-gray-6); border-radius: var(--radius-sm); border-left: 4px solid var(--primary-purple);">
                        <h4 style="color: var(--primary-purple); margin-bottom: 12px; font-size: var(--text-callout);">識 髟ｷ譛溽岼讓・/h4>
                        <?php if (!empty($planData['long_term_goal_text'])): ?>
                            <div style="padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: 6px; margin-bottom: 15px; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: 6px; margin-bottom: 15px; color: var(--text-secondary); font-style: italic;">
                                髟ｷ譛溽岼讓吶′險ｭ螳壹＆繧後※縺・∪縺帙ｓ
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">驕疲・迥ｶ豕・/label>
                            <select name="long_term_goal_achievement" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--apple-gray-5); border-radius: 6px; font-size: var(--text-subhead);">
                                <option value="">-- 驕ｸ謚槭＠縺ｦ縺上□縺輔＞ --</option>
                                <option value="譛ｪ逹謇・ <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '譛ｪ逹謇・ ? 'selected' : '' ?>>譛ｪ逹謇・/option>
                                <option value="騾ｲ陦御ｸｭ" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '騾ｲ陦御ｸｭ' ? 'selected' : '' ?>>騾ｲ陦御ｸｭ</option>
                                <option value="驕疲・" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '驕疲・' ? 'selected' : '' ?>>驕疲・</option>
                                <option value="邯咏ｶ壻ｸｭ" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '邯咏ｶ壻ｸｭ' ? 'selected' : '' ?>>邯咏ｶ壻ｸｭ</option>
                                <option value="隕狗峩縺怜ｿ・ｦ・ <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '隕狗峩縺怜ｿ・ｦ・ ? 'selected' : '' ?>>隕狗峩縺怜ｿ・ｦ・/option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">繧ｳ繝｡繝ｳ繝・/label>
                            <textarea name="long_term_goal_comment" rows="4" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--apple-gray-5); border-radius: 6px; font-size: var(--text-subhead); font-family: inherit; resize: vertical;" placeholder="髟ｷ譛溽岼讓吶↓蟇ｾ縺吶ｋ謖ｯ繧願ｿ斐ｊ繧・園隕九ｒ險伜・縺励※縺上□縺輔＞"><?= htmlspecialchars($monitoringData['long_term_goal_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- 遏ｭ譛溽岼讓・-->
                    <div style="margin-bottom: 25px; padding: var(--spacing-lg); background: var(--apple-gray-6); border-radius: var(--radius-sm); border-left: 4px solid var(--apple-green);">
                        <h4 style="color: var(--apple-green); margin-bottom: 12px; font-size: var(--text-callout);">東 遏ｭ譛溽岼讓・/h4>
                        <?php if (!empty($planData['short_term_goal_text'])): ?>
                            <div style="padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: 6px; margin-bottom: 15px; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: var(--spacing-md); background: var(--apple-bg-primary); border-radius: 6px; margin-bottom: 15px; color: var(--text-secondary); font-style: italic;">
                                遏ｭ譛溽岼讓吶′險ｭ螳壹＆繧後※縺・∪縺帙ｓ
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">驕疲・迥ｶ豕・/label>
                            <select name="short_term_goal_achievement" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--apple-gray-5); border-radius: 6px; font-size: var(--text-subhead);">
                                <option value="">-- 驕ｸ謚槭＠縺ｦ縺上□縺輔＞ --</option>
                                <option value="譛ｪ逹謇・ <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '譛ｪ逹謇・ ? 'selected' : '' ?>>譛ｪ逹謇・/option>
                                <option value="騾ｲ陦御ｸｭ" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '騾ｲ陦御ｸｭ' ? 'selected' : '' ?>>騾ｲ陦御ｸｭ</option>
                                <option value="驕疲・" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '驕疲・' ? 'selected' : '' ?>>驕疲・</option>
                                <option value="邯咏ｶ壻ｸｭ" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '邯咏ｶ壻ｸｭ' ? 'selected' : '' ?>>邯咏ｶ壻ｸｭ</option>
                                <option value="隕狗峩縺怜ｿ・ｦ・ <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '隕狗峩縺怜ｿ・ｦ・ ? 'selected' : '' ?>>隕狗峩縺怜ｿ・ｦ・/option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">繧ｳ繝｡繝ｳ繝・/label>
                            <textarea name="short_term_goal_comment" rows="4" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--apple-gray-5); border-radius: 6px; font-size: var(--text-subhead); font-family: inherit; resize: vertical;" placeholder="遏ｭ譛溽岼讓吶↓蟇ｾ縺吶ｋ謖ｯ繧願ｿ斐ｊ繧・園隕九ｒ險伜・縺励※縺上□縺輔＞"><?= htmlspecialchars($monitoringData['short_term_goal_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- 邱丞粋謇隕・-->
                    <div class="section-title">邱丞粋謇隕・/div>
                    <div class="form-group">
                        <textarea name="overall_comment" rows="6"><?= htmlspecialchars($monitoringData['overall_comment'] ?? '') ?></textarea>
                    </div>

                    <!-- 繝懊ち繝ｳ -->
                    <div class="button-group">
                        <button type="submit" name="save_draft" class="btn btn-secondary">統 荳区嶌縺堺ｿ晏ｭ假ｼ井ｿ晁ｭｷ閠・撼蜈ｬ髢具ｼ・/button>
                        <button type="submit" class="btn btn-success">笨・菴懈・繝ｻ謠仙・・井ｿ晁ｭｷ閠・↓蜈ｬ髢具ｼ・/button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    逕溷ｾ偵ｒ驕ｸ謚槭＠縲∝句挨謾ｯ謠ｴ險育判譖ｸ繧帝∈謚槭＠縺ｦ縺上□縺輔＞縲・                </div>
            <?php endif; ?>

<script>
// 繝壹・繧ｸ螟画焚
        const planId = <?= json_encode($selectedPlanId) ?>;
        const studentId = <?= json_encode($selectedStudentId) ?>;
        const detailIds = <?= json_encode(array_column($planDetails, 'id')) ?>;

        function changeStudent() {
            const studentId = document.getElementById('studentSelect').value;
            if (studentId) {
                window.location.href = `kobetsu_monitoring.php?student_id=${studentId}`;
            }
        }

        function changePlan() {
            const studentId = document.getElementById('studentSelect').value;
            const planId = document.getElementById('planSelect').value;
            if (studentId && planId) {
                window.location.href = `kobetsu_monitoring.php?student_id=${studentId}&plan_id=${planId}`;
            }
        }

        // 蜈ｨ縺ｦ縺ｮ隧穂ｾ｡繧剃ｸ諡ｬ逕滓・
        async function generateAllEvaluations() {
            if (!planId || !studentId) {
                alert('險育判縺ｨ逕溷ｾ偵ｒ驕ｸ謚槭＠縺ｦ縺上□縺輔＞');
                return;
            }

            if (!confirm('驕主悉6繝ｶ譛医・騾｣邨｡蟶ｳ繝・・繧ｿ繧貞渕縺ｫ縲、I縺ｧ蜈ｨ縺ｦ縺ｮ逶ｮ讓吶・隧穂ｾ｡繧定・蜍慕函謌舌＠縺ｾ縺吶・n譌｢蟄倥・蜈･蜉帛・螳ｹ縺ｯ荳頑嶌縺阪＆繧後∪縺吶らｶ夊｡後＠縺ｾ縺吶°・・)) {
                return;
            }

            const btn = document.querySelector('.btn-ai-generate');
            const progressDiv = document.getElementById('generateProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            // UI繧堤函謌蝉ｸｭ迥ｶ諷九↓
            btn.disabled = true;
            btn.textContent = '竢ｳ 逕滓・荳ｭ...';
            progressDiv.style.display = 'block';
            progressFill.style.width = '0%';
            progressText.textContent = '驕主悉縺ｮ騾｣邨｡蟶ｳ繝・・繧ｿ繧貞・譫蝉ｸｭ...';

            try {
                const response = await fetch('monitoring_generate_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        student_id: studentId
                    })
                });

                progressFill.style.width = '50%';
                progressText.textContent = 'AI縺瑚ｩ穂ｾ｡繧堤函謌蝉ｸｭ...';

                const data = await response.json();

                if (data.success) {
                    progressFill.style.width = '80%';
                    progressText.textContent = '繝輔か繝ｼ繝縺ｫ蜿肴丐荳ｭ...';

                    // 蜷・岼讓吶↓隧穂ｾ｡繧貞渚譏
                    const evaluations = data.data;
                    for (const [detailId, evaluation] of Object.entries(evaluations)) {
                        const statusSelect = document.getElementById(`status_${detailId}`);
                        const commentTextarea = document.getElementById(`comment_${detailId}`);

                        if (statusSelect && evaluation.achievement_status) {
                            statusSelect.value = evaluation.achievement_status;
                        }
                        if (commentTextarea && evaluation.monitoring_comment) {
                            commentTextarea.value = evaluation.monitoring_comment;
                        }
                    }

                    progressFill.style.width = '100%';
                    progressText.textContent = '笨・逕滓・螳御ｺ・ｼ∝・螳ｹ繧堤｢ｺ隱阪＠縲∝ｿ・ｦ√↓蠢懊§縺ｦ邱ｨ髮・＠縺ｦ縺上□縺輔＞縲・;

                    setTimeout(() => {
                        progressDiv.style.display = 'none';
                    }, 3000);
                } else {
                    throw new Error(data.error || '逕滓・縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
                }
            } catch (error) {
                console.error('Generation error:', error);
                alert('繧ｨ繝ｩ繝ｼ縺檎匱逕溘＠縺ｾ縺励◆: ' + error.message);
                progressDiv.style.display = 'none';
            } finally {
                btn.disabled = false;
                btn.textContent = '､・AI縺ｧ隧穂ｾ｡繧定・蜍慕函謌・;
            }
        }

        // 蛟句挨縺ｮ隧穂ｾ｡繧堤函謌・        async function generateSingleEvaluation(detailId) {
            if (!planId || !studentId) {
                alert('險育判縺ｨ逕溷ｾ偵ｒ驕ｸ謚槭＠縺ｦ縺上□縺輔＞');
                return;
            }

            const btn = document.getElementById(`btn_single_${detailId}`);
            const statusSelect = document.getElementById(`status_${detailId}`);
            const commentTextarea = document.getElementById(`comment_${detailId}`);

            // UI繧堤函謌蝉ｸｭ迥ｶ諷九↓
            btn.disabled = true;
            btn.textContent = '竢ｳ 逕滓・荳ｭ...';
            btn.classList.add('generating-indicator');
            commentTextarea.classList.add('generating');

            try {
                const response = await fetch('monitoring_generate_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        student_id: studentId,
                        detail_id: detailId
                    })
                });

                const data = await response.json();

                if (data.success && data.data[detailId]) {
                    const evaluation = data.data[detailId];

                    if (evaluation.achievement_status) {
                        statusSelect.value = evaluation.achievement_status;
                    }
                    if (evaluation.monitoring_comment) {
                        commentTextarea.value = evaluation.monitoring_comment;
                    }

                    // 謌仙粥繧｢繝九Γ繝ｼ繧ｷ繝ｧ繝ｳ
                    commentTextarea.style.borderColor = '#28a745';
                    setTimeout(() => {
                        commentTextarea.style.borderColor = '';
                    }, 2000);
                } else {
                    throw new Error(data.error || '逕滓・縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
                }
            } catch (error) {
                console.error('Generation error:', error);
                alert('繧ｨ繝ｩ繝ｼ縺檎匱逕溘＠縺ｾ縺励◆: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '､・AI逕滓・';
                btn.classList.remove('generating-indicator');
                commentTextarea.classList.remove('generating');
            }
        }
</script>

<?php renderPageEnd(); ?>
