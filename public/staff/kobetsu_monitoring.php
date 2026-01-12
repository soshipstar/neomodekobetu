<?php
/**
 * スタッフ用 モニタリング表作成ページ
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 削除処理（自分の教室の生徒のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_monitoring_id'])) {
    $deleteId = $_POST['delete_monitoring_id'];
    $studentId = $_POST['student_id'] ?? null;
    $planId = $_POST['plan_id'] ?? null;

    try {
        // 生徒が自分の教室に所属しているか確認
        if ($classroomId && $studentId) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND classroom_id = ?");
            $stmt->execute([$studentId, $classroomId]);
            if (!$stmt->fetch()) {
                throw new Exception('アクセス権限がありません。');
            }
        }

        // モニタリング明細も削除
        $stmt = $pdo->prepare("DELETE FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$deleteId]);

        // モニタリング本体を削除
        $stmt = $pdo->prepare("DELETE FROM monitoring_records WHERE id = ?");
        $stmt->execute([$deleteId]);

        $_SESSION['success'] = 'モニタリング表を削除しました。';

        if ($studentId && $planId) {
            header("Location: kobetsu_monitoring.php?student_id=$studentId&plan_id=$planId");
        } else {
            header("Location: kobetsu_monitoring.php");
        }
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = '削除に失敗しました: ' . $e->getMessage();
    }
}

// 自分の教室の生徒を取得
if ($classroomId) {
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

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedPlanId = $_GET['plan_id'] ?? null;
$selectedMonitoringId = $_GET['monitoring_id'] ?? null;
$selectedStudentSupportPlanStartType = 'current';

// 選択された生徒のsupport_plan_start_typeを取得
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT support_plan_start_type FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $studentTypeInfo = $stmt->fetch();
    $selectedStudentSupportPlanStartType = $studentTypeInfo['support_plan_start_type'] ?? 'current';
}

// 選択された生徒の個別支援計画一覧（モニタリング期限が今日から1ヶ月以内のもの、または既存モニタリングがあるもの）
// モニタリング期限 = 個別支援計画書のcreated_date + 5ヶ月
// つまり、created_date + 5ヶ月 <= 今日 + 1ヶ月 → created_date <= 今日 - 4ヶ月
$studentPlans = [];
if ($selectedStudentId && $selectedStudentSupportPlanStartType === 'current') {
    // 「次回の期間から作成する」設定の場合は、既存の計画も非表示
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

// 選択された計画の詳細
$planData = null;
$planDetails = [];
if ($selectedPlanId) {
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
    $stmt->execute([$selectedPlanId]);
    $planData = $stmt->fetch();

    if ($planData) {
        $selectedStudentId = $planData['student_id'];

        // 明細を取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// 既存のモニタリングデータを取得
$monitoringData = null;
$monitoringDetails = [];
if ($selectedMonitoringId) {
    $stmt = $pdo->prepare("SELECT * FROM monitoring_records WHERE id = ?");
    $stmt->execute([$selectedMonitoringId]);
    $monitoringData = $stmt->fetch();

    if ($monitoringData) {
        $selectedPlanId = $monitoringData['plan_id'];
        $selectedStudentId = $monitoringData['student_id'];

        // モニタリング明細を取得
        $stmt = $pdo->prepare("SELECT * FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$selectedMonitoringId]);
        $monitoringDetailsRaw = $stmt->fetchAll();

        // plan_detail_idをキーにした配列に変換
        foreach ($monitoringDetailsRaw as $detail) {
            $monitoringDetails[$detail['plan_detail_id']] = $detail;
        }

        // 計画データも取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
        $stmt->execute([$selectedPlanId]);
        $planData = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// 選択された計画の既存モニタリング一覧
$existingMonitorings = [];
if ($selectedPlanId) {
    $stmt = $pdo->prepare("SELECT * FROM monitoring_records WHERE plan_id = ? ORDER BY monitoring_date DESC");
    $stmt->execute([$selectedPlanId]);
    $existingMonitorings = $stmt->fetchAll();
}

// ページ開始
$currentPage = 'kobetsu_monitoring';
renderPageStart('staff', $currentPage, 'モニタリング表作成');
?>

<style>
.selection-area {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-2xl);
    padding: var(--spacing-lg);
    background: var(--md-gray-6);
    border-radius: var(--radius-md);
    flex-wrap: wrap;
}

.plan-info {
    background: rgba(0, 122, 255, 0.1);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-2xl);
    border-left: 4px solid var(--md-blue);
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
    color: var(--md-blue);
    font-size: var(--text-subhead);
}

.info-value {
    color: var(--text-primary);
    margin-top: 5px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--md-blue);
    margin: var(--spacing-2xl) 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--md-blue);
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
    background: var(--md-bg-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.monitoring-table th {
    background: var(--md-blue);
    color: white;
    padding: var(--spacing-md) 8px;
    text-align: left;
    font-size: var(--text-footnote);
    font-weight: 600;
    border: 1px solid var(--md-blue);
}

.monitoring-table td {
    padding: var(--spacing-md) 8px;
    border: 1px solid var(--md-gray-5);
    vertical-align: top;
    text-align: left;
}

.monitoring-table .plan-content {
    background: var(--md-gray-6);
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
    border: 1px solid var(--md-gray-5);
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
    color: var(--md-orange);
    transition: all var(--duration-normal) var(--ease-out);
}

.monitoring-item:hover,
.monitoring-item.active {
    background: var(--md-orange);
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
    background: var(--md-gray);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.generate-progress {
    background: var(--md-gray-6);
    padding: 15px 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--md-gray-5);
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
    background: var(--md-bg-secondary);
    color: var(--md-blue);
    border: 1px solid var(--md-blue);
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
    background: var(--md-blue);
    color: white;
}

.btn-ai-single:disabled {
    background: var(--md-gray);
    border-color: var(--md-gray);
    color: white;
    cursor: not-allowed;
}

.generating {
    border-color: var(--md-blue) !important;
    background: rgba(0,122,255,0.05) !important;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.generating-indicator { animation: pulse 1.5s infinite; }

.guardian-confirmed-badge {
    display: inline-block;
    background: var(--md-green);
    color: white;
    padding: 6px 15px;
    border-radius: var(--radius-xl);
    font-size: var(--text-footnote);
    font-weight: 600;
    margin-left: var(--spacing-md);
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

@media (max-width: 768px) {
    .selection-area { flex-direction: column; }
    .button-group { flex-direction: column; }
}

/* 電子署名スタイル */
.signature-section {
    background: var(--md-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-top: var(--spacing-md);
}

.signature-row {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.signature-item {
    flex: 1;
    min-width: 300px;
}

.signature-label {
    font-weight: 600;
    color: var(--md-blue);
    margin-bottom: var(--spacing-sm);
    font-size: var(--text-subhead);
}

.signature-container {
    border: 2px solid var(--md-gray-4);
    border-radius: var(--radius-sm);
    background: white;
    overflow: hidden;
}

.signature-canvas {
    display: block;
    width: 100%;
    height: 120px;
    cursor: crosshair;
    touch-action: none;
}

.signature-controls {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-sm);
    align-items: center;
}

.existing-signature {
    margin-top: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--md-gray-6);
    border-radius: var(--radius-sm);
}

