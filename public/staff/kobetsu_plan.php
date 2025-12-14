<?php
/**
 * スタッフ用 個別支援計画書作成ページ
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

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

// 既存の計画を取得（plan_idが指定されている場合）
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

// 選択された生徒の情報
$selectedStudent = null;
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $selectedStudent = $stmt->fetch();
}

// 選択された生徒の計画一覧
$studentPlans = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE student_id = ? ORDER BY created_date DESC");
    $stmt->execute([$selectedStudentId]);
    $studentPlans = $stmt->fetchAll();
}

// 初期明細データ（新規作成時）
$defaultDetails = [
    ['category' => '本人支援', 'sub_category' => '生活習慣（健康・生活）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => 'コミュニケーション（言語・コミュニケーション）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => '社会性（人間関係・社会性）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => '運動・感覚（運動・感覚）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => '学習（認知・行動）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '家族支援', 'sub_category' => '保護者支援', 'achievement_date' => '2025-09-28', 'staff_organization' => "児童発達支援管理責任者\n保育士"],
    ['category' => '地域支援', 'sub_category' => '関係機関連携', 'achievement_date' => '2025-09-28', 'staff_organization' => "児童発達支援管理責任者"],
];

// 明細データの準備
if (empty($planDetails)) {
    $planDetails = $defaultDetails;
}

// かけはし分析データから明細を上書き
if (isset($_SESSION['generated_plan']) && !empty($_SESSION['generated_plan']['details'])) {
    $planDetails = $_SESSION['generated_plan']['details'];
}

// かけはし分析データの取得（セッションから）
$generatedPlan = null;
if (isset($_SESSION['generated_plan'])) {
    $generatedPlan = $_SESSION['generated_plan'];
    unset($_SESSION['generated_plan']);
}

// 選択された生徒のかけはし期間一覧を取得（提出期限が今日から1ヶ月以内の期間のみ）
$studentPeriods = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT kp.*,
               kg.is_submitted as guardian_submitted,
               ks.is_submitted as staff_submitted
        FROM kakehashi_periods kp
        LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = kp.student_id
        LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = kp.student_id
        WHERE kp.student_id = ? AND kp.is_active = 1
        AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
        ORDER BY kp.submission_deadline DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $studentPeriods = $stmt->fetchAll();
}

// 未作成のかけはし期間をチェック
$uncreatedPeriods = [];
if ($selectedStudentId) {
    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT support_start_date FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $student = $stmt->fetch();

    if ($student && $student['support_start_date']) {
        // 作成可能なかけはし期間を計算
        $supportStartDate = new DateTime($student['support_start_date']);
        $today = new DateTime();
        $generationLimit = clone $today;
        $generationLimit->modify('+1 month');

        // 既存のかけはし期間数を取得
        $stmt = $pdo->prepare("SELECT COUNT(*) as period_count FROM kakehashi_periods WHERE student_id = ?");
        $stmt->execute([$selectedStudentId]);
        $existingCount = (int)$stmt->fetch()['period_count'];

        // 初回かけはし（支援開始日の1日前が期限）
        $firstDeadline = clone $supportStartDate;
        $firstDeadline->modify('-1 day');

        if ($existingCount === 0 && $firstDeadline <= $generationLimit) {
            $uncreatedPeriods[] = [
                'type' => '初回',
                'deadline' => $firstDeadline->format('Y/m/d')
            ];
        }

        // 2回目かけはし（初回期限の4ヶ月後が期限）
        $secondDeadline = clone $firstDeadline;
        $secondDeadline->modify('+4 months');

        if ($existingCount <= 1 && $secondDeadline <= $generationLimit) {
            $uncreatedPeriods[] = [
                'type' => '2回目',
                'deadline' => $secondDeadline->format('Y/m/d')
            ];
        }

        // 3回目以降のかけはし（6ヶ月ごと）
        if ($existingCount >= 1) {
            // 最新のかけはし期限を取得
            $stmt = $pdo->prepare("
                SELECT submission_deadline
                FROM kakehashi_periods
                WHERE student_id = ?
                ORDER BY submission_deadline DESC
                LIMIT 1
            ");
            $stmt->execute([$selectedStudentId]);
            $latestPeriod = $stmt->fetch();

            if ($latestPeriod) {
                $latestDeadline = new DateTime($latestPeriod['submission_deadline']);
                $nextDeadline = clone $latestDeadline;

                // 6ヶ月ごとに次のかけはしをチェック
                $periodNum = $existingCount + 1;
                while (true) {
                    $nextDeadline->modify('+6 months');

                    if ($nextDeadline > $generationLimit) {
                        break;
                    }

                    $uncreatedPeriods[] = [
                        'type' => "{$periodNum}回目",
                        'deadline' => $nextDeadline->format('Y/m/d')
                    ];

                    $periodNum++;
                }
            }
        }
    }
}

// ページ開始
$currentPage = 'kobetsu_plan';
renderPageStart('staff', $currentPage, '個別支援計画書作成');
?>

<style>
.selection-area {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--apple-gray-6);
    border-radius: var(--radius-md);
}

.plan-meta {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-xl);
}

.meta-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.meta-item { flex: 1; }

.meta-label {
    font-weight: 600;
    color: var(--apple-blue);
    margin-bottom: 5px;
    font-size: var(--text-subhead);
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--apple-blue);
    margin: var(--spacing-xl) 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
}

.goal-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: var(--spacing-md);
}

.goal-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: var(--text-subhead);
}

.goal-date {
    padding: var(--spacing-md);
    border: 2px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
}

.table-wrapper {
    overflow-x: auto;
    margin-top: var(--spacing-lg);
}

.support-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--apple-bg-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.support-table th {
    background: var(--apple-blue);
    color: white;
    padding: var(--spacing-md) 8px;
    text-align: left;
    font-size: var(--text-footnote);
    font-weight: 600;
    border: 1px solid var(--apple-blue);
}

.support-table td {
    padding: var(--spacing-md) 8px;
    border: 1px solid var(--apple-gray-5);
    vertical-align: top;
}

.support-table input,
.support-table textarea {
    width: 100%;
    padding: var(--spacing-sm);
    border: 1px solid var(--apple-gray-5);
    border-radius: 4px;
    font-size: var(--text-subhead);
    font-family: inherit;
}

.support-table textarea {
    min-height: 60px;
    resize: vertical;
}

.support-table input[type="number"] {
    width: 80px;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: var(--spacing-xl);
    justify-content: flex-end;
    flex-wrap: wrap;
}

.note-box {
    background: var(--apple-bg-secondary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-top: var(--spacing-lg);
    border-left: 4px solid var(--apple-orange);
    font-size: var(--text-subhead);
}

.plans-list {
    margin-bottom: var(--spacing-lg);
}

.plan-item {
    display: inline-block;
    padding: var(--spacing-sm) 15px;
    margin: 5px;
    background: rgba(0, 122, 255, 0.1);
    border-radius: 6px;
    text-decoration: none;
    color: var(--apple-blue);
    transition: all var(--duration-normal) var(--ease-out);
}

.plan-item:hover {
    background: var(--apple-blue);
    color: white;
}

.plan-item.active {
    background: var(--apple-blue);
    color: white;
}

.guardian-confirmed-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--apple-green) 0%, #20c997 100%);
    color: white;
    padding: 6px 15px;
    border-radius: var(--radius-xl);
    font-size: var(--text-footnote);
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.analyze-section {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-purple);
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
    .meta-row { flex-direction: column; }
    .button-group { flex-direction: column; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">📋 個別支援計画書作成</h1>
        <?php if ($planData && ($planData['guardian_confirmed'] ?? 0)): ?>
            <div class="guardian-confirmed-badge">
                ✓ 保護者確認済み（<?= date('Y/m/d H:i', strtotime($planData['guardian_confirmed_at'])) ?>）
            </div>
        <?php endif; ?>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 戻る</a>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- 生徒選択エリア -->
<div class="selection-area">
    <div class="form-group" style="flex: 1;">
        <label class="form-label">生徒を選択 *</label>
        <select id="studentSelect" onchange="changeStudent()" class="form-control">
            <option value="">-- 生徒を選択してください --</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($student['student_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($selectedStudentId): ?>
    <!-- 未作成のかけはし期間がある場合の警告 -->
    <?php if (!empty($uncreatedPeriods)): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Caution: 作成可能なかけはしで未作成のものがあります</strong>
            <p>以下のかけはし期間が未作成です。生徒管理ページから自動生成してください：</p>
            <ul>
                <?php foreach ($uncreatedPeriods as $period): ?>
                    <li><?= htmlspecialchars($period['type']) ?>かけはし（提出期限: <?= htmlspecialchars($period['deadline']) ?>）</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- かけはし分析（新規作成時のみ） -->
    <?php if (!$selectedPlanId && !empty($studentPeriods)): ?>
        <div class="analyze-section">
            <h3 style="margin-bottom: 15px; color: var(--apple-purple);">📊 かけはしを分析</h3>
            <p style="margin-bottom: 15px; color: var(--text-secondary);">かけはしデータとモニタリング情報を分析し、個別支援計画書案を生成します。</p>
            <form method="POST" action="kobetsu_plan_generate.php" onsubmit="return confirmGenerate()" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label class="form-label">かけはし期間を選択</label>
                    <select name="period_id" required class="form-control">
                        <option value="">-- かけはし期間を選択 --</option>
                        <?php foreach ($studentPeriods as $period): ?>
                            <option value="<?= $period['id'] ?>">
                                <?= date('Y/m/d', strtotime($period['submission_deadline'])) ?> 期限
                                <?php if ($period['guardian_submitted']): ?>(保護者提出済)<?php endif; ?>
                                <?php if ($period['staff_submitted']): ?>(スタッフ提出済)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    📊 分析開始
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- 既存の計画一覧 -->
    <?php if (!empty($studentPlans)): ?>
        <div class="plans-list">
            <strong>既存の計画:</strong>
            <?php foreach ($studentPlans as $plan): ?>
                <a href="kobetsu_plan.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $plan['id'] ?>"
                   class="plan-item <?= $plan['id'] == $selectedPlanId ? 'active' : '' ?>">
                    <?= date('Y/m/d', strtotime($plan['created_date'])) ?>
                </a>
            <?php endforeach; ?>
            <a href="kobetsu_plan.php?student_id=<?= $selectedStudentId ?>" class="plan-item">+ 新規作成</a>
        </div>
    <?php endif; ?>

    <!-- 計画書入力フォーム -->
    <form method="POST" action="kobetsu_plan_save.php" id="planForm">
        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
        <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?? '' ?>">

        <div class="card">
            <div class="card-body">
                <!-- 基本情報 -->
                <div class="plan-meta">
                    <div class="meta-row">
                        <div class="meta-item">
                            <div class="meta-label">氏名</div>
                            <input type="text" name="student_name" class="form-control" value="<?= htmlspecialchars($planData['student_name'] ?? $selectedStudent['student_name']) ?>" required>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">作成年月日</div>
                            <input type="date" name="created_date" class="form-control" value="<?= $planData['created_date'] ?? ($generatedPlan['created_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- 意向・方針 -->
                <div class="section-title">利用児及び家族の生活に対する意向</div>
                <div class="form-group">
                    <textarea name="life_intention" rows="4" class="form-control"><?= htmlspecialchars($planData['life_intention'] ?? $generatedPlan['life_intention'] ?? '') ?></textarea>
                </div>

                <div class="section-title">総合的な支援の方針</div>
                <div class="form-group">
                    <textarea name="overall_policy" rows="4" class="form-control"><?= htmlspecialchars($planData['overall_policy'] ?? $generatedPlan['overall_policy'] ?? '') ?></textarea>
                </div>

                <!-- 目標設定 -->
                <div class="section-title">長期目標</div>
                <div class="goal-header">
                    <div class="goal-title">達成時期</div>
                    <input type="date" name="long_term_goal_date" class="goal-date" value="<?= $planData['long_term_goal_date'] ?? ($generatedPlan['long_term_goal_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <textarea name="long_term_goal_text" rows="4" class="form-control"><?= htmlspecialchars($planData['long_term_goal_text'] ?? $generatedPlan['long_term_goal_text'] ?? '') ?></textarea>
                </div>

                <div class="section-title">短期目標</div>
                <div class="goal-header">
                    <div class="goal-title">達成時期</div>
                    <input type="date" name="short_term_goal_date" class="goal-date" value="<?= $planData['short_term_goal_date'] ?? ($generatedPlan['short_term_goal_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <textarea name="short_term_goal_text" rows="4" class="form-control"><?= htmlspecialchars($planData['short_term_goal_text'] ?? $generatedPlan['short_term_goal_text'] ?? '') ?></textarea>
                </div>

                <!-- 支援目標及び具体的な支援内容等 -->
                <div class="section-title">○支援目標及び具体的な支援内容等</div>

                <div class="table-wrapper">
                    <table class="support-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">項目</th>
                                <th style="width: 200px;">支援目標<br>（具体的な到達目標）</th>
                                <th style="width: 250px;">支援内容<br>（内容・支援の提供上のポイント・5領域（※）との関連性等）</th>
                                <th style="width: 110px;">達成時期</th>
                                <th style="width: 150px;">担当者／提供機関</th>
                                <th style="width: 150px;">留意事項</th>
                                <th style="width: 80px;">優先順位</th>
                            </tr>
                        </thead>
                        <tbody id="detailsTable">
                            <?php foreach ($planDetails as $index => $detail): ?>
                                <tr>
                                    <td>
                                        <input type="text" name="details[<?= $index ?>][category]" value="<?= htmlspecialchars($detail['category'] ?? '') ?>" placeholder="項目">
                                        <textarea name="details[<?= $index ?>][sub_category]" rows="2" placeholder="サブカテゴリ"><?= htmlspecialchars($detail['sub_category'] ?? '') ?></textarea>
                                    </td>
                                    <td>
                                        <textarea name="details[<?= $index ?>][support_goal]" rows="3"><?= htmlspecialchars($detail['support_goal'] ?? '') ?></textarea>
                                    </td>
                                    <td>
                                        <textarea name="details[<?= $index ?>][support_content]" rows="3"><?= htmlspecialchars($detail['support_content'] ?? '') ?></textarea>
                                    </td>
                                    <td>
                                        <input type="date" name="details[<?= $index ?>][achievement_date]" value="<?= $detail['achievement_date'] ?? '' ?>">
                                    </td>
                                    <td>
                                        <textarea name="details[<?= $index ?>][staff_organization]" rows="3"><?= htmlspecialchars($detail['staff_organization'] ?? '') ?></textarea>
                                    </td>
                                    <td>
                                        <textarea name="details[<?= $index ?>][notes]" rows="3"><?= htmlspecialchars($detail['notes'] ?? '') ?></textarea>
                                    </td>
                                    <td>
                                        <input type="number" name="details[<?= $index ?>][priority]" value="<?= $detail['priority'] ?? '' ?>" min="1" max="10">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-warning" onclick="addDetailRow()" style="margin-top: 10px;">+ 行を追加</button>

                <div class="note-box">
                    <strong>※ 5領域の視点：</strong>
                    「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」
                </div>

                <!-- 同意欄 -->
                <div class="section-title">同意</div>
                <div class="meta-row">
                    <div class="meta-item">
                        <div class="meta-label">管理責任者氏名</div>
                        <input type="text" name="manager_name" class="form-control" value="<?= htmlspecialchars($planData['manager_name'] ?? '') ?>">
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">同意日</div>
                        <input type="date" name="consent_date" class="form-control" value="<?= $planData['consent_date'] ?? ($generatedPlan['consent_date'] ?? '') ?>">
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">保護者署名</div>
                        <input type="text" name="guardian_signature" class="form-control" value="<?= htmlspecialchars($planData['guardian_signature'] ?? '') ?>">
                    </div>
                </div>

                <!-- ボタン -->
                <div class="button-group">
                    <button type="submit" name="save_draft" class="btn btn-secondary">📝 下書き保存（保護者非公開）</button>
                    <button type="submit" name="action" value="save" class="btn btn-success">✅ 作成・提出（保護者に公開）</button>
                    <?php if ($selectedPlanId): ?>
                        <a href="kobetsu_plan_export.php?plan_id=<?= $selectedPlanId ?>" class="btn btn-info">📥 CSV出力</a>
                        <a href="kobetsu_plan_pdf.php?plan_id=<?= $selectedPlanId ?>" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">📄 PDF出力</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
<?php else: ?>
    <div class="alert alert-info">生徒を選択してください。</div>
<?php endif; ?>

<?php
$detailCount = count($planDetails);
$inlineJs = <<<JS
function changeStudent() {
    const studentId = document.getElementById('studentSelect').value;
    if (studentId) {
        window.location.href = 'kobetsu_plan.php?student_id=' + studentId;
    }
}

function confirmGenerate() {
    return confirm('選択したかけはし期間のデータを分析し、個別支援計画書案を生成します。\\n既に入力されている内容は上書きされます。\\nよろしいですか？');
}

let rowIndex = {$detailCount};

// 短期目標の達成時期が変更されたら、支援内容の達成時期も同期
document.addEventListener('DOMContentLoaded', function() {
    const shortTermGoalDate = document.querySelector('input[name="short_term_goal_date"]');

    if (shortTermGoalDate) {
        shortTermGoalDate.addEventListener('change', function() {
            syncAchievementDates(this.value);
        });

        // 初期読み込み時に既存の値で同期
        if (shortTermGoalDate.value) {
            syncAchievementDates(shortTermGoalDate.value);
        }
    }
});

function syncAchievementDates(dateValue) {
    const achievementDates = document.querySelectorAll('input[name*="[achievement_date]"]');
    achievementDates.forEach(function(input) {
        input.value = dateValue;
    });
}

function addDetailRow() {
    const table = document.getElementById('detailsTable');
    const row = table.insertRow();

    // 短期目標の達成時期を取得
    const shortTermGoalDate = document.querySelector('input[name="short_term_goal_date"]');
    const achievementDate = shortTermGoalDate ? shortTermGoalDate.value : '';

    row.innerHTML = \`
        <td>
            <input type="text" name="details[\${rowIndex}][category]" placeholder="項目">
            <textarea name="details[\${rowIndex}][sub_category]" rows="2" placeholder="サブカテゴリ"></textarea>
        </td>
        <td>
            <textarea name="details[\${rowIndex}][support_goal]" rows="3"></textarea>
        </td>
        <td>
            <textarea name="details[\${rowIndex}][support_content]" rows="3"></textarea>
        </td>
        <td>
            <input type="date" name="details[\${rowIndex}][achievement_date]" value="\${achievementDate}">
        </td>
        <td>
            <textarea name="details[\${rowIndex}][staff_organization]" rows="3"></textarea>
        </td>
        <td>
            <textarea name="details[\${rowIndex}][notes]" rows="3"></textarea>
        </td>
        <td>
            <input type="number" name="details[\${rowIndex}][priority]" min="1" max="10">
        </td>
    \`;

    rowIndex++;
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
