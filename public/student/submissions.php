<?php
/**
 * 生徒用提出物管理画面
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// すべての提出物を統合
$allSubmissions = [];

// 1. 週間計画表の提出物
$stmt = $pdo->prepare("
    SELECT wps.id, wps.submission_item as title, '' as description, wps.due_date, wps.is_completed, wps.completed_at, 'weekly_plan' as source
    FROM weekly_plan_submissions wps
    INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
    WHERE wp.student_id = ?
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) $allSubmissions[] = $row;

// 2. 保護者チャット経由の提出物
$stmt = $pdo->prepare("
    SELECT sr.id, sr.title, sr.description, sr.due_date, sr.is_completed, sr.completed_at, 'guardian_chat' as source,
           sr.attachment_path, sr.attachment_original_name, sr.attachment_size
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ?
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) $allSubmissions[] = $row;

// 3. 生徒自身が登録した提出物
$stmt = $pdo->prepare("
    SELECT id, title, description, due_date, is_completed, completed_at, 'student' as source
    FROM student_submissions WHERE student_id = ?
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) $allSubmissions[] = $row;

// 日付でソート
usort($allSubmissions, function($a, $b) {
    if ($a['is_completed'] != $b['is_completed']) return $a['is_completed'] - $b['is_completed'];
    return strcmp($a['due_date'], $b['due_date']);
});

$pending = array_filter($allSubmissions, function($s) { return !$s['is_completed']; });
$completed = array_filter($allSubmissions, function($s) { return $s['is_completed']; });

$sourceLabels = ['weekly_plan' => '週間計画表', 'guardian_chat' => '保護者チャット', 'student' => '自分で登録'];

$_SESSION['user_type'] = 'student';
$_SESSION['full_name'] = $student['student_name'];

// ページ開始
$currentPage = 'submissions';
renderPageStart('student', $currentPage, '提出物管理');

$today = date('Y-m-d');
$urgentCount = 0;
$pendingCount = count($pending);
$completedCount = count($completed);

foreach ($pending as $sub) {
    $daysLeft = (strtotime($sub['due_date']) - strtotime($today)) / 86400;
    if ($daysLeft <= 3) $urgentCount++;
}
?>

<style>
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.summary-card {
    background: var(--md-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    text-align: center;
}

.summary-number {
    font-size: var(--text-title-1);
    font-weight: 700;
    color: var(--md-purple);
    margin-bottom: 5px;
}

.summary-card.urgent .summary-number { color: var(--md-red); }
.summary-card.completed .summary-number { color: var(--md-green); }

.summary-label {
    font-size: var(--text-subhead);
    color: var(--text-secondary);
}

.submission-card {
    background: var(--md-gray-6);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    border-left: 4px solid var(--md-purple);
}

.submission-card.urgent { border-left-color: var(--md-red); background: rgba(255, 59, 48, 0.05); }
.submission-card.overdue { border-left-color: var(--md-gray-4); }
.submission-card.completed { border-left-color: var(--md-green); background: rgba(52, 199, 89, 0.05); }

.submission-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.submission-title {
    font-size: var(--text-body);
    font-weight: 600;
    color: var(--text-primary);
}

.submission-badges {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.submission-badge {
    padding: 4px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-caption-1);
    font-weight: 600;
}

.submission-badge.urgent { background: var(--md-red); color: white; }
.submission-badge.overdue { background: var(--md-gray-4); color: white; }
.submission-badge.normal { background: var(--md-purple); color: white; }
.submission-badge.completed { background: var(--md-green); color: white; }
.submission-badge.source { background: var(--md-gray-5); color: var(--text-secondary); }

.submission-due {
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    margin-bottom: var(--spacing-sm);
}

.submission-actions {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-md);
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: var(--spacing-3xl);
    color: var(--text-secondary);
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: var(--spacing-md);
}

/* モーダル */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active { display: flex; }