.existing-signature p {
    margin: 0 0 var(--spacing-sm) 0;
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.signature-preview {
    max-width: 200px;
    max-height: 80px;
    border: 1px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">モニタリング表作成</h1>
        <p class="page-subtitle">
            支援目標の達成状況を評価
            <?php if ($monitoringData && ($monitoringData['guardian_confirmed'] ?? 0)): ?>
                <span class="guardian-confirmed-badge">
                    ✓ 保護者確認済み（<?= date('Y/m/d H:i', strtotime($monitoringData['guardian_confirmed_at'])) ?>）
                </span>
            <?php endif; ?>
        </p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理に戻る</a>
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

            <!-- 生徒・計画選択エリア -->
            <div class="selection-area">
                <div class="form-group">
                    <label>生徒を選択 *</label>
                    <select id="studentSelect" onchange="changeStudent()">
                        <option value="">-- 生徒を選択してください --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($studentPlans)): ?>
                    <div class="form-group">
                        <label>個別支援計画書を選択 *</label>
                        <select id="planSelect" onchange="changePlan()">
                            <option value="">-- 計画書を選択してください --</option>
                            <?php foreach ($studentPlans as $plan): ?>
                                <option value="<?= $plan['id'] ?>" <?= $plan['id'] == $selectedPlanId ? 'selected' : '' ?>>
                                    <?= date('Y年m月d日', strtotime($plan['created_date'])) ?> 作成
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selectedPlanId && $planData): ?>
                <!-- 既存のモニタリング一覧 -->
                <?php if (!empty($existingMonitorings)): ?>
                    <div class="monitoring-list">
                        <strong>既存のモニタリング:</strong>
                        <?php foreach ($existingMonitorings as $monitoring): ?>
                            <div style="display: inline-flex; align-items: center; gap: 5px;">
                                <a href="kobetsu_monitoring.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $selectedPlanId ?>&monitoring_id=<?= $monitoring['id'] ?>"
                                   class="monitoring-item <?= $monitoring['id'] == $selectedMonitoringId ? 'active' : '' ?>">
                                    <?= date('Y/m/d', strtotime($monitoring['monitoring_date'])) ?>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('このモニタリング表を削除してもよろしいですか？');">
                                    <input type="hidden" name="delete_monitoring_id" value="<?= $monitoring['id'] ?>">
                                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
                                    <button type="submit" style="background: var(--md-red); color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: var(--text-caption-1);"><span class="material-symbols-outlined">delete</span></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <a href="kobetsu_monitoring.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $selectedPlanId ?>" class="monitoring-item">+ 新規作成</a>
                    </div>
                <?php endif; ?>

                <!-- 計画情報表示 -->
                <div class="plan-info">
                    <h3 style="margin-bottom: 15px; color: #1976d2;">対象の個別支援計画書</h3>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">生徒氏名</div>
                            <div class="info-value"><?= htmlspecialchars($planData['student_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">作成年月日</div>
                            <div class="info-value"><?= date('Y年m月d日', strtotime($planData['created_date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">長期目標達成時期</div>
                            <div class="info-value"><?= $planData['long_term_goal_date'] ? date('Y年m月d日', strtotime($planData['long_term_goal_date'])) : '未設定' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">短期目標達成時期</div>
                            <div class="info-value"><?= $planData['short_term_goal_date'] ? date('Y年m月d日', strtotime($planData['short_term_goal_date'])) : '未設定' ?></div>
                        </div>
                    </div>
                </div>

                <!-- モニタリング入力フォーム -->
                <form method="POST" action="kobetsu_monitoring_save.php" id="monitoringForm">
                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    <input type="hidden" name="monitoring_id" value="<?= $selectedMonitoringId ?? '' ?>">

                    <!-- モニタリング実施日 -->
                    <div class="selection-area">
                        <div class="form-group">
                            <label>モニタリング実施日 *</label>
                            <input type="date" name="monitoring_date" value="<?= $monitoringData['monitoring_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- モニタリング表 -->
                    <div class="section-title">
                        支援目標の達成状況
                        <button type="button" class="btn-ai-generate" onclick="generateAllEvaluations()">
                            🤖 AIで評価を自動生成
                        </button>
                    </div>

                    <div id="generateProgress" class="generate-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div class="progress-text" id="progressText">生成中...</div>
                    </div>

                    <div class="table-wrapper">
                        <table class="monitoring-table">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">項目</th>
                                    <th style="width: 180px;">支援目標</th>
                                    <th style="width: 200px;">支援内容</th>
                                    <th style="width: 100px;">達成時期</th>
                                    <th style="width: 120px;">達成状況</th>
                                    <th style="width: 300px;">モニタリングコメント</th>
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
                                                <?= nl2br(htmlspecialchars($detail['support_goal'] ?: '（未設定）')) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= nl2br(htmlspecialchars($detail['support_content'] ?: '（未設定）')) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= $detail['achievement_date'] ? date('Y/m/d', strtotime($detail['achievement_date'])) : '（未設定）' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="hidden" name="details[<?= $detail['id'] ?>][plan_detail_id]" value="<?= $detail['id'] ?>">
                                            <select name="details[<?= $detail['id'] ?>][achievement_status]" id="status_<?= $detail['id'] ?>">
                                                <option value="">-- 選択 --</option>
                                                <option value="未着手" <?= ($monitoringDetail['achievement_status'] ?? '') == '未着手' ? 'selected' : '' ?>>未着手</option>
                                                <option value="進行中" <?= ($monitoringDetail['achievement_status'] ?? '') == '進行中' ? 'selected' : '' ?>>進行中</option>
                                                <option value="達成" <?= ($monitoringDetail['achievement_status'] ?? '') == '達成' ? 'selected' : '' ?>>達成</option>
                                                <option value="継続中" <?= ($monitoringDetail['achievement_status'] ?? '') == '継続中' ? 'selected' : '' ?>>継続中</option>
                                                <option value="見直し必要" <?= ($monitoringDetail['achievement_status'] ?? '') == '見直し必要' ? 'selected' : '' ?>>見直し必要</option>
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $detail['id'] ?>][monitoring_comment]" rows="3" id="comment_<?= $detail['id'] ?>"><?= htmlspecialchars($monitoringDetail['monitoring_comment'] ?? '') ?></textarea>
                                            <button type="button" class="btn-ai-single" onclick="generateSingleEvaluation(<?= $detail['id'] ?>)" id="btn_single_<?= $detail['id'] ?>">
                                                🤖 AI生成
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 短期目標・長期目標の振り返り -->
                    <div class="section-title" style="margin-top: var(--spacing-2xl);">目標の達成状況</div>

                    <!-- 長期目標 -->
                    <div style="margin-bottom: 25px; padding: var(--spacing-lg); background: var(--md-gray-6); border-radius: var(--radius-sm); border-left: 4px solid var(--primary-purple);">
                        <h4 style="color: var(--primary-purple); margin-bottom: 12px; font-size: var(--text-callout);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">target</span> 長期目標</h4>
                        <?php if (!empty($planData['long_term_goal_text'])): ?>
                            <div style="padding: var(--spacing-md); background: var(--md-bg-primary); border-radius: 6px; margin-bottom: 15px; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: var(--spacing-md); background: var(--md-bg-primary); border-radius: 6px; margin-bottom: 15px; color: var(--text-secondary); font-style: italic;">
                                長期目標が設定されていません
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">達成状況</label>
                            <select name="long_term_goal_achievement" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--md-gray-5); border-radius: 6px; font-size: var(--text-subhead);">
                                <option value="">-- 選択してください --</option>
                                <option value="未着手" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '未着手' ? 'selected' : '' ?>>未着手</option>
                                <option value="進行中" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '進行中' ? 'selected' : '' ?>>進行中</option>
                                <option value="達成" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '達成' ? 'selected' : '' ?>>達成</option>
                                <option value="継続中" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '継続中' ? 'selected' : '' ?>>継続中</option>
                                <option value="見直し必要" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '見直し必要' ? 'selected' : '' ?>>見直し必要</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">コメント</label>
                            <textarea name="long_term_goal_comment" rows="4" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--md-gray-5); border-radius: 6px; font-size: var(--text-subhead); font-family: inherit; resize: vertical;" placeholder="長期目標に対する振り返りや意見を記入してください"><?= htmlspecialchars($monitoringData['long_term_goal_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- 短期目標 -->
                    <div style="margin-bottom: 25px; padding: var(--spacing-lg); background: var(--md-gray-6); border-radius: var(--radius-sm); border-left: 4px solid var(--md-green);">
                        <h4 style="color: var(--md-green); margin-bottom: 12px; font-size: var(--text-callout);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">push_pin</span> 短期目標</h4>
                        <?php if (!empty($planData['short_term_goal_text'])): ?>
                            <div style="padding: var(--spacing-md); background: var(--md-bg-primary); border-radius: 6px; margin-bottom: 15px; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: var(--spacing-md); background: var(--md-bg-primary); border-radius: 6px; margin-bottom: 15px; color: var(--text-secondary); font-style: italic;">
                                短期目標が設定されていません
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">達成状況</label>
                            <select name="short_term_goal_achievement" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--md-gray-5); border-radius: 6px; font-size: var(--text-subhead);">
                                <option value="">-- 選択してください --</option>
                                <option value="未着手" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '未着手' ? 'selected' : '' ?>>未着手</option>
                                <option value="進行中" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '進行中' ? 'selected' : '' ?>>進行中</option>
                                <option value="達成" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '達成' ? 'selected' : '' ?>>達成</option>
                                <option value="継続中" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '継続中' ? 'selected' : '' ?>>継続中</option>
                                <option value="見直し必要" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '見直し必要' ? 'selected' : '' ?>>見直し必要</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">コメント</label>
                            <textarea name="short_term_goal_comment" rows="4" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--md-gray-5); border-radius: 6px; font-size: var(--text-subhead); font-family: inherit; resize: vertical;" placeholder="短期目標に対する振り返りや意見を記入してください"><?= htmlspecialchars($monitoringData['short_term_goal_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- 総合所見 -->
                    <div class="section-title">総合所見</div>
                    <div class="form-group">
                        <textarea name="overall_comment" rows="6"><?= htmlspecialchars($monitoringData['overall_comment'] ?? '') ?></textarea>
                    </div>

                    <!-- 電子署名 -->
                    <div class="section-title">電子署名</div>
                    <div class="signature-section">
                        <div class="signature-row">
                            <!-- 職員署名 -->
                            <div class="signature-item">
                                <div class="signature-label">職員署名</div>
                                <div class="signature-container">
                                    <canvas id="staffSignatureCanvas" class="signature-canvas"></canvas>
                                </div>
                                <input type="hidden" name="staff_signature_image" id="staffSignatureData" value="<?= htmlspecialchars($monitoringData['staff_signature_image'] ?? '') ?>">
                                <input type="hidden" name="staff_signer_name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>">
                                <div class="signature-controls">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearStaffSignature()">
                                        <span class="material-symbols-outlined">refresh</span> クリア
                                    </button>
                                    <div class="form-group" style="margin: 0; flex: 1;">
                                        <label style="font-size: var(--text-footnote);">署名日</label>
                                        <input type="date" name="staff_signature_date" class="form-control" style="padding: 6px;" value="<?= $monitoringData['staff_signature_date'] ?? date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <?php if (!empty($monitoringData['staff_signature_image'])): ?>
                                    <div class="existing-signature">
                                        <p>保存済みの署名（<?= htmlspecialchars($monitoringData['staff_signer_name'] ?? '') ?>）:</p>
                                        <img src="<?= htmlspecialchars($monitoringData['staff_signature_image']) ?>" alt="職員署名" class="signature-preview">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 保護者署名（表示のみ） -->
                            <div class="signature-item">
                                <div class="signature-label">保護者署名</div>
                                <?php if (!empty($monitoringData['guardian_signature_image'])): ?>
                                    <div class="existing-signature" style="margin-top: 0;">
                                        <p>保護者署名日: <?= $monitoringData['guardian_signature_date'] ? date('Y年m月d日', strtotime($monitoringData['guardian_signature_date'])) : '未署名' ?></p>
                                        <img src="<?= htmlspecialchars($monitoringData['guardian_signature_image']) ?>" alt="保護者署名" class="signature-preview">
                                    </div>
                                <?php else: ?>
                                    <div style="padding: var(--spacing-lg); background: var(--md-gray-6); border-radius: var(--radius-sm); text-align: center; color: var(--text-secondary);">
                                        <span class="material-symbols-outlined" style="font-size: 32px; opacity: 0.5;">draw</span>
                                        <p style="margin: var(--spacing-sm) 0 0 0;">保護者からの署名待ち</p>
                                        <p style="margin: 0; font-size: var(--text-caption);">モニタリング表提出後、保護者画面から署名できます</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ボタン -->
                    <div class="button-group">
                        <button type="submit" name="save_draft" class="btn btn-secondary"><span class="material-symbols-outlined">edit_note</span> 下書き保存（保護者非公開）</button>
                        <button type="submit" class="btn btn-success"><span class="material-symbols-outlined">check_circle</span> 作成・提出（保護者公開）</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    <?php if ($selectedStudentId && $selectedStudentSupportPlanStartType === 'next'): ?>
                        この生徒は「次回の期間から個別支援計画を作成する」設定になっています。<br>
                        現在は連絡帳のみ利用可能です。次回の期間が近づくと自動的に個別支援計画が作成されます。
                    <?php elseif ($selectedStudentId && empty($studentPlans)): ?>
                        この生徒にはまだモニタリング対象の個別支援計画書がありません。<br>
                        個別支援計画書を作成してから5ヶ月後にモニタリングが可能になります。
                    <?php else: ?>
                        生徒を選択し、個別支援計画書を選択してください。
                    <?php endif; ?>
                </div>
            <?php endif; ?>

