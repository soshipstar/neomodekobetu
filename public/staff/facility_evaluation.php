<?php
/**
 * 事業所評価シート管理ページ
 * 年度ごとの評価期間の作成・管理、回答状況の確認、集計結果の表示
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 現在の年度を計算（4月始まり）
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$currentFiscalYear = $currentMonth >= 4 ? $currentYear : $currentYear - 1;

// 評価期間の作成処理
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_period') {
        $fiscalYear = (int)$_POST['fiscal_year'];
        $guardianDeadline = $_POST['guardian_deadline'];
        $staffDeadline = $_POST['staff_deadline'];

        try {
            // 既存チェック
            $stmt = $pdo->prepare("SELECT id FROM facility_evaluation_periods WHERE fiscal_year = ?");
            $stmt->execute([$fiscalYear]);
            if ($stmt->fetch()) {
                $message = "{$fiscalYear}年度の評価期間は既に作成されています。";
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO facility_evaluation_periods (fiscal_year, title, guardian_deadline, staff_deadline, created_by, status)
                    VALUES (?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->execute([
                    $fiscalYear,
                    "{$fiscalYear}年度 事業所評価",
                    $guardianDeadline ?: null,
                    $staffDeadline ?: null,
                    $currentUser['id']
                ]);
                $message = "{$fiscalYear}年度の評価期間を作成しました。";
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = "エラー: " . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'start_collecting') {
        $periodId = (int)$_POST['period_id'];
        try {
            $pdo->prepare("UPDATE facility_evaluation_periods SET status = 'collecting' WHERE id = ?")->execute([$periodId]);
            $message = "回答収集を開始しました。保護者とスタッフに依頼を送信してください。";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "エラー: " . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'send_requests') {
        $periodId = (int)$_POST['period_id'];
        try {
            // 評価期間情報を取得
            $stmt = $pdo->prepare("SELECT * FROM facility_evaluation_periods WHERE id = ?");
            $stmt->execute([$periodId]);
            $period = $stmt->fetch();

            if ($period) {
                // 回答収集中に変更（通知システムで自動的に保護者に表示される）
                if ($period['status'] === 'draft') {
                    $pdo->prepare("UPDATE facility_evaluation_periods SET status = 'collecting' WHERE id = ?")
                        ->execute([$periodId]);
                }

                $message = "依頼を開始しました。保護者の画面に事業所評価のお知らせが表示されます。";
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = "エラー: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 評価期間一覧を取得
$stmt = $pdo->query("
    SELECT fep.*,
           (SELECT COUNT(*) FROM facility_guardian_evaluations fge WHERE fge.period_id = fep.id AND fge.is_submitted = 1) as guardian_submitted_count,
           (SELECT COUNT(*) FROM facility_guardian_evaluations fge WHERE fge.period_id = fep.id) as guardian_total_count,
           (SELECT COUNT(*) FROM facility_staff_evaluations fse WHERE fse.period_id = fep.id AND fse.is_submitted = 1) as staff_submitted_count,
           (SELECT COUNT(*) FROM facility_staff_evaluations fse WHERE fse.period_id = fep.id) as staff_total_count,
           u.display_name as created_by_name
    FROM facility_evaluation_periods fep
    LEFT JOIN users u ON fep.created_by = u.id
    ORDER BY fep.fiscal_year DESC
");
$periods = $stmt->fetchAll();

// 保護者数とスタッフ数を取得
$guardianCount = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'guardian' AND is_active = 1")->fetchColumn();
$staffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type IN ('staff', 'admin') AND is_active = 1")->fetchColumn();

// ページ開始
$currentPage = 'facility_evaluation';
$pageTitle = '事業所評価シート';
renderPageStart('staff', $currentPage, $pageTitle);

$statusLabels = [
    'draft' => '下書き',
    'collecting' => '回答収集中',
    'aggregating' => '集計中',
    'published' => '公表済み'
];
$statusClasses = [
    'draft' => 'status-draft',
    'collecting' => 'status-collecting',
    'aggregating' => 'status-aggregating',
    'published' => 'status-published'
];
?>

<style>
    .page-header {
        margin-bottom: var(--spacing-xl);
    }

    .page-header-content h1 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .message {
        padding: 12px 16px;
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-lg);
    }

    .message.success {
        background: rgba(52, 199, 89, 0.1);
        color: var(--md-green);
        border: 1px solid var(--md-green);
    }

    .message.error {
        background: rgba(255, 59, 48, 0.1);
        color: var(--md-red);
        border: 1px solid var(--md-red);
    }

    .create-section {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-xl);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .create-section h2 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: var(--spacing-lg);
        color: var(--text-primary);
    }

    .form-row {
        display: flex;
        gap: var(--spacing-lg);
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .form-group input,
    .form-group select {
        padding: 10px 14px;
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: var(--radius-sm);
        font-size: 14px;
        min-width: 180px;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--cds-blue-60);
        box-shadow: 0 0 0 2px rgba(15, 98, 254, 0.2);
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

    .btn-primary {
        background: var(--primary-purple);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-purple-dark);
    }

    .btn-secondary {
        background: var(--md-bg-secondary);
        color: var(--text-primary);
    }

    .btn-secondary:hover {
        background: var(--md-gray-5);
    }

    .btn-success {
        background: var(--md-green);
        color: white;
    }

    .btn-warning {
        background: var(--md-orange);
        color: var(--text-primary);
    }

    .periods-section {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-xl);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .periods-section h2 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: var(--spacing-lg);
        color: var(--text-primary);
    }

    .period-card {
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-lg);
    }

    .period-card:last-child {
        margin-bottom: 0;
    }

    .period-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-md);
    }

    .period-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: var(--radius-xl);
        font-size: 12px;
        font-weight: 600;
    }

    .status-draft {
        background: var(--md-gray-5);
        color: var(--text-secondary);
    }

    .status-collecting {
        background: rgba(52, 199, 89, 0.1);
        color: var(--md-green);
    }

    .status-aggregating {
        background: rgba(255, 149, 0, 0.1);
        color: var(--md-orange);
    }

    .status-published {
        background: rgba(0, 122, 255, 0.1);
        color: var(--md-blue);
    }

    .period-stats {
        display: flex;
        gap: var(--spacing-xl);
        margin-bottom: var(--spacing-lg);
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .stat-label {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .stat-value {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .period-actions {
        display: flex;
        gap: var(--spacing-sm);
        flex-wrap: wrap;
    }

    .empty-state {
        text-align: center;
        padding: var(--spacing-2xl);
        color: var(--text-secondary);
    }

    .progress-bar {
        height: 8px;
        background: var(--md-gray-5);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 4px;
    }

    .progress-fill {
        height: 100%;
        background: var(--md-green);
        transition: width 0.3s ease;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }

        .form-group {
            width: 100%;
        }

        .form-group input,
        .form-group select {
            width: 100%;
        }

        .period-stats {
            flex-direction: column;
            gap: var(--spacing-md);
        }

        .period-actions {
            flex-direction: column;
        }

        .period-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-symbols-outlined">fact_check</span>
            事業所評価シート
        </h1>
        <p class="page-subtitle">年度ごとの事業所評価アンケートを管理します</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="message <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- 新規作成セクション -->
<div class="create-section">
    <h2>
        <span class="material-symbols-outlined">add_circle</span>
        新しい評価期間を作成
    </h2>
    <form method="POST">
        <input type="hidden" name="action" value="create_period">
        <div class="form-row">
            <div class="form-group">
                <label>年度</label>
                <select name="fiscal_year" required>
                    <?php for ($y = $currentFiscalYear + 1; $y >= $currentFiscalYear - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y === $currentFiscalYear ? 'selected' : ''; ?>>
                            <?php echo $y; ?>年度
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>保護者回答期限</label>
                <input type="date" name="guardian_deadline" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
            </div>
            <div class="form-group">
                <label>スタッフ回答期限</label>
                <input type="date" name="staff_deadline" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">add</span>
                    作成
                </button>
            </div>
        </div>
    </form>
</div>

<!-- 評価期間一覧 -->
<div class="periods-section">
    <h2>
        <span class="material-symbols-outlined">list</span>
        評価期間一覧
    </h2>

    <?php if (empty($periods)): ?>
        <div class="empty-state">
            <span class="material-symbols-outlined" style="font-size: 48px; margin-bottom: 16px;">folder_open</span>
            <p>評価期間がまだ作成されていません。</p>
            <p>上のフォームから新しい評価期間を作成してください。</p>
        </div>
    <?php else: ?>
        <?php foreach ($periods as $period): ?>
            <div class="period-card">
                <div class="period-header">
                    <span class="period-title"><?php echo htmlspecialchars($period['title']); ?></span>
                    <span class="status-badge <?php echo $statusClasses[$period['status']]; ?>">
                        <?php echo $statusLabels[$period['status']]; ?>
                    </span>
                </div>

                <div class="period-stats">
                    <div class="stat-item">
                        <span class="stat-label">保護者回答</span>
                        <span class="stat-value">
                            <?php echo $period['guardian_submitted_count']; ?> / <?php echo $guardianCount; ?>件
                        </span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $guardianCount > 0 ? ($period['guardian_submitted_count'] / $guardianCount * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">スタッフ回答</span>
                        <span class="stat-value">
                            <?php echo $period['staff_submitted_count']; ?> / <?php echo $staffCount; ?>件
                        </span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $staffCount > 0 ? ($period['staff_submitted_count'] / $staffCount * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">保護者期限</span>
                        <span class="stat-value">
                            <?php echo $period['guardian_deadline'] ? date('Y/m/d', strtotime($period['guardian_deadline'])) : '未設定'; ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">スタッフ期限</span>
                        <span class="stat-value">
                            <?php echo $period['staff_deadline'] ? date('Y/m/d', strtotime($period['staff_deadline'])) : '未設定'; ?>
                        </span>
                    </div>
                </div>

                <?php if ($period['status'] === 'collecting'): ?>
                    <div style="background: rgba(52, 199, 89, 0.1); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: var(--spacing-md); font-size: 13px; color: var(--md-green);">
                        <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">notifications_active</span>
                        保護者の画面に通知が表示されています。メニューの「事業所評価」から回答できます。
                    </div>
                <?php endif; ?>

                <div class="period-actions">
                    <?php if ($period['status'] === 'draft'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="start_collecting">
                            <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                            <button type="submit" class="btn btn-success">
                                <span class="material-symbols-outlined">play_arrow</span>
                                回答収集開始
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="facility_evaluation_form.php?period_id=<?php echo $period['id']; ?>" class="btn btn-secondary">
                        <span class="material-symbols-outlined">edit</span>
                        スタッフ自己評価
                    </a>

                    <a href="facility_evaluation_responses.php?period_id=<?php echo $period['id']; ?>" class="btn btn-secondary">
                        <span class="material-symbols-outlined">visibility</span>
                        回答一覧
                    </a>

                    <?php if ($period['guardian_submitted_count'] > 0 || $period['staff_submitted_count'] > 0): ?>
                        <a href="facility_evaluation_summary.php?period_id=<?php echo $period['id']; ?>" class="btn btn-primary">
                            <span class="material-symbols-outlined">summarize</span>
                            集計・公表
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php renderPageEnd(); ?>
