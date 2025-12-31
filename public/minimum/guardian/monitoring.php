<?php
/**
 * 保護者用 モニタリング表閲覧ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();

// 保護者でない場合はリダイレクト
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// 保護者に紐づく生徒を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? AND is_active = 1 ORDER BY student_name");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);

// 選択された生徒のモニタリング一覧（提出済みのみ）
$monitorings = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT mr.*, isp.created_date as plan_created_date
        FROM monitoring_records mr
        INNER JOIN individual_support_plans isp ON mr.plan_id = isp.id
        WHERE mr.student_id = ? AND mr.is_draft = 0
        ORDER BY mr.monitoring_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $monitorings = $stmt->fetchAll();
}

// 選択されたモニタリングの詳細
$selectedMonitoringId = $_GET['monitoring_id'] ?? null;
$monitoringData = null;
$planData = null;
$monitoringDetails = [];

if ($selectedMonitoringId) {
    $stmt = $pdo->prepare("
        SELECT * FROM monitoring_records
        WHERE id = ? AND student_id = ? AND is_draft = 0
    ");
    $stmt->execute([$selectedMonitoringId, $selectedStudentId]);
    $monitoringData = $stmt->fetch();

    if ($monitoringData) {
        // 計画データを取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
        $stmt->execute([$monitoringData['plan_id']]);
        $planData = $stmt->fetch();

        // 計画明細を取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$monitoringData['plan_id']]);
        $planDetails = $stmt->fetchAll();

        // モニタリング明細を取得
        $stmt = $pdo->prepare("SELECT * FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$selectedMonitoringId]);
        $monitoringDetailsRaw = $stmt->fetchAll();

        // plan_detail_idをキーにした配列に変換
        foreach ($monitoringDetailsRaw as $detail) {
            $monitoringDetails[$detail['plan_detail_id']] = $detail;
        }
    }
}

// ページ開始
$currentPage = 'monitoring';
renderPageStart('guardian', $currentPage, 'モニタリング表', [
    'classroom' => $classroom
]);
?>

<style>
.monitoring-card {
    background: var(--md-bg-primary);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    cursor: pointer;
    transition: all var(--duration-fast);
}

.monitoring-card:hover {
    border-color: var(--md-green);
    box-shadow: var(--shadow-sm);
}

.monitoring-card.selected {
    border-color: var(--md-green);
    background: rgba(52, 199, 89, 0.05);
}

.monitoring-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.monitoring-card-title {
    font-size: var(--text-headline);
    font-weight: 600;
    color: var(--text-primary);
}

.monitoring-card-date {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
}

.section-title {
    font-size: var(--text-title-3);
    font-weight: 700;
    color: var(--md-green);
    margin: var(--spacing-xl) 0 var(--spacing-md);
    padding-bottom: var(--spacing-sm);
    border-bottom: 3px solid var(--md-green);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.info-item {
    background: var(--md-bg-secondary);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
}

.info-item label {
    display: block;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: var(--spacing-xs);
    font-size: var(--text-subhead);
}

.info-item .value {
    color: var(--text-primary);
    font-size: var(--text-body);
}

.achievement-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: var(--text-caption-1);
    font-weight: 600;
}

.achievement-achieved {
    background: rgba(52, 199, 89, 0.15);
    color: var(--md-green);
}

.achievement-progressing {
    background: rgba(255, 149, 0, 0.15);
    color: var(--md-orange);
}

.achievement-not-achieved {
    background: rgba(255, 59, 48, 0.15);
    color: var(--md-red);
}

.goal-section {
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
}

.goal-section.long-term {
    border-left: 4px solid var(--md-purple);
}

.goal-section.short-term {
    border-left: 4px solid var(--md-green);
}

.goal-section h4 {
    color: var(--md-purple);
    margin-bottom: var(--spacing-md);
    font-size: var(--text-callout);
}

.goal-section.short-term h4 {
    color: var(--md-green);
}

.goal-text {
    padding: var(--spacing-md);
    background: var(--md-bg-primary);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
    line-height: 1.6;
}

.goal-status {
    margin-bottom: var(--spacing-sm);
}

.goal-status-label {
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: var(--spacing-xs);
    font-size: var(--text-subhead);
}

.goal-status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    font-weight: 600;
    color: white;
}

.goal-status-badge.purple {
    background: var(--md-purple);
}

.goal-status-badge.green {
    background: var(--md-green);
}

.confirmation-section {
    background: var(--md-bg-secondary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    text-align: center;
}

.confirmation-section.confirmed {
    background: rgba(52, 199, 89, 0.1);
    border: 2px solid var(--md-green);
    display: flex;
    align-items: center;
    gap: var(--spacing-lg);
    text-align: left;
}

.confirmation-icon {
    width: 60px;
    height: 60px;
    background: var(--md-green);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: bold;
    flex-shrink: 0;
}

.confirmation-content {
    flex: 1;
}

.confirmation-title {
    font-size: var(--text-title-3);
    font-weight: bold;
    color: var(--md-green);
    margin-bottom: 4px;
}

.confirmation-date {
    font-size: var(--text-subhead);
    color: var(--md-green);
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }

    .monitoring-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-xs);
    }

    .confirmation-section.confirmed {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">モニタリング表</h1>
        <p class="page-subtitle">お子様の支援目標の達成状況</p>
    </div>
</div>

<!-- 生徒選択 -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <div class="form-group">
            <label class="form-label">お子様を選択</label>
            <select class="form-control" onchange="location.href='/minimum/guardian/monitoring.php?student_id=' + this.value">
                <option value="">-- 選択してください --</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= $student['id'] ?>" <?= $selectedStudentId == $student['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($student['student_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php if ($selectedStudentId): ?>
    <div class="section-title">提出済みのモニタリング表</div>

    <?php if (!empty($monitorings)): ?>
        <?php foreach ($monitorings as $monitoring): ?>
            <div class="monitoring-card <?= $selectedMonitoringId == $monitoring['id'] ? 'selected' : '' ?>"
                 onclick="location.href='/minimum/guardian/monitoring.php?student_id=<?= $selectedStudentId ?>&monitoring_id=<?= $monitoring['id'] ?>'">
                <div class="monitoring-card-header">
                    <div class="monitoring-card-title">
                        モニタリング（<?= date('Y年n月j日', strtotime($monitoring['monitoring_date'])) ?>実施）
                    </div>
                    <div class="monitoring-card-date">
                        対象計画: <?= date('Y年n月', strtotime($monitoring['plan_created_date'])) ?>作成
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- モニタリング詳細 -->
        <?php if ($monitoringData && $planData): ?>
            <div class="section-title">モニタリングの詳細</div>

            <!-- 基本情報 -->
            <div class="info-grid">
                <div class="info-item">
                    <label>お子様のお名前</label>
                    <div class="value"><?= htmlspecialchars($monitoringData['student_name']) ?></div>
                </div>
                <div class="info-item">
                    <label>実施日</label>
                    <div class="value"><?= date('Y年n月j日', strtotime($monitoringData['monitoring_date'])) ?></div>
                </div>
                <div class="info-item">
                    <label>対象計画書</label>
                    <div class="value"><?= date('Y年n月j日', strtotime($planData['created_date'])) ?>作成</div>
                </div>
            </div>

            <!-- 達成状況詳細 -->
            <?php if (!empty($planDetails)): ?>
                <div class="section-title">支援目標の達成状況</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>項目</th>
                                <th>支援目標</th>
                                <th>支援内容</th>
                                <th>達成時期</th>
                                <th>達成状況</th>
                                <th>コメント</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($planDetails as $detail): ?>
                                <?php $monitoring = $monitoringDetails[$detail['id']] ?? null; ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($detail['main_category'] ?: '') ?>
                                        <?php if ($detail['sub_category']): ?>
                                            <br><small style="color: var(--text-secondary);"><?= htmlspecialchars($detail['sub_category']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= nl2br(htmlspecialchars($detail['support_goal'] ?: '')) ?></td>
                                    <td><?= nl2br(htmlspecialchars($detail['support_content'] ?: '')) ?></td>
                                    <td>
                                        <?= $detail['achievement_date'] ? date('Y/m/d', strtotime($detail['achievement_date'])) : '' ?>
                                    </td>
                                    <td>
                                        <?php if ($monitoring && $monitoring['achievement_status']): ?>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($monitoring['achievement_status']) {
                                                case '達成':
                                                    $statusClass = 'achievement-achieved';
                                                    $statusText = '✓ 達成';
                                                    break;
                                                case '一部達成':
                                                    $statusClass = 'achievement-progressing';
                                                    $statusText = '△ 一部達成';
                                                    break;
                                                case '未達成':
                                                    $statusClass = 'achievement-not-achieved';
                                                    $statusText = '× 未達成';
                                                    break;
                                                default:
                                                    $statusClass = 'achievement-progressing';
                                                    $statusText = htmlspecialchars($monitoring['achievement_status']);
                                            }
                                            ?>
                                            <span class="achievement-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $monitoring ? nl2br(htmlspecialchars($monitoring['monitoring_comment'] ?: '')) : '' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- 短期目標・長期目標の達成状況 -->
            <?php if (!empty($monitoringData['short_term_goal_achievement']) || !empty($monitoringData['short_term_goal_comment']) ||
                      !empty($monitoringData['long_term_goal_achievement']) || !empty($monitoringData['long_term_goal_comment'])): ?>
                <div class="section-title">目標の達成状況</div>

                <!-- 長期目標 -->
                <?php if (!empty($monitoringData['long_term_goal_achievement']) || !empty($monitoringData['long_term_goal_comment'])): ?>
                    <div class="goal-section long-term">
                        <h4><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">target</span> 長期目標</h4>

                        <?php if (!empty($planData['long_term_goal_text'])): ?>
                            <div class="goal-text">
                                <?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($monitoringData['long_term_goal_achievement'])): ?>
                            <div class="goal-status">
                                <div class="goal-status-label">達成状況</div>
                                <span class="goal-status-badge purple">
                                    <?= htmlspecialchars($monitoringData['long_term_goal_achievement']) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($monitoringData['long_term_goal_comment'])): ?>
                            <div class="goal-status">
                                <div class="goal-status-label">コメント</div>
                                <div class="goal-text">
                                    <?= nl2br(htmlspecialchars($monitoringData['long_term_goal_comment'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 短期目標 -->
                <?php if (!empty($monitoringData['short_term_goal_achievement']) || !empty($monitoringData['short_term_goal_comment'])): ?>
                    <div class="goal-section short-term">
                        <h4><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">push_pin</span> 短期目標</h4>

                        <?php if (!empty($planData['short_term_goal_text'])): ?>
                            <div class="goal-text">
                                <?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($monitoringData['short_term_goal_achievement'])): ?>
                            <div class="goal-status">
                                <div class="goal-status-label">達成状況</div>
                                <span class="goal-status-badge green">
                                    <?= htmlspecialchars($monitoringData['short_term_goal_achievement']) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($monitoringData['short_term_goal_comment'])): ?>
                            <div class="goal-status">
                                <div class="goal-status-label">コメント</div>
                                <div class="goal-text">
                                    <?= nl2br(htmlspecialchars($monitoringData['short_term_goal_comment'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- 総合コメント -->
            <?php if ($monitoringData['overall_comment']): ?>
                <div class="section-title">総合コメント</div>
                <div class="info-item">
                    <div class="value"><?= nl2br(htmlspecialchars($monitoringData['overall_comment'])) ?></div>
                </div>
            <?php endif; ?>

            <!-- 保護者確認 -->
            <div class="section-title">保護者確認</div>
            <?php
            $guardianConfirmed = $monitoringData['guardian_confirmed'] ?? 0;
            $guardianConfirmedAt = $monitoringData['guardian_confirmed_at'] ?? null;
            ?>
            <?php if ($guardianConfirmed): ?>
                <div class="confirmation-section confirmed">
                    <div class="confirmation-icon">✓</div>
                    <div class="confirmation-content">
                        <div class="confirmation-title">確認済み</div>
                        <div class="confirmation-date">
                            確認日時: <?= date('Y年n月j日 H:i', strtotime($guardianConfirmedAt)) ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="confirmation-section">
                    <p style="margin-bottom: var(--spacing-lg); font-size: var(--text-callout);">
                        このモニタリング表の内容を確認しました。
                    </p>
                    <button onclick="confirmMonitoring(<?= $selectedMonitoringId ?>)" class="btn btn-success" id="confirmBtn">
                        ✓ 内容を確認しました
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: var(--spacing-2xl);">
                <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> 提出済みのモニタリング表はまだありません</h3>
                <p style="color: var(--text-secondary);">スタッフがモニタリング表を作成・提出すると、ここに表示されます。</p>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: var(--spacing-2xl);">
            <h3 style="color: var(--text-secondary);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> お子様を選択してください</h3>
        </div>
    </div>
<?php endif; ?>

<?php
$inlineJs = <<<'JS'
function confirmMonitoring(monitoringId) {
    if (!confirm('このモニタリング表の内容を確認しましたか？\n確認後は取り消せません。')) {
        return;
    }

    const btn = document.getElementById('confirmBtn');
    btn.disabled = true;
    btn.textContent = '処理中...';

    fetch('/minimum/guardian/monitoring_confirm.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ monitoring_id: monitoringId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('確認しました。ありがとうございます。');
            location.reload();
        } else {
            alert('エラー: ' + data.message);
            btn.disabled = false;
            btn.textContent = '✓ 内容を確認しました';
        }
    })
    .catch(error => {
        alert('エラーが発生しました: ' + error);
        btn.disabled = false;
        btn.textContent = '✓ 内容を確認しました';
    });
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