<script>
// 署名パッドクラス
class SignaturePad {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext('2d');
        this.drawing = false;
        this.lastX = 0;
        this.lastY = 0;
        this.hasDrawn = false;

        this.init();
    }

    init() {
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = rect.width;
        this.canvas.height = rect.height;

        this.clear();
        this.bindEvents();
    }

    bindEvents() {
        this.canvas.addEventListener('mousedown', (e) => this.startDrawing(e));
        this.canvas.addEventListener('mousemove', (e) => this.draw(e));
        this.canvas.addEventListener('mouseup', () => this.stopDrawing());
        this.canvas.addEventListener('mouseleave', () => this.stopDrawing());

        this.canvas.addEventListener('touchstart', (e) => this.startDrawing(e));
        this.canvas.addEventListener('touchmove', (e) => this.draw(e));
        this.canvas.addEventListener('touchend', () => this.stopDrawing());
    }

    getCoordinates(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.canvas.width / rect.width;
        const scaleY = this.canvas.height / rect.height;

        if (e.touches) {
            return {
                x: (e.touches[0].clientX - rect.left) * scaleX,
                y: (e.touches[0].clientY - rect.top) * scaleY
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    startDrawing(e) {
        e.preventDefault();
        this.drawing = true;
        const coords = this.getCoordinates(e);
        this.lastX = coords.x;
        this.lastY = coords.y;
    }

    draw(e) {
        if (!this.drawing) return;
        e.preventDefault();

        const coords = this.getCoordinates(e);

        this.ctx.beginPath();
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 2;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.moveTo(this.lastX, this.lastY);
        this.ctx.lineTo(coords.x, coords.y);
        this.ctx.stroke();

        this.lastX = coords.x;
        this.lastY = coords.y;
        this.hasDrawn = true;
    }

    stopDrawing() {
        this.drawing = false;
    }

    clear() {
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.hasDrawn = false;
    }

    isEmpty() {
        return !this.hasDrawn;
    }

    toDataURL() {
        return this.canvas.toDataURL('image/png');
    }
}

let staffSignaturePad = null;

// 署名パッド初期化
document.addEventListener('DOMContentLoaded', function() {
    const staffCanvas = document.getElementById('staffSignatureCanvas');
    if (staffCanvas) {
        staffSignaturePad = new SignaturePad('staffSignatureCanvas');
    }

    // フォーム送信時に署名データを保存
    const monitoringForm = document.getElementById('monitoringForm');
    if (monitoringForm) {
        monitoringForm.addEventListener('submit', function(e) {
            if (staffSignaturePad && !staffSignaturePad.isEmpty()) {
                document.getElementById('staffSignatureData').value = staffSignaturePad.toDataURL();
            }
        });
    }
});

function clearStaffSignature() {
    if (staffSignaturePad) {
        staffSignaturePad.clear();
        document.getElementById('staffSignatureData').value = '';
    }
}

// ページ変数
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

        // 全ての評価を一括生成
        async function generateAllEvaluations() {
            if (!planId || !studentId) {
                alert('計画と生徒を選択してください');
                return;
            }

            if (!confirm('過去6ヶ月の連絡帳データを基に、AIで全ての目標の評価を自動生成します。\n既存の入力内容は上書きされます。続行しますか？')) {
                return;
            }

            const btn = document.querySelector('.btn-ai-generate');
            const progressDiv = document.getElementById('generateProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            // UIを生成中状態に
            btn.disabled = true;
            btn.textContent = '⏳ 生成中...';
            progressDiv.style.display = 'block';
            progressFill.style.width = '0%';
            progressText.textContent = '過去の連絡帳データを分析中...';

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
                progressText.textContent = 'AIが評価を生成中...';

                const data = await response.json();

                if (data.success) {
                    progressFill.style.width = '80%';
                    progressText.textContent = 'フォームに反映中...';

                    // 各目標に評価を反映
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
                    progressText.textContent = '✓ 生成完了！内容を確認し、必要に応じて編集してください。';

                    setTimeout(() => {
                        progressDiv.style.display = 'none';
                    }, 3000);
                } else {
                    throw new Error(data.error || '生成に失敗しました');
                }
            } catch (error) {
                console.error('Generation error:', error);
                alert('エラーが発生しました: ' + error.message);
                progressDiv.style.display = 'none';
            } finally {
                btn.disabled = false;
                btn.textContent = '🤖 AIで評価を自動生成';
            }
        }

        // 個別の評価を生成
        async function generateSingleEvaluation(detailId) {
            if (!planId || !studentId) {
                alert('計画と生徒を選択してください');
                return;
            }

            const btn = document.getElementById(`btn_single_${detailId}`);
            const statusSelect = document.getElementById(`status_${detailId}`);
            const commentTextarea = document.getElementById(`comment_${detailId}`);

            // UIを生成中状態に
            btn.disabled = true;
            btn.textContent = '⏳ 生成中...';
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

                    // 成功アニメーション
                    commentTextarea.style.borderColor = '#388E3C';
                    setTimeout(() => {
                        commentTextarea.style.borderColor = '';
                    }, 2000);
                } else {
                    throw new Error(data.error || '生成に失敗しました');
                }
            } catch (error) {
                console.error('Generation error:', error);
                alert('エラーが発生しました: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '🤖 AI生成';
                btn.classList.remove('generating-indicator');
                commentTextarea.classList.remove('generating');
            }
        }
</script>

<?php renderPageEnd(); ?>