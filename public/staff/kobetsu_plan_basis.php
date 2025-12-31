<?php
/**
 * 個別支援計画の根拠 表示ページ
 * 計画書作成の根拠となったデータを保護者に説明するための書類
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$planId = $_GET['plan_id'] ?? null;

if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: kobetsu_plan.php');
    exit;
}

// 計画書を取得
$stmt = $pdo->prepare("
    SELECT isp.*, s.student_name as current_student_name
    FROM individual_support_plans isp
    JOIN students s ON isp.student_id = s.id
    WHERE isp.id = ?
");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = '計画書が見つかりません。';
    header('Location: kobetsu_plan.php');
    exit;
}

$studentId = $plan['student_id'];
$studentName = $plan['student_name'] ?: $plan['current_student_name'];

// 計画の作成日に近いかけはし期間を探す
$planDate = new DateTime($plan['created_date']);
$stmt = $pdo->prepare("
    SELECT kp.*
    FROM kakehashi_periods kp
    WHERE kp.student_id = ?
    AND kp.submission_deadline <= ?
    ORDER BY kp.submission_deadline DESC
    LIMIT 1
");
$stmt->execute([$studentId, $planDate->format('Y-m-d')]);
$period = $stmt->fetch();

// 保護者かけはしデータを取得
$guardianKakehashi = null;
if ($period) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $period['id']]);
    $guardianKakehashi = $stmt->fetch();
}

// スタッフかけはしデータを取得
$staffKakehashi = null;
if ($period) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_staff
        WHERE student_id = ? AND period_id = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $period['id']]);
    $staffKakehashi = $stmt->fetch();
}

// 直近のモニタリングを取得
$stmt = $pdo->prepare("
    SELECT mr.*, GROUP_CONCAT(
        CONCAT(
            COALESCE(ispd.category, ''), '|',
            COALESCE(ispd.sub_category, ''), '|',
            COALESCE(md.achievement_status, ''), '|',
            COALESCE(md.monitoring_comment, '')
        ) SEPARATOR '###'
    ) as monitoring_items
    FROM monitoring_records mr
    LEFT JOIN monitoring_details md ON mr.id = md.monitoring_id
    LEFT JOIN individual_support_plan_details ispd ON md.plan_detail_id = ispd.id
    WHERE mr.student_id = ?
    AND mr.monitoring_date <= ?
    GROUP BY mr.id
    ORDER BY mr.monitoring_date DESC
    LIMIT 1
");
$stmt->execute([$studentId, $planDate->format('Y-m-d')]);
$monitoring = $stmt->fetch();

// ページ開始
$currentPage = 'kobetsu_plan';
renderPageStart('staff', $currentPage, '個別支援計画の根拠');
?>

<style>
.basis-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: var(--spacing-xl);
    border-radius: var(--radius-lg);
    margin-bottom: var(--spacing-xl);
}

.basis-header h1 {
    font-size: 24px;
    margin-bottom: 10px;
}

.basis-meta {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    font-size: var(--text-subhead);
    opacity: 0.9;
}

.basis-section {
    background: var(--md-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-lg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.basis-section h2 {
    font-size: 18px;
    color: var(--md-blue);
    margin-bottom: var(--spacing-lg);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--md-blue);
    display: flex;
    align-items: center;
    gap: 10px;
}

.data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-lg);
}

.data-item {
    background: var(--md-bg-secondary);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    border-left: 4px solid var(--md-blue);
}

.data-item.guardian {
    border-left-color: var(--md-pink);
}

.data-item.staff {
    border-left-color: var(--md-green);
}

.data-item.monitoring {
    border-left-color: var(--md-orange);
}

.data-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: var(--text-footnote);
    margin-bottom: 5px;
}

.data-value {
    color: var(--text-primary);
    font-size: var(--text-body);
    
    line-height: 1.6;
}

.data-value.empty {
    color: var(--text-tertiary);
    font-style: italic;
}

.goal-comparison {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: var(--spacing-lg);
    margin-top: var(--spacing-lg);
}

.goal-column {
    background: var(--md-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
}

.goal-column h3 {
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
    padding-bottom: 8px;
    border-bottom: 1px solid var(--md-gray-5);
}

.goal-column.guardian h3 { color: var(--md-pink); }
.goal-column.staff h3 { color: var(--md-green); }
.goal-column.plan h3 { color: var(--md-blue); }

.overall-impression {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 1px solid rgba(102, 126, 234, 0.3);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.overall-impression h2 {
    color: #667eea;
    margin-bottom: var(--spacing-md);
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: var(--spacing-xl);
    flex-wrap: wrap;
}

.no-data-message {
    background: var(--md-bg-secondary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    text-align: center;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .goal-comparison {
        grid-template-columns: 1fr;
    }
    .basis-meta {
        flex-direction: column;
        gap: 10px;
    }
}

@media print {
    .button-group, .quick-link { display: none !important; }
    .basis-section { break-inside: avoid; }
}
</style>

<a href="kobetsu_plan.php?student_id=<?= $studentId ?>&plan_id=<?= $planId ?>" class="quick-link" style="display: inline-block; margin-bottom: var(--spacing-lg); padding: 8px 16px; background: var(--md-bg-secondary); border-radius: 6px; text-decoration: none; color: var(--text-primary);">← 個別支援計画に戻る</a>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- ヘッダー -->
<div class="basis-header">
    <h1><span class="material-symbols-outlined">analytics</span> 個別支援計画の根拠</h1>
    <div class="basis-meta">
        <span><span class="material-symbols-outlined">person</span> <?= htmlspecialchars($studentName) ?></span>
        <span><span class="material-symbols-outlined">event</span> 計画作成日: <?= date('Y年m月d日', strtotime($plan['created_date'])) ?></span>
        <?php if ($period): ?>
            <span><span class="material-symbols-outlined">assignment</span> 根拠期間: <?= date('Y/m/d', strtotime($period['submission_deadline'])) ?> 期限のかけはし</span>
        <?php endif; ?>
    </div>
</div>

<?php if (!$period && !$guardianKakehashi && !$staffKakehashi && !$monitoring): ?>
    <div class="no-data-message">
        <h3><span class="material-symbols-outlined">inbox</span> 根拠データが見つかりません</h3>
        <p>この計画書に関連するかけはしデータやモニタリングデータが見つかりませんでした。</p>
        <p>計画書が手動で作成された可能性があります。</p>
    </div>
<?php else: ?>

    <!-- 目標の比較 -->
    <div class="basis-section">
        <h2><span class="material-symbols-outlined">track_changes</span> 目標の比較と整合性</h2>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
            保護者・スタッフのかけはしで設定された目標と、個別支援計画で設定された目標を比較します。
        </p>

        <h4 style="margin-bottom: 10px; color: var(--text-primary);">【短期目標】</h4>
        <div class="goal-comparison">
            <div class="goal-column guardian">
                <h3><span class="material-symbols-outlined">group</span> 保護者の目標</h3>
                <div class="data-value <?= empty($guardianKakehashi['short_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['short_term_goal'] ?? '（データなし）')) ?></div>
            </div>
            <div class="goal-column staff">
                <h3><span class="material-symbols-outlined">person</span> スタッフの目標</h3>
                <div class="data-value <?= empty($staffKakehashi['short_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['short_term_goal'] ?? '（データなし）')) ?></div>
            </div>
            <div class="goal-column plan">
                <h3><span class="material-symbols-outlined">assignment</span> 計画書の目標</h3>
                <div class="data-value <?= empty($plan['short_term_goal_text']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($plan['short_term_goal_text'] ?? '（未設定）')) ?></div>
            </div>
        </div>

        <h4 style="margin: 30px 0 10px 0; color: var(--text-primary);">【長期目標】</h4>
        <div class="goal-comparison">
            <div class="goal-column guardian">
                <h3><span class="material-symbols-outlined">group</span> 保護者の目標</h3>
                <div class="data-value <?= empty($guardianKakehashi['long_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['long_term_goal'] ?? '（データなし）')) ?></div>
            </div>
            <div class="goal-column staff">
                <h3><span class="material-symbols-outlined">person</span> スタッフの目標</h3>
                <div class="data-value <?= empty($staffKakehashi['long_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['long_term_goal'] ?? '（データなし）')) ?></div>
            </div>
            <div class="goal-column plan">
                <h3><span class="material-symbols-outlined">assignment</span> 計画書の目標</h3>
                <div class="data-value <?= empty($plan['long_term_goal_text']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($plan['long_term_goal_text'] ?? '（未設定）')) ?></div>
            </div>
        </div>
    </div>

    <!-- 保護者かけはし -->
    <?php if ($guardianKakehashi): ?>
    <div class="basis-section">
        <h2><span class="material-symbols-outlined">group</span> 保護者からのかけはし</h2>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
            提出日: <?= $guardianKakehashi['submitted_at'] ? date('Y年m月d日', strtotime($guardianKakehashi['submitted_at'])) : '未提出' ?>
        </p>
        <div class="data-grid">
            <div class="data-item guardian">
                <div class="data-label">本人の願い</div>
                <div class="data-value <?= empty($guardianKakehashi['student_wish']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['student_wish'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">家庭での課題・願い</div>
                <div class="data-value <?= empty($guardianKakehashi['home_challenges']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['home_challenges'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">健康・生活</div>
                <div class="data-value <?= empty($guardianKakehashi['domain_health_life']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_health_life'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">運動・感覚</div>
                <div class="data-value <?= empty($guardianKakehashi['domain_motor_sensory']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_motor_sensory'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">認知・行動</div>
                <div class="data-value <?= empty($guardianKakehashi['domain_cognitive_behavior']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_cognitive_behavior'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">言語・コミュニケーション</div>
                <div class="data-value <?= empty($guardianKakehashi['domain_language_communication']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_language_communication'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">人間関係・社会性</div>
                <div class="data-value <?= empty($guardianKakehashi['domain_social_relations']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_social_relations'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item guardian">
                <div class="data-label">その他</div>
                <div class="data-value <?= empty($guardianKakehashi['other_challenges']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['other_challenges'] ?: '（未記入）')) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- スタッフかけはし -->
    <?php if ($staffKakehashi): ?>
    <div class="basis-section">
        <h2><span class="material-symbols-outlined">person</span> スタッフからのかけはし</h2>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
            提出日: <?= $staffKakehashi['submitted_at'] ? date('Y年m月d日', strtotime($staffKakehashi['submitted_at'])) : '未提出' ?>
        </p>
        <div class="data-grid">
            <div class="data-item staff">
                <div class="data-label">本人の願い（スタッフ観察）</div>
                <div class="data-value <?= empty($staffKakehashi['student_wish']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['student_wish'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item staff">
                <div class="data-label">健康・生活</div>
                <div class="data-value <?= empty($staffKakehashi['domain_health_life']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_health_life'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item staff">
                <div class="data-label">運動・感覚</div>
                <div class="data-value <?= empty($staffKakehashi['domain_motor_sensory']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_motor_sensory'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item staff">
                <div class="data-label">認知・行動</div>
                <div class="data-value <?= empty($staffKakehashi['domain_cognitive_behavior']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_cognitive_behavior'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item staff">
                <div class="data-label">言語・コミュニケーション</div>
                <div class="data-value <?= empty($staffKakehashi['domain_language_communication']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_language_communication'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item staff">
                <div class="data-label">人間関係・社会性</div>
                <div class="data-value <?= empty($staffKakehashi['domain_social_relations']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_social_relations'] ?: '（未記入）')) ?></div>
            </div>
            <div class="data-item staff">
                <div class="data-label">その他</div>
                <div class="data-value <?= empty($staffKakehashi['other_challenges']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['other_challenges'] ?: '（未記入）')) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- モニタリング情報 -->
    <?php if ($monitoring): ?>
    <div class="basis-section">
        <h2><span class="material-symbols-outlined">trending_up</span> 直近のモニタリング情報</h2>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
            実施日: <?= date('Y年m月d日', strtotime($monitoring['monitoring_date'])) ?>
        </p>
        <div class="data-grid">
            <div class="data-item monitoring" style="grid-column: 1 / -1;">
                <div class="data-label">総合所見</div>
                <div class="data-value <?= empty($monitoring['overall_comment']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($monitoring['overall_comment'] ?: '（未記入）')) ?></div>
            </div>
            <?php
            if ($monitoring['monitoring_items']) {
                $items = explode('###', $monitoring['monitoring_items']);
                foreach ($items as $item) {
                    $parts = explode('|', $item);
                    if (count($parts) >= 4 && !empty($parts[0])) {
                        $category = trim($parts[0]);
                        $subCategory = trim($parts[1]);
                        $status = trim($parts[2]);
                        $comment = trim($parts[3]);

                        $statusLabel = match($status) {
                            '達成' => '<span class="material-symbols-outlined">check_circle</span> 達成',
                            '継続' => '<span class="material-symbols-outlined">sync</span> 継続',
                            '未達成' => '<span class="material-symbols-outlined">close</span> 未達成',
                            default => $status ?: '（未評価）'
                        };
            ?>
            <div class="data-item monitoring">
                <div class="data-label"><?= htmlspecialchars($category) ?> - <?= htmlspecialchars($subCategory) ?></div>
                <div style="margin-bottom: 5px; font-weight: 500;"><?= $statusLabel ?></div>
                <div class="data-value <?= empty($comment) ? 'empty' : '' ?>"><?= htmlspecialchars($comment ?: '（コメントなし）') ?></div>
            </div>
            <?php
                    }
                }
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 全体所感 -->
    <?php if (!empty($plan['basis_content'])): ?>
    <div class="overall-impression">
        <h2><span class="material-symbols-outlined">edit_note</span> 全体所感</h2>
        <div class="data-value" style="background: white; padding: var(--spacing-lg); border-radius: var(--radius-sm);"><?= nl2br(htmlspecialchars(trim($plan['basis_content']))) ?></div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<!-- ボタン -->
<div class="button-group">
    <?php if (empty($plan['basis_content'])): ?>
        <a href="kobetsu_plan_basis_generate.php?plan_id=<?= $planId ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <span class="material-symbols-outlined">edit_note</span> AIで全体所感を生成
        </a>
    <?php else: ?>
        <a href="kobetsu_plan_basis_generate.php?plan_id=<?= $planId ?>&regenerate=1" class="btn btn-secondary">
            <span class="material-symbols-outlined">sync</span> 全体所感を再生成
        </a>
    <?php endif; ?>
    <a href="kobetsu_plan_basis_pdf.php?plan_id=<?= $planId ?>" class="btn btn-info" target="_blank">
        <span class="material-symbols-outlined">description</span> PDFで出力
    </a>
    <button onclick="window.print()" class="btn btn-secondary">
        <span class="material-symbols-outlined">print</span> 印刷
    </button>
</div>

<?php
renderPageEnd();
?>
