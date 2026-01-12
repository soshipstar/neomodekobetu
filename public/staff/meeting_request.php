<?php
/**
 * 面談予約リクエスト作成ページ
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_GET['student_id'] ?? null;
$planId = $_GET['plan_id'] ?? null;
$monitoringId = $_GET['monitoring_id'] ?? null;

if (!$studentId) {
    header('Location: chat.php');
    exit;
}

// 生徒情報を取得
$stmt = $pdo->prepare("
    SELECT s.id, s.student_name, s.guardian_id, u.full_name as guardian_name, s.classroom_id
    FROM students s
    LEFT JOIN users u ON s.guardian_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: chat.php');
    exit;
}

// 関連する計画を取得（未確認のもの）
$stmt = $pdo->prepare("
    SELECT id, student_name, created_date
    FROM individual_support_plans
    WHERE student_id = ? AND is_draft = 0
    ORDER BY created_date DESC
    LIMIT 10
");
$stmt->execute([$studentId]);
$plans = $stmt->fetchAll();

// 関連するモニタリングを取得
$stmt = $pdo->prepare("
    SELECT id, monitoring_date
    FROM monitoring_records
    WHERE student_id = ? AND is_draft = 0
    ORDER BY monitoring_date DESC
    LIMIT 10
");
$stmt->execute([$studentId]);
$monitorings = $stmt->fetchAll();

// エラー・成功メッセージ
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

$currentPage = 'meeting_request';
renderPageStart('staff', $currentPage, '面談予約');
?>

<style>
.meeting-form-container {
    max-width: 700px;
    margin: 0 auto;
    padding: var(--spacing-lg);
}

.form-card {
    background: var(--md-bg-tertiary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    box-shadow: var(--shadow-md);
}

.form-header {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--md-gray-5);
}

.form-title {
    font-size: var(--text-title-2);
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.student-info {
    margin-top: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
}

.student-info-label {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.student-info-name {
    font-size: var(--text-body);
    font-weight: 600;
    color: var(--text-primary);
}

.form-group {
    margin-bottom: var(--spacing-xl);
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: var(--spacing-sm);
    color: var(--text-primary);
}

.form-label .required {
    color: var(--md-red);
    margin-left: var(--spacing-xs);
}

.form-hint {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
    margin-top: var(--spacing-xs);
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-md);
    font-size: var(--text-body);
    background: var(--md-bg-primary);
    color: var(--text-primary);
    transition: border-color var(--duration-fast);
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--md-blue);
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
}

.datetime-group {
    display: flex;
    gap: var(--spacing-md);
    align-items: center;
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
}

.datetime-group .form-input {
    flex: 1;
    min-width: 200px;
}

.datetime-label {
    font-weight: 500;
    color: var(--text-secondary);
    min-width: 80px;
}

.purpose-options {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.purpose-option {
    flex: 1;
    min-width: 150px;
}

.purpose-option input {
    display: none;
}

.purpose-option label {
    display: block;
    padding: var(--spacing-md);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition: all var(--duration-fast);
}

.purpose-option input:checked + label {
    border-color: var(--md-blue);
    background: rgba(0, 122, 255, 0.1);
    color: var(--md-blue);
}

.purpose-option label:hover {
    border-color: var(--md-gray-3);
}

.form-actions {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-2xl);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--md-gray-5);
}

.btn {
    padding: var(--spacing-md) var(--spacing-xl);
    border-radius: var(--radius-md);
    font-size: var(--text-body);
    font-weight: 600;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-sm);
    text-decoration: none;
}

.btn-primary {
    background: var(--md-blue);
    color: white;
}

.btn-primary:hover {
    opacity: 0.9;
}

.btn-secondary {
    background: var(--md-gray-4);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: var(--md-gray-3);
}

.alert {
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.alert-error {
    background: rgba(255, 59, 48, 0.1);
    color: var(--md-red);
    border: 1px solid var(--md-red);
}

.alert-success {
    background: rgba(52, 199, 89, 0.1);
    color: var(--md-green);
    border: 1px solid var(--md-green);
}
</style>

<div class="meeting-form-container">
    <div class="form-card">
        <div class="form-header">
            <h1 class="form-title">
                <span class="material-symbols-outlined">calendar_month</span>
                面談予約リクエスト
            </h1>
            <div class="student-info">
                <div class="student-info-label">対象児童</div>
                <div class="student-info-name"><?= htmlspecialchars($student['student_name']) ?>さん</div>
                <div class="student-info-label" style="margin-top: var(--spacing-sm);">保護者</div>
                <div class="student-info-name"><?= htmlspecialchars($student['guardian_name'] ?? '未登録') ?></div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="meeting_request_save.php" method="POST">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <input type="hidden" name="guardian_id" value="<?= $student['guardian_id'] ?>">
            <input type="hidden" name="classroom_id" value="<?= $student['classroom_id'] ?>">

            <div class="form-group">
                <label class="form-label">面談目的<span class="required">*</span></label>
                <div class="purpose-options">
                    <div class="purpose-option">
                        <input type="radio" name="purpose" id="purpose_plan" value="個別支援計画" <?= $planId ? 'checked' : '' ?>>
                        <label for="purpose_plan"><span class="material-symbols-outlined">description</span><br>個別支援計画</label>
                    </div>
                    <div class="purpose-option">
                        <input type="radio" name="purpose" id="purpose_monitoring" value="モニタリング" <?= $monitoringId ? 'checked' : '' ?>>
                        <label for="purpose_monitoring"><span class="material-symbols-outlined">monitoring</span><br>モニタリング</label>
                    </div>
                    <div class="purpose-option">
                        <input type="radio" name="purpose" id="purpose_other" value="その他">
                        <label for="purpose_other"><span class="material-symbols-outlined">more_horiz</span><br>その他</label>
                    </div>
                </div>
            </div>

            <?php if (!empty($plans)): ?>
            <div class="form-group" id="planSelectGroup" style="display: none;">
                <label class="form-label">関連する個別支援計画</label>
                <select name="related_plan_id" class="form-select">
                    <option value="">選択してください</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>" <?= $planId == $plan['id'] ? 'selected' : '' ?>>
                            <?= date('Y年m月d日', strtotime($plan['created_date'])) ?> 作成
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (!empty($monitorings)): ?>
            <div class="form-group" id="monitoringSelectGroup" style="display: none;">
                <label class="form-label">関連するモニタリング</label>
                <select name="related_monitoring_id" class="form-select">
                    <option value="">選択してください</option>
                    <?php foreach ($monitorings as $monitoring): ?>
                        <option value="<?= $monitoring['id'] ?>" <?= $monitoringId == $monitoring['id'] ? 'selected' : '' ?>>
                            <?= date('Y年m月d日', strtotime($monitoring['monitoring_date'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">面談の詳細・備考</label>
                <textarea name="purpose_detail" class="form-textarea" placeholder="面談の目的や話し合いたい内容などがあればご記入ください"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">面談候補日時<span class="required">*</span></label>
                <p class="form-hint">保護者に提案する面談候補日時を最大3つ選択してください</p>

                <div class="datetime-group">
                    <span class="datetime-label">候補1<span class="required">*</span></span>
                    <input type="datetime-local" name="candidate_date1" class="form-input" required>
                </div>
                <div class="datetime-group">
                    <span class="datetime-label">候補2</span>
                    <input type="datetime-local" name="candidate_date2" class="form-input">
                </div>
                <div class="datetime-group">
                    <span class="datetime-label">候補3</span>
                    <input type="datetime-local" name="candidate_date3" class="form-input">
                </div>
            </div>

            <div class="form-actions">
                <a href="chat.php?student_id=<?= $studentId ?>" class="btn btn-secondary">
                    <span class="material-symbols-outlined">arrow_back</span> キャンセル
                </a>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">send</span> 面談予約を送信
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const purposeRadios = document.querySelectorAll('input[name="purpose"]');
    const planGroup = document.getElementById('planSelectGroup');
    const monitoringGroup = document.getElementById('monitoringSelectGroup');

    function updateVisibility() {
        const selected = document.querySelector('input[name="purpose"]:checked');
        if (planGroup) planGroup.style.display = 'none';
        if (monitoringGroup) monitoringGroup.style.display = 'none';

        if (selected) {
            if (selected.value === '個別支援計画' && planGroup) {
                planGroup.style.display = 'block';
            } else if (selected.value === 'モニタリング' && monitoringGroup) {
                monitoringGroup.style.display = 'block';
            }
        }
    }

    purposeRadios.forEach(radio => {
        radio.addEventListener('change', updateVisibility);
    });

    updateVisibility();
});
</script>

<?php renderPageEnd(); ?>
