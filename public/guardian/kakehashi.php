<?php
/**
 * 保護者用かけはし入力ページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /login.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// 保護者の子どもを取得（在籍中のみ）
$stmt = $pdo->prepare("SELECT id, student_name, support_start_date FROM students WHERE guardian_id = ? AND is_active = 1 AND status = 'active' ORDER BY student_name");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? null;

// 選択された生徒の有効な期間を取得
$activePeriods = [];
$generationMessage = null;
if ($selectedStudentId) {
    // まず生徒のsupport_plan_start_typeを確認
    $stmt = $pdo->prepare("SELECT student_name, support_start_date, support_plan_start_type FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $student = $stmt->fetch();
    $supportPlanStartType = $student['support_plan_start_type'] ?? 'current';

    // 「次回の期間から作成する」設定の場合は、既存のかけはしも非表示
    if ($supportPlanStartType === 'next') {
        $generationMessage = 'お子様は「次回の期間から個別支援計画を作成する」設定になっています。現在は連絡帳のみご利用いただけます。';
    } else {
        // 次のかけはし期間を自動生成
        try {
            if ($student && shouldGenerateNextKakehashi($pdo, $selectedStudentId)) {
                generateNextKakehashiPeriod($pdo, $selectedStudentId, $student['student_name']);
            }
        } catch (Exception $e) {
            error_log("Error auto-generating next kakehashi period: " . $e->getMessage());
        }

        // 提出期限が今日から1ヶ月以内の期間のみ表示
        $stmt = $pdo->prepare("
            SELECT kp.*, kg.is_hidden
            FROM kakehashi_periods kp
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = kp.student_id
            WHERE kp.student_id = ?
            AND kp.is_active = 1
            AND (kg.is_hidden = 0 OR kg.is_hidden IS NULL)
            AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            ORDER BY kp.submission_deadline DESC
        ");
        $stmt->execute([$selectedStudentId]);
        $activePeriods = $stmt->fetchAll();

        // かけはし期間が存在しない場合は自動生成
        if (empty($activePeriods)) {
            if ($student && $student['support_start_date']) {
                $supportStartDate = new DateTime($student['support_start_date']);
                $today = new DateTime();
                $generationLimit = clone $today;
                $generationLimit->modify('+1 month');
                $firstDeadline = clone $supportStartDate;
                $firstDeadline->modify('-1 day');

                if ($firstDeadline > $generationLimit) {
                    $generationMessage = '支援開始日（' . $supportStartDate->format('Y年n月j日') . '）が' . $generationLimit->format('Y年n月j日') . 'より先のため、かけはし期間はまだ作成されていません。';
                } else {
                    try {
                        generateKakehashiPeriodsForStudent($pdo, $selectedStudentId, $student['support_start_date']);
                        $stmt->execute([$selectedStudentId]);
                        $activePeriods = $stmt->fetchAll();
                        if (empty($activePeriods)) {
                            $generationMessage = 'かけはし期間の自動生成に失敗しました。スタッフにお問い合わせください。';
                        }
                    } catch (Exception $e) {
                        $generationMessage = 'かけはし期間の自動生成でエラーが発生しました。スタッフにお問い合わせください。';
                    }
                }
            } else {
                $generationMessage = 'お子様の支援開始日が設定されていないため、かけはし期間を自動生成できませんでした。スタッフに支援開始日の設定をご依頼ください。';
            }
        }
    }
}

// 選択された期間
$selectedPeriodId = $_GET['period_id'] ?? null;

// 既存のかけはしデータを取得
$kakehashiData = null;
if ($selectedStudentId && $selectedPeriodId) {
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_guardian WHERE student_id = ? AND period_id = ?");
    $stmt->execute([$selectedStudentId, $selectedPeriodId]);
    $kakehashiData = $stmt->fetch();

    if ($kakehashiData && $kakehashiData['is_hidden']) {
        header('Location: kakehashi.php');
        exit;
    }
}

// 選択された期間の情報
$selectedPeriod = null;
if ($selectedPeriodId) {
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$selectedPeriodId]);
    $selectedPeriod = $stmt->fetch();
}

// ページ開始
$currentPage = 'kakehashi';
renderPageStart('guardian', $currentPage, 'かけはし入力', ['classroom' => $classroom]);
?>

<style>
.selection-area {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-lg);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
}

.deadline-countdown {
    background: linear-gradient(135deg, var(--md-blue) 0%, var(--md-purple) 100%);
    color: white;
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.deadline-countdown.urgent {
    background: linear-gradient(135deg, var(--md-red) 0%, #ff6b6b 100%);
}

.deadline-countdown.warning {
    background: linear-gradient(135deg, var(--md-orange) 0%, #ff9800 100%);
}

.countdown-display {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: var(--spacing-md) 0;
}

.countdown-item {
    text-align: center;
}

.countdown-number {
    font-size: 48px;
    font-weight: bold;
    line-height: 1;
}

.countdown-label {
    font-size: var(--text-subhead);
    opacity: 0.9;
}

.period-info {
    background: rgba(0, 122, 255, 0.1);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--md-blue);
}

.section-title {
    font-size: var(--text-headline);
    font-weight: 600;
    color: var(--md-purple);
    margin: var(--spacing-xl) 0 var(--spacing-md) 0;
    padding-bottom: var(--spacing-sm);
    border-bottom: 2px solid var(--md-purple);
}

.domains-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-md);
}

.button-group {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
    justify-content: flex-end;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-caption-1);
    font-weight: 600;
}

.status-draft {
    background: var(--md-orange);
    color: white;
}

.status-submitted {
    background: var(--md-green);
    color: white;
}

@media (max-width: 768px) {
    .selection-area {
        flex-direction: column;
    }
    .countdown-number {
        font-size: 32px;
    }
    .button-group {
        flex-direction: column;
    }
    .button-group .btn {
        width: 100%;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">かけはし入力</h1>
        <p class="page-subtitle">お子様の目標と課題を入力してください</p>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="alert alert-info">
        お子様の情報が登録されていません。管理者にお問い合わせください。
    </div>
<?php else: ?>
    <!-- 生徒選択 -->
    <div class="selection-area">
        <div class="form-group" style="flex: 1;">
            <label class="form-label">お子様を選択 *</label>
            <select class="form-control" id="studentSelect" onchange="changeStudent()">
                <option value="">-- お子様を選択してください --</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($student['student_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($selectedStudentId && empty($activePeriods)): ?>
        <div class="alert alert-info">
            <?= nl2br(htmlspecialchars($generationMessage ?? 'かけはし期間が見つかりません。')) ?>
        </div>
    <?php elseif ($selectedStudentId && !empty($activePeriods)): ?>
        <!-- 期間選択 -->
        <div class="selection-area">
            <div class="form-group" style="flex: 1;">
                <label class="form-label">かけはし提出期限を選択 *</label>
                <select class="form-control" id="periodSelect" onchange="changePeriod()">
                    <option value="">-- 提出期限を選択してください --</option>
                    <?php foreach ($activePeriods as $period): ?>
                        <option value="<?= $period['id'] ?>" <?= $period['id'] == $selectedPeriodId ? 'selected' : '' ?>>
                            [<?= getIndividualSupportPlanStartMonth($period) ?>開始] 提出期限: <?= date('Y年n月j日', strtotime($period['submission_deadline'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selectedPeriod): ?>
        <?php
        $deadlineTimestamp = strtotime($selectedPeriod['submission_deadline'] . ' 23:59:59');
        $now = time();
        $timeLeft = $deadlineTimestamp - $now;
        $daysLeft = floor($timeLeft / 86400);
        $hoursLeft = floor(($timeLeft % 86400) / 3600);
        $minutesLeft = floor(($timeLeft % 3600) / 60);

        $countdownClass = '';
        if ($daysLeft <= 3) $countdownClass = 'urgent';
        elseif ($daysLeft <= 7) $countdownClass = 'warning';

        $isSubmitted = $kakehashiData && $kakehashiData['is_submitted'];
        ?>

        <?php if (!$isSubmitted && $timeLeft > 0): ?>
            <div class="deadline-countdown <?= $countdownClass ?>">
                <div style="font-size: var(--text-callout); margin-bottom: var(--spacing-sm);">提出期限まで</div>
                <div class="countdown-display">
                    <div class="countdown-item">
                        <div class="countdown-number"><?= $daysLeft ?></div>
                        <div class="countdown-label">日</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-number"><?= $hoursLeft ?></div>
                        <div class="countdown-label">時間</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-number"><?= $minutesLeft ?></div>
                        <div class="countdown-label">分</div>
                    </div>
                </div>
                <div style="font-size: var(--text-headline); font-weight: 600;">
                    提出期限: <?= date('Y年n月j日', strtotime($selectedPeriod['submission_deadline'])) ?>
                </div>
            </div>
        <?php elseif (!$isSubmitted && $timeLeft <= 0): ?>
            <div class="deadline-countdown urgent">
                <div style="font-size: var(--text-headline); font-weight: 600;">提出期限を過ぎています</div>
                <div>提出期限: <?= date('Y年n月j日', strtotime($selectedPeriod['submission_deadline'])) ?></div>
            </div>
        <?php endif; ?>

        <div class="period-info">
            <p><strong>個別支援計画:</strong> <?= getIndividualSupportPlanStartMonth($selectedPeriod) ?>開始分</p>
            <p><strong>対象期間:</strong> <?= date('Y年m月d日', strtotime($selectedPeriod['start_date'])) ?> ～ <?= date('Y年m月d日', strtotime($selectedPeriod['end_date'])) ?></p>
            <p><strong>提出期限:</strong> <?= date('Y年m月d日', strtotime($selectedPeriod['submission_deadline'])) ?></p>
            <p>
                <strong>状態:</strong>
                <?php if ($isSubmitted): ?>
                    <span class="status-badge status-submitted">提出済み</span>
                    <small>（提出日時: <?= date('Y年m月d日 H:i', strtotime($kakehashiData['submitted_at'])) ?>）</small>
                <?php else: ?>
                    <span class="status-badge status-draft">下書き</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- かけはし入力フォーム -->
        <form method="POST" action="kakehashi_save.php" id="kakehashiForm">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
            <input type="hidden" name="action" id="formAction" value="save">

            <div class="section-title">本人の願い</div>
            <div class="form-group">
                <label class="form-label">お子様が望んでいること、なりたい姿</label>
                <textarea name="student_wish" class="form-control" rows="4" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['student_wish'] ?? '' ?></textarea>
            </div>

            <div class="section-title">家庭での願い</div>
            <div class="form-group">
                <label class="form-label">家庭で気になっていること、取り組みたいこと</label>
                <textarea name="home_challenges" class="form-control" rows="4" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['home_challenges'] ?? '' ?></textarea>
            </div>

            <div class="section-title">目標設定</div>
            <div class="form-group">
                <label class="form-label">短期目標（6か月）</label>
                <textarea name="short_term_goal" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['short_term_goal'] ?? '' ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">長期目標（1年以上）</label>
                <textarea name="long_term_goal" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['long_term_goal'] ?? '' ?></textarea>
            </div>

            <div class="section-title">五領域の課題</div>
            <div class="domains-grid">
                <div class="form-group">
                    <label class="form-label">健康・生活</label>
                    <textarea name="domain_health_life" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['domain_health_life'] ?? '' ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">運動・感覚</label>
                    <textarea name="domain_motor_sensory" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['domain_motor_sensory'] ?? '' ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">認知・行動</label>
                    <textarea name="domain_cognitive_behavior" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['domain_cognitive_behavior'] ?? '' ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">言語・コミュニケーション</label>
                    <textarea name="domain_language_communication" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['domain_language_communication'] ?? '' ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">人間関係・社会性</label>
                    <textarea name="domain_social_relations" class="form-control" rows="3" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['domain_social_relations'] ?? '' ?></textarea>
                </div>
            </div>

            <div class="section-title">その他の課題</div>
            <div class="form-group">
                <label class="form-label">その他、お伝えしたいこと</label>
                <textarea name="other_challenges" class="form-control" rows="4" <?= $isSubmitted ? 'readonly' : '' ?>><?= $kakehashiData['other_challenges'] ?? '' ?></textarea>
            </div>

            <?php if (!$isSubmitted): ?>
                <div class="button-group">
                    <button type="submit" class="btn btn-secondary" onclick="setAction('save')">下書き保存</button>
                    <button type="submit" class="btn btn-primary" onclick="return confirmSubmit()">提出する</button>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    このかけはしは提出済みです。内容の確認のみ可能です。
                </div>
            <?php endif; ?>

            <?php if ($kakehashiData): ?>
                <div class="button-group">
                    <a href="kakehashi_pdf.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $selectedPeriodId ?>" target="_blank" class="btn btn-info">
                        PDF印刷用ダウンロード
                    </a>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php
$inlineJs = <<<JS
function changeStudent() {
    const studentId = document.getElementById('studentSelect').value;
    if (studentId) {
        window.location.href = 'kakehashi.php?student_id=' + studentId;
    }
}

function changePeriod() {
    const studentId = document.getElementById('studentSelect').value;
    const periodId = document.getElementById('periodSelect').value;
    if (studentId && periodId) {
        window.location.href = 'kakehashi.php?student_id=' + studentId + '&period_id=' + periodId;
    }
}

function setAction(action) {
    document.getElementById('formAction').value = action;
}

function confirmSubmit() {
    setAction('submit');
    return confirm('提出すると内容の変更ができなくなります。提出してよろしいですか？');
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
