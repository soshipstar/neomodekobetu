<?php
/**
 * 保護者用かけはし履歴閲覧ページ
 * 提出済みのスタッフ・保護者かけはしを過去にさかのぼって閲覧・印刷できる
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireLogin();
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /login.php');
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

// 保護者の子どもを取得（在籍中のみ）
$stmt = $pdo->prepare("SELECT id, student_name, support_start_date FROM students WHERE guardian_id = ? AND is_active = 1 AND status = 'active' ORDER BY student_name");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);

// 提出済みのかけはし履歴を取得
$kakehashiHistory = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT
            kp.id as period_id,
            kp.period_name,
            kp.start_date,
            kp.end_date,
            kp.submission_deadline,
            ks.id as staff_kakehashi_id,
            ks.is_submitted as staff_submitted,
            ks.submitted_at as staff_submitted_at,
            kg.id as guardian_kakehashi_id,
            kg.is_submitted as guardian_submitted,
            kg.submitted_at as guardian_submitted_at
        FROM kakehashi_periods kp
        LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = kp.student_id
        LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = kp.student_id
        WHERE kp.student_id = ?
        AND kp.is_active = 1
        AND (
            (ks.is_submitted = 1) OR (kg.is_submitted = 1)
        )
        ORDER BY kp.submission_deadline DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $kakehashiHistory = $stmt->fetchAll();
}

// 選択された生徒の名前を取得
$selectedStudentName = '';
foreach ($students as $student) {
    if ($student['id'] == $selectedStudentId) {
        $selectedStudentName = $student['student_name'];
        break;
    }
}

// ページ開始
$currentPage = 'kakehashi_history';
renderPageStart('guardian', $currentPage, 'かけはし履歴', ['classroom' => $classroom]);
?>

<style>
.selection-area {
    display: flex;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--md-gray-6);
    border-radius: var(--radius-md);
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.history-item {
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    border: 1px solid var(--md-gray-5);
    transition: all var(--duration-normal) var(--ease-out);
}

.history-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.history-period {
    font-size: var(--text-body);
    font-weight: 600;
    color: var(--text-primary);
}

.history-meta {
    display: flex;
    gap: var(--spacing-lg);
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    margin-bottom: var(--spacing-md);
}

.history-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.document-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--spacing-md);
    margin-top: var(--spacing-md);
}

.document-card {
    background: var(--md-bg-primary);
    border-radius: var(--radius-sm);
    padding: var(--spacing-lg);
    border: 1px solid var(--md-gray-5);
}

.document-card.disabled {
    opacity: 0.5;
}

.document-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.document-card-title {
    font-size: var(--text-callout);
    font-weight: 600;
    color: var(--text-primary);
}

.document-card-title.guardian {
    color: var(--md-purple);
}

.document-card-title.staff {
    color: var(--md-blue);
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-xl);
    font-size: var(--text-caption-2);
    font-weight: 600;
}

.status-submitted {
    background: var(--md-green);
    color: white;
}

.status-not-submitted {
    background: var(--md-gray-4);
    color: var(--text-secondary);
}

.document-card-meta {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-bottom: var(--spacing-md);
}

.document-card-actions {
    display: flex;
    gap: var(--spacing-sm);
}

@media (max-width: 768px) {
    .selection-area { flex-direction: column; }
    .history-header { flex-direction: column; align-items: flex-start; gap: var(--spacing-sm); }
    .history-meta { flex-direction: column; gap: 5px; }
    .document-cards { grid-template-columns: 1fr; }
    .document-card-actions { flex-direction: column; }
    .document-card-actions .btn { width: 100%; justify-content: center; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">かけはし履歴</h1>
        <p class="page-subtitle">過去のかけはしを閲覧・印刷できます</p>
    </div>
    <a href="/minimum/guardian/kakehashi.php" class="btn btn-primary">かけはし入力</a>
</div>

<?php if (empty($students)): ?>
    <div class="alert alert-info">
        お子様の情報が登録されていません。管理者にお問い合わせください。
    </div>
<?php else: ?>
    <!-- 生徒選択エリア -->
    <div class="selection-area">
        <div class="form-group" style="flex: 1;">
            <label class="form-label">お子様を選択</label>
            <select id="studentSelect" class="form-control" onchange="changeStudent()">
                <?php foreach ($students as $student): ?>
                    <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($student['student_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($kakehashiHistory)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: var(--spacing-3xl);">
                <div style="font-size: 64px; margin-bottom: var(--spacing-lg);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span></div>
                <p style="color: var(--text-secondary);">
                    <?= htmlspecialchars($selectedStudentName) ?>さんの提出済みかけはしはまだありません
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($kakehashiHistory as $item): ?>
                <div class="history-item">
                    <div class="history-header">
                        <div class="history-period">
                            <?= getIndividualSupportPlanStartMonth($item) ?>開始分
                        </div>
                    </div>

                    <div class="history-meta">
                        <div class="history-meta-item">
                            <span><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span></span>
                            <span>対象期間: <?= date('Y/m/d', strtotime($item['start_date'])) ?> ～ <?= date('Y/m/d', strtotime($item['end_date'])) ?></span>
                        </div>
                        <div class="history-meta-item">
                            <span>⏰</span>
                            <span>提出期限: <?= date('Y年n月j日', strtotime($item['submission_deadline'])) ?></span>
                        </div>
                    </div>

                    <div class="document-cards">
                        <!-- 保護者用かけはし -->
                        <div class="document-card <?= !$item['guardian_submitted'] ? 'disabled' : '' ?>">
                            <div class="document-card-header">
                                <span class="document-card-title guardian">保護者</span>
                                <?php if ($item['guardian_submitted']): ?>
                                    <span class="status-badge status-submitted">提出済み</span>
                                <?php else: ?>
                                    <span class="status-badge status-not-submitted">未提出</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['guardian_submitted']): ?>
                                <div class="document-card-meta">
                                    提出日: <?= date('Y/m/d H:i', strtotime($item['guardian_submitted_at'])) ?>
                                </div>
                                <div class="document-card-actions">
                                    <a href="/minimum/guardian/kakehashi_history_view.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $item['period_id'] ?>&type=guardian"
                                       class="btn btn-secondary btn-sm">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">visibility</span> 表示
                                    </a>
                                    <a href="/minimum/guardian/kakehashi_history_view.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $item['period_id'] ?>&type=guardian"
                                       class="btn btn-primary btn-sm"
                                       target="_blank">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">print</span> 印刷
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="document-card-meta">
                                    まだ提出されていません
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- スタッフ用かけはし -->
                        <div class="document-card <?= !$item['staff_submitted'] ? 'disabled' : '' ?>">
                            <div class="document-card-header">
                                <span class="document-card-title staff">事業所</span>
                                <?php if ($item['staff_submitted']): ?>
                                    <span class="status-badge status-submitted">提出済み</span>
                                <?php else: ?>
                                    <span class="status-badge status-not-submitted">未提出</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['staff_submitted']): ?>
                                <div class="document-card-meta">
                                    提出日: <?= date('Y/m/d H:i', strtotime($item['staff_submitted_at'])) ?>
                                </div>
                                <div class="document-card-actions">
                                    <a href="/minimum/guardian/kakehashi_history_view.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $item['period_id'] ?>&type=staff"
                                       class="btn btn-secondary btn-sm">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">visibility</span> 表示
                                    </a>
                                    <a href="/minimum/guardian/kakehashi_history_view.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $item['period_id'] ?>&type=staff"
                                       class="btn btn-info btn-sm"
                                       target="_blank">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">print</span> 印刷
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="document-card-meta">
                                    まだ提出されていません
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$inlineJs = <<<JS
function changeStudent() {
    const studentId = document.getElementById('studentSelect').value;
    window.location.href = '/minimum/guardian/kakehashi_history.php?student_id=' + studentId;
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
