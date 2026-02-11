<?php
/**
 * 事業所評価回答一覧ページ
 * 誰が回答したか、誰が未回答かを確認
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

$periodId = $_GET['period_id'] ?? null;
if (!$periodId) {
    header('Location: facility_evaluation.php');
    exit;
}

// 評価期間情報を取得
$stmt = $pdo->prepare("SELECT * FROM facility_evaluation_periods WHERE id = ?");
$stmt->execute([$periodId]);
$period = $stmt->fetch();

if (!$period) {
    header('Location: facility_evaluation.php');
    exit;
}

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 保護者一覧と回答状況を取得
$guardianSql = "
    SELECT u.id, u.display_name, u.email,
           fge.id as evaluation_id, fge.is_submitted, fge.submitted_at, fge.created_at as started_at
    FROM users u
    LEFT JOIN facility_guardian_evaluations fge ON u.id = fge.guardian_id AND fge.period_id = ?
    WHERE u.user_type = 'guardian' AND u.is_active = 1
";
if ($classroomId) {
    $guardianSql .= " AND u.classroom_id = ?";
    $stmt = $pdo->prepare($guardianSql . " ORDER BY fge.is_submitted DESC, u.display_name");
    $stmt->execute([$periodId, $classroomId]);
} else {
    $stmt = $pdo->prepare($guardianSql . " ORDER BY fge.is_submitted DESC, u.display_name");
    $stmt->execute([$periodId]);
}
$guardians = $stmt->fetchAll();

// スタッフ一覧と回答状況を取得
$staffSql = "
    SELECT u.id, u.display_name, u.email,
           fse.id as evaluation_id, fse.is_submitted, fse.submitted_at, fse.created_at as started_at
    FROM users u
    LEFT JOIN facility_staff_evaluations fse ON u.id = fse.staff_id AND fse.period_id = ?
    WHERE u.user_type IN ('staff', 'admin') AND u.is_active = 1
";
if ($classroomId) {
    $staffSql .= " AND u.classroom_id = ?";
    $stmt = $pdo->prepare($staffSql . " ORDER BY fse.is_submitted DESC, u.display_name");
    $stmt->execute([$periodId, $classroomId]);
} else {
    $stmt = $pdo->prepare($staffSql . " ORDER BY fse.is_submitted DESC, u.display_name");
    $stmt->execute([$periodId]);
}
$staffMembers = $stmt->fetchAll();

// 統計を計算
$guardianSubmitted = count(array_filter($guardians, fn($g) => $g['is_submitted']));
$guardianTotal = count($guardians);
$staffSubmitted = count(array_filter($staffMembers, fn($s) => $s['is_submitted']));
$staffTotal = count($staffMembers);

// ページ開始
$currentPage = 'facility_evaluation';
$pageTitle = '回答状況';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
    .page-header {
        margin-bottom: var(--spacing-xl);
    }

    .summary-cards {
        display: flex;
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
        flex-wrap: wrap;
    }

    .summary-card {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
        flex: 1;
        min-width: 200px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .summary-card h3 {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: var(--spacing-sm);
    }

    .summary-card .value {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-purple);
    }

    .summary-card .total {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .progress-bar {
        height: 8px;
        background: var(--md-gray-5);
        border-radius: 4px;
        overflow: hidden;
        margin-top: var(--spacing-sm);
    }

    .progress-fill {
        height: 100%;
        background: var(--md-green);
        transition: width 0.3s ease;
    }

    .tabs {
        display: flex;
        border-bottom: 2px solid var(--cds-border-subtle-00);
        margin-bottom: var(--spacing-xl);
    }

    .tab {
        padding: 12px 24px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s ease;
    }

    .tab:hover {
        color: var(--text-primary);
    }

    .tab.active {
        color: var(--primary-purple);
        border-bottom-color: var(--primary-purple);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .respondent-list {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .respondent-list table {
        width: 100%;
        border-collapse: collapse;
    }

    .respondent-list th {
        background: var(--md-bg-secondary);
        padding: 14px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .respondent-list td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--cds-border-subtle-00);
        font-size: 14px;
    }

    .respondent-list tr:last-child td {
        border-bottom: none;
    }

    .respondent-list tr:hover {
        background: var(--md-gray-6);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: var(--radius-xl);
        font-size: 12px;
        font-weight: 600;
    }

    .status-submitted {
        background: rgba(52, 199, 89, 0.1);
        color: var(--md-green);
    }

    .status-in-progress {
        background: rgba(255, 149, 0, 0.1);
        color: var(--md-orange);
    }

    .status-not-started {
        background: var(--md-gray-5);
        color: var(--text-secondary);
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-secondary {
        background: var(--md-bg-secondary);
        color: var(--text-primary);
    }

    .empty-state {
        text-align: center;
        padding: var(--spacing-2xl);
        color: var(--text-secondary);
    }

    @media (max-width: 768px) {
        .summary-cards {
            flex-direction: column;
        }

        .respondent-list {
            overflow-x: auto;
        }
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-symbols-outlined">group</span>
            <?php echo htmlspecialchars($period['title']); ?> - 回答状況
        </h1>
    </div>
    <div class="page-header-actions">
        <a href="facility_evaluation.php" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            戻る
        </a>
    </div>
</div>

<div class="summary-cards">
    <div class="summary-card">
        <h3>保護者回答</h3>
        <div class="value"><?php echo $guardianSubmitted; ?></div>
        <div class="total">/ <?php echo $guardianTotal; ?> 名</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $guardianTotal > 0 ? ($guardianSubmitted / $guardianTotal * 100) : 0; ?>%"></div>
        </div>
    </div>
    <div class="summary-card">
        <h3>スタッフ回答</h3>
        <div class="value"><?php echo $staffSubmitted; ?></div>
        <div class="total">/ <?php echo $staffTotal; ?> 名</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $staffTotal > 0 ? ($staffSubmitted / $staffTotal * 100) : 0; ?>%"></div>
        </div>
    </div>
</div>

<div class="tabs">
    <div class="tab active" data-tab="guardian">保護者 (<?php echo $guardianTotal; ?>名)</div>
    <div class="tab" data-tab="staff">スタッフ (<?php echo $staffTotal; ?>名)</div>
</div>

<!-- 保護者一覧 -->
<div class="tab-content active" id="tab-guardian">
    <?php if (empty($guardians)): ?>
        <div class="empty-state">
            <p>保護者が登録されていません。</p>
        </div>
    <?php else: ?>
        <div class="respondent-list">
            <table>
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>メールアドレス</th>
                        <th>状態</th>
                        <th>提出日時</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guardians as $guardian): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($guardian['display_name']); ?></td>
                            <td><?php echo htmlspecialchars($guardian['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($guardian['is_submitted']): ?>
                                    <span class="status-badge status-submitted">提出済み</span>
                                <?php elseif ($guardian['evaluation_id']): ?>
                                    <span class="status-badge status-in-progress">入力中</span>
                                <?php else: ?>
                                    <span class="status-badge status-not-started">未開始</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($guardian['submitted_at']): ?>
                                    <?php echo date('Y/m/d H:i', strtotime($guardian['submitted_at'])); ?>
                                <?php elseif ($guardian['started_at']): ?>
                                    開始: <?php echo date('Y/m/d H:i', strtotime($guardian['started_at'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- スタッフ一覧 -->
<div class="tab-content" id="tab-staff">
    <?php if (empty($staffMembers)): ?>
        <div class="empty-state">
            <p>スタッフが登録されていません。</p>
        </div>
    <?php else: ?>
        <div class="respondent-list">
            <table>
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>メールアドレス</th>
                        <th>状態</th>
                        <th>提出日時</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffMembers as $staff): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($staff['display_name']); ?></td>
                            <td><?php echo htmlspecialchars($staff['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($staff['is_submitted']): ?>
                                    <span class="status-badge status-submitted">提出済み</span>
                                <?php elseif ($staff['evaluation_id']): ?>
                                    <span class="status-badge status-in-progress">入力中</span>
                                <?php else: ?>
                                    <span class="status-badge status-not-started">未開始</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($staff['submitted_at']): ?>
                                    <?php echo date('Y/m/d H:i', strtotime($staff['submitted_at'])); ?>
                                <?php elseif ($staff['started_at']): ?>
                                    開始: <?php echo date('Y/m/d H:i', strtotime($staff['started_at'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});
</script>

<?php renderPageEnd(); ?>