.modal-content {
    background: var(--md-bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    max-width: 500px;
    width: 90%;
}

.modal-header {
    font-size: var(--text-title-3);
    font-weight: 600;
    margin-bottom: var(--spacing-lg);
    color: var(--text-primary);
}

.modal-actions {
    display: flex;
    gap: var(--spacing-sm);
    justify-content: flex-end;
    margin-top: var(--spacing-lg);
}

@media (max-width: 768px) {
    .submission-header { flex-direction: column; align-items: flex-start; }
    .submission-actions { flex-direction: column; }
    .submission-actions .btn { width: 100%; justify-content: center; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">提出物管理</h1>
        <p class="page-subtitle">提出物の確認と管理</p>
    </div>
    <button class="btn btn-success" onclick="openAddModal()">+ 提出物を追加</button>
</div>

<div class="summary-cards">
    <div class="summary-card urgent">
        <div class="summary-number"><?= $urgentCount ?></div>
        <div class="summary-label">期限間近</div>
    </div>
    <div class="summary-card">
        <div class="summary-number"><?= $pendingCount ?></div>
        <div class="summary-label">未提出</div>
    </div>
    <div class="summary-card completed">
        <div class="summary-number"><?= $completedCount ?></div>
        <div class="summary-label">提出済み</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-body); margin-bottom: var(--spacing-lg); color: var(--md-purple);">未提出の提出物</h2>

        <?php if (empty($pending)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">celebration</span></div>
                <p>未提出の提出物はありません</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending as $sub):
                $dueDate = strtotime($sub['due_date']);
                $today = strtotime(date('Y-m-d'));
                $daysLeft = ($dueDate - $today) / 86400;
                $cardClass = ($daysLeft < 0) ? 'overdue' : (($daysLeft <= 3) ? 'urgent' : '');
                $badgeClass = ($daysLeft < 0) ? 'overdue' : (($daysLeft <= 3) ? 'urgent' : 'normal');
                $badgeText = ($daysLeft < 0) ? '期限切れ' : (($daysLeft <= 3) ? '期限間近' : '未提出');
            ?>
                <div class="submission-card <?= $cardClass ?>">
                    <div class="submission-header">
                        <div class="submission-title"><?= htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="submission-badges">
                            <span class="submission-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                            <span class="submission-badge source"><?= $sourceLabels[$sub['source']] ?></span>
                        </div>
                    </div>
                    <div class="submission-due">
                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 提出期限: <?= date('Y年m月d日', strtotime($sub['due_date'])) ?>
                        <?php if ($daysLeft >= 0): ?>
                            （あと<?= ceil($daysLeft) ?>日）
                        <?php else: ?>
                            （<?= abs(floor($daysLeft)) ?>日超過）
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($sub['description'])): ?>
                        <div style="color: var(--text-primary); line-height: 1.6; margin-bottom: var(--spacing-sm);">
                            <?= nl2br(htmlspecialchars($sub['description'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    <?php endif; ?>
                    <div class="submission-actions">
                        <button class="btn btn-success btn-sm" onclick="completeSubmission('<?= $sub['source'] ?>', <?= $sub['id'] ?>)"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> 完了にする</button>
                        <?php if ($sub['source'] === 'student'): ?>
                            <button class="btn btn-primary btn-sm" onclick="editSubmission(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($sub['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= $sub['due_date'] ?>')"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit</span> 編集</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteSubmission(<?= $sub['id'] ?>)"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">delete</span> 削除</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($completed)): ?>
<div class="card" style="margin-top: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-body); margin-bottom: var(--spacing-lg); color: var(--md-green);">提出済みの提出物</h2>
        <?php foreach ($completed as $sub): ?>
            <div class="submission-card completed">
                <div class="submission-header">
                    <div class="submission-title"><?= htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="submission-badges">
                        <span class="submission-badge completed">提出済み</span>
                        <span class="submission-badge source"><?= $sourceLabels[$sub['source']] ?></span>
                    </div>
                </div>
                <div class="submission-due"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 提出期限: <?= date('Y年m月d日', strtotime($sub['due_date'])) ?></div>
                <div class="submission-actions">
                    <button class="btn btn-warning btn-sm" onclick="uncompleteSubmission('<?= $sub['source'] ?>', <?= $sub['id'] ?>)"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">undo</span> 未完了に戻す</button>
                    <?php if ($sub['source'] === 'student'): ?>
                        <button class="btn btn-danger btn-sm" onclick="deleteSubmission(<?= $sub['id'] ?>)"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">delete</span> 削除</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 追加/編集モーダル -->
<div id="submissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" id="modalTitle">提出物を追加</div>
        <form id="submissionForm">
            <input type="hidden" id="submissionId" name="id">
            <div class="form-group">
                <label class="form-label">提出物名 *</label>
                <input type="text" id="title" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">詳細説明</label>
                <textarea id="description" name="description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">提出期限 *</label>
                <input type="date" id="due_date" name="due_date" class="form-control" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">キャンセル</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<?php
$inlineJs = <<<JS
function openAddModal() {
    document.getElementById('modalTitle').textContent = '提出物を追加';
    document.getElementById('submissionForm').reset();
    document.getElementById('submissionId').value = '';
    document.getElementById('submissionModal').classList.add('active');
}

function editSubmission(id, title, description, dueDate) {
    document.getElementById('modalTitle').textContent = '提出物を編集';
    document.getElementById('submissionId').value = id;
    document.getElementById('title').value = title;
    document.getElementById('description').value = description;
    document.getElementById('due_date').value = dueDate;
    document.getElementById('submissionModal').classList.add('active');
}

function closeModal() {
    document.getElementById('submissionModal').classList.remove('active');
}

document.getElementById('submissionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    try {
        const response = await fetch('submissions_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('エラー: ' + (result.error || '保存に失敗しました'));
    } catch (error) {
        alert('通信エラーが発生しました');
    }
});

async function completeSubmission(source, id) {
    if (!confirm('この提出物を完了にしますか？')) return;
    const formData = new FormData();
    formData.append('action', 'complete');
    formData.append('source', source);
    formData.append('id', id);
    try {
        const response = await fetch('submissions_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('エラー: ' + (result.error || '完了に失敗しました'));
    } catch (error) {
        alert('通信エラーが発生しました');
    }
}

async function uncompleteSubmission(source, id) {
    if (!confirm('この提出物を未完了に戻しますか？')) return;
    const formData = new FormData();
    formData.append('action', 'uncomplete');
    formData.append('source', source);
    formData.append('id', id);
    try {
        const response = await fetch('submissions_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('エラー: ' + (result.error || '更新に失敗しました'));
    } catch (error) {
        alert('通信エラーが発生しました');
    }
}

async function deleteSubmission(id) {
    if (!confirm('この提出物を削除しますか？')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    try {
        const response = await fetch('submissions_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('エラー: ' + (result.error || '削除に失敗しました'));
    } catch (error) {
        alert('通信エラーが発生しました');
    }
}

document.getElementById('submissionModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
