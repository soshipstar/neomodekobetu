<?php
/**
 * 利用者一括登録 - AI解析結果確認・編集ページ
 * AIで解析したデータを確認・編集して登録する
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
require_once __DIR__ . '/../../includes/student_helper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

// セッションからAI解析結果を取得
if (!isset($_SESSION['bulk_register_data']) || empty($_SESSION['bulk_register_data']['ai_parsed'])) {
    header('Location: bulk_register.php');
    exit;
}

$parseResult = $_SESSION['bulk_register_data'];
$hasErrors = !empty($parseResult['errors']);
$guardianCount = count($parseResult['guardians']);
$studentCount = count($parseResult['students']);

$role = 'staff';
$currentPage = 'bulk_register';

renderPageStart($role, $currentPage, '利用者一括登録 - AI解析結果', [
    'classroom' => $classroom
]);
?>

<style>
.edit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.edit-table th, .edit-table td {
    border: 1px solid var(--border-primary);
    padding: 8px;
    vertical-align: middle;
}
.edit-table th {
    background: var(--md-bg-tertiary);
    font-weight: 600;
    white-space: nowrap;
}
.edit-table input[type="text"],
.edit-table input[type="date"],
.edit-table input[type="email"],
.edit-table select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-sm);
    font-size: 13px;
}
.edit-table input[type="text"]:focus,
.edit-table input[type="date"]:focus,
.edit-table input[type="email"]:focus,
.edit-table select:focus {
    outline: none;
    border-color: var(--md-blue);
    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
}
.schedule-checkboxes {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.schedule-checkboxes label {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 4px 6px;
    background: var(--md-bg-tertiary);
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-size: 12px;
}
.schedule-checkboxes input[type="checkbox"] {
    margin: 0;
}
.schedule-checkboxes label:has(input:checked) {
    background: var(--md-blue);
    color: white;
}
.required-mark {
    color: var(--md-red);
    margin-left: 2px;
}
.row-number {
    color: var(--text-tertiary);
    font-size: 11px;
}
.optional-field {
    background: var(--md-bg-secondary);
}
.ai-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 8px;
}
.delete-row-btn {
    background: var(--md-red);
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-size: 11px;
}
.delete-row-btn:hover {
    opacity: 0.8;
}
.add-row-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--md-green);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    margin-top: var(--spacing-md);
}
.add-row-btn:hover {
    opacity: 0.9;
}
.action-cell {
    text-align: center;
    width: 60px;
}
.guardian-select {
    min-width: 120px;
}
</style>

<div class="page-header">
    <h1>利用者一括登録 - AI解析結果 <span class="ai-badge">AI解析</span></h1>
    <p class="page-description">AIが抽出した情報を確認・編集してください。</p>
</div>

<?php if (!empty($parseResult['errors'])): ?>
    <div class="alert alert-danger">
        <strong>エラー</strong>
        <ul style="margin: var(--spacing-sm) 0 0 var(--spacing-lg);">
            <?php foreach ($parseResult['errors'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($parseResult['warnings'])): ?>
    <div class="alert alert-warning">
        <strong>確認</strong>
        <ul style="margin: var(--spacing-sm) 0 0 var(--spacing-lg);">
            <?php foreach ($parseResult['warnings'] as $warning): ?>
                <li><?= htmlspecialchars($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>抽出結果サマリー</h2>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: var(--spacing-xl); flex-wrap: wrap;">
            <div style="text-align: center; padding: var(--spacing-lg); background: var(--md-bg-tertiary); border-radius: var(--radius-md); min-width: 150px;">
                <div style="font-size: var(--text-title-1); font-weight: bold; color: var(--md-blue);"><?= $guardianCount ?></div>
                <div style="color: var(--text-secondary);">保護者</div>
            </div>
            <div style="text-align: center; padding: var(--spacing-lg); background: var(--md-bg-tertiary); border-radius: var(--radius-md); min-width: 150px;">
                <div style="font-size: var(--text-title-1); font-weight: bold; color: var(--md-green);"><?= $studentCount ?></div>
                <div style="color: var(--text-secondary);">生徒</div>
            </div>
        </div>
        <p style="margin-top: var(--spacing-md); color: var(--text-secondary); font-size: var(--text-footnote);">
            <span class="required-mark">*</span> は必須項目です。AIが正しく抽出できなかった項目は手動で修正してください。
        </p>
    </div>
</div>

<?php if (!$hasErrors && $guardianCount > 0): ?>
<form method="POST" action="bulk_register_execute.php" id="bulkRegisterForm">
    <?= csrfTokenField() ?>

    <div class="card" style="margin-top: var(--spacing-xl);">
        <div class="card-header">
            <h2>保護者情報</h2>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="edit-table" id="guardianTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>保護者氏名<span class="required-mark">*</span></th>
                        <th>メールアドレス</th>
                        <th class="action-cell">操作</th>
                    </tr>
                </thead>
                <tbody id="guardianTableBody">
                    <?php $gIndex = 0; foreach ($parseResult['guardians'] as $gId => $guardian): ?>
                    <tr data-guardian-id="<?= htmlspecialchars($gId) ?>">
                        <td class="row-number"><?= $gIndex + 1 ?></td>
                        <td>
                            <input type="hidden" name="guardians[<?= $gIndex ?>][id]" value="<?= htmlspecialchars($gId) ?>">
                            <input type="text" name="guardians[<?= $gIndex ?>][name]" value="<?= htmlspecialchars($guardian['name']) ?>" required class="guardian-name-input">
                        </td>
                        <td class="optional-field">
                            <input type="email" name="guardians[<?= $gIndex ?>][email]" value="<?= htmlspecialchars($guardian['email'] ?? '') ?>" placeholder="任意">
                        </td>
                        <td class="action-cell">
                            <button type="button" class="delete-row-btn" onclick="deleteGuardianRow(this)">削除</button>
                        </td>
                    </tr>
                    <?php $gIndex++; endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="add-row-btn" onclick="addGuardianRow()">＋ 保護者を追加</button>
        </div>
    </div>

    <div class="card" style="margin-top: var(--spacing-xl);">
        <div class="card-header">
            <h2>生徒情報</h2>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="edit-table" id="studentTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>生徒氏名<span class="required-mark">*</span></th>
                        <th>保護者<span class="required-mark">*</span></th>
                        <th>生年月日<span class="required-mark">*</span></th>
                        <th>学年調整</th>
                        <th>支援開始日</th>
                        <th>通所曜日</th>
                        <th class="action-cell">操作</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <?php foreach ($parseResult['students'] as $idx => $student): ?>
                    <tr>
                        <td class="row-number"><?= $idx + 1 ?></td>
                        <td>
                            <input type="text" name="students[<?= $idx ?>][name]" value="<?= htmlspecialchars($student['name']) ?>" required>
                        </td>
                        <td>
                            <select name="students[<?= $idx ?>][guardian_id]" class="guardian-select" required>
                                <?php foreach ($parseResult['guardians'] as $gId => $guardian): ?>
                                <option value="<?= htmlspecialchars($gId) ?>" <?= $student['guardian_id'] === $gId ? 'selected' : '' ?>><?= htmlspecialchars($guardian['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="date" name="students[<?= $idx ?>][birth_date]" value="<?= htmlspecialchars($student['birth_date']) ?>" required>
                        </td>
                        <td style="width: 100px;">
                            <select name="students[<?= $idx ?>][grade_adjustment]">
                                <option value="-2" <?= ($student['grade_adjustment'] ?? 0) == -2 ? 'selected' : '' ?>>-2</option>
                                <option value="-1" <?= ($student['grade_adjustment'] ?? 0) == -1 ? 'selected' : '' ?>>-1</option>
                                <option value="0" <?= ($student['grade_adjustment'] ?? 0) == 0 ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= ($student['grade_adjustment'] ?? 0) == 1 ? 'selected' : '' ?>>+1</option>
                                <option value="2" <?= ($student['grade_adjustment'] ?? 0) == 2 ? 'selected' : '' ?>>+2</option>
                            </select>
                        </td>
                        <td class="optional-field">
                            <input type="date" name="students[<?= $idx ?>][support_start_date]" value="<?= htmlspecialchars($student['support_start_date'] ?? '') ?>">
                        </td>
                        <td>
                            <div class="schedule-checkboxes">
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_monday]" value="1" <?= !empty($student['scheduled_monday']) ? 'checked' : '' ?>> 月</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_tuesday]" value="1" <?= !empty($student['scheduled_tuesday']) ? 'checked' : '' ?>> 火</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_wednesday]" value="1" <?= !empty($student['scheduled_wednesday']) ? 'checked' : '' ?>> 水</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_thursday]" value="1" <?= !empty($student['scheduled_thursday']) ? 'checked' : '' ?>> 木</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_friday]" value="1" <?= !empty($student['scheduled_friday']) ? 'checked' : '' ?>> 金</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_saturday]" value="1" <?= !empty($student['scheduled_saturday']) ? 'checked' : '' ?>> 土</label>
                            </div>
                        </td>
                        <td class="action-cell">
                            <button type="button" class="delete-row-btn" onclick="deleteStudentRow(this)">削除</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="add-row-btn" onclick="addStudentRow()">＋ 生徒を追加</button>
        </div>
    </div>

    <div class="card" style="margin-top: var(--spacing-xl); background: var(--md-yellow-light, #fffde7); border: 1px solid var(--md-yellow, #ffc107);">
        <div class="card-body">
            <p style="margin: 0; text-align: center; font-size: var(--text-body);">
                <strong>この保護者、生徒を登録しますがよろしいですか？</strong>
            </p>
        </div>
    </div>

    <div class="form-actions" style="margin-top: var(--spacing-xl); display: flex; gap: var(--spacing-md); justify-content: center;">
        <a href="bulk_register.php" class="btn btn-secondary">キャンセル</a>
        <button type="submit" class="btn btn-primary">登録する</button>
    </div>
</form>
<?php else: ?>
<div class="form-actions" style="margin-top: var(--spacing-xl);">
    <a href="bulk_register.php" class="btn btn-secondary">戻る</a>
</div>
<?php endif; ?>

<script>
let guardianCounter = <?= $guardianCount ?>;
let studentCounter = <?= $studentCount ?>;

// 保護者行を削除
function deleteGuardianRow(btn) {
    const row = btn.closest('tr');
    const guardianId = row.dataset.guardianId;

    // この保護者に紐づく生徒がいるか確認
    const studentSelects = document.querySelectorAll('#studentTableBody select.guardian-select');
    let hasStudents = false;
    studentSelects.forEach(select => {
        if (select.value === guardianId) {
            hasStudents = true;
        }
    });

    if (hasStudents) {
        alert('この保護者に紐づく生徒がいます。先に生徒を削除するか、別の保護者に変更してください。');
        return;
    }

    if (confirm('この保護者を削除しますか？')) {
        row.remove();
        updateGuardianIndices();
        updateGuardianSelects();
    }
}

// 生徒行を削除
function deleteStudentRow(btn) {
    const row = btn.closest('tr');
    if (confirm('この生徒を削除しますか？')) {
        row.remove();
        updateStudentIndices();
    }
}

// 保護者行を追加
function addGuardianRow() {
    const tbody = document.getElementById('guardianTableBody');
    const newId = 'G_NEW_' + guardianCounter;
    const rowCount = tbody.querySelectorAll('tr').length;

    const tr = document.createElement('tr');
    tr.dataset.guardianId = newId;
    tr.innerHTML = `
        <td class="row-number">${rowCount + 1}</td>
        <td>
            <input type="hidden" name="guardians[${rowCount}][id]" value="${newId}">
            <input type="text" name="guardians[${rowCount}][name]" value="" required class="guardian-name-input" placeholder="保護者氏名">
        </td>
        <td class="optional-field">
            <input type="email" name="guardians[${rowCount}][email]" value="" placeholder="任意">
        </td>
        <td class="action-cell">
            <button type="button" class="delete-row-btn" onclick="deleteGuardianRow(this)">削除</button>
        </td>
    `;
    tbody.appendChild(tr);
    guardianCounter++;

    // 保護者セレクトを更新
    updateGuardianSelects();

    // 新しい行にフォーカス
    tr.querySelector('input[type="text"]').focus();
}

// 生徒行を追加
function addStudentRow() {
    const tbody = document.getElementById('studentTableBody');
    const rowCount = tbody.querySelectorAll('tr').length;

    // 保護者のオプションを取得
    const guardianOptions = getGuardianOptions();
    if (!guardianOptions) {
        alert('先に保護者を追加してください。');
        return;
    }

    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="row-number">${rowCount + 1}</td>
        <td>
            <input type="text" name="students[${rowCount}][name]" value="" required placeholder="生徒氏名">
        </td>
        <td>
            <select name="students[${rowCount}][guardian_id]" class="guardian-select" required>
                ${guardianOptions}
            </select>
        </td>
        <td>
            <input type="date" name="students[${rowCount}][birth_date]" value="" required>
        </td>
        <td style="width: 100px;">
            <select name="students[${rowCount}][grade_adjustment]">
                <option value="-2">-2</option>
                <option value="-1">-1</option>
                <option value="0" selected>0</option>
                <option value="1">+1</option>
                <option value="2">+2</option>
            </select>
        </td>
        <td class="optional-field">
            <input type="date" name="students[${rowCount}][support_start_date]" value="">
        </td>
        <td>
            <div class="schedule-checkboxes">
                <label><input type="checkbox" name="students[${rowCount}][scheduled_monday]" value="1"> 月</label>
                <label><input type="checkbox" name="students[${rowCount}][scheduled_tuesday]" value="1"> 火</label>
                <label><input type="checkbox" name="students[${rowCount}][scheduled_wednesday]" value="1"> 水</label>
                <label><input type="checkbox" name="students[${rowCount}][scheduled_thursday]" value="1"> 木</label>
                <label><input type="checkbox" name="students[${rowCount}][scheduled_friday]" value="1"> 金</label>
                <label><input type="checkbox" name="students[${rowCount}][scheduled_saturday]" value="1"> 土</label>
            </div>
        </td>
        <td class="action-cell">
            <button type="button" class="delete-row-btn" onclick="deleteStudentRow(this)">削除</button>
        </td>
    `;
    tbody.appendChild(tr);
    studentCounter++;

    // 新しい行にフォーカス
    tr.querySelector('input[type="text"]').focus();
}

// 保護者のインデックスを更新
function updateGuardianIndices() {
    const rows = document.querySelectorAll('#guardianTableBody tr');
    rows.forEach((row, idx) => {
        row.querySelector('.row-number').textContent = idx + 1;

        const hiddenInput = row.querySelector('input[type="hidden"]');
        const nameInput = row.querySelector('input[type="text"]');
        const emailInput = row.querySelector('input[type="email"]');

        hiddenInput.name = `guardians[${idx}][id]`;
        nameInput.name = `guardians[${idx}][name]`;
        emailInput.name = `guardians[${idx}][email]`;
    });
}

// 生徒のインデックスを更新
function updateStudentIndices() {
    const rows = document.querySelectorAll('#studentTableBody tr');
    rows.forEach((row, idx) => {
        row.querySelector('.row-number').textContent = idx + 1;

        const inputs = row.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name) {
                input.name = input.name.replace(/students\[\d+\]/, `students[${idx}]`);
            }
        });
    });
}

// 保護者選択オプションを取得
function getGuardianOptions() {
    const rows = document.querySelectorAll('#guardianTableBody tr');
    if (rows.length === 0) return null;

    let options = '';
    rows.forEach(row => {
        const id = row.dataset.guardianId;
        const name = row.querySelector('.guardian-name-input').value || '（未入力）';
        options += `<option value="${id}">${escapeHtml(name)}</option>`;
    });
    return options;
}

// 全ての保護者セレクトを更新
function updateGuardianSelects() {
    const guardianOptions = getGuardianOptions();
    if (!guardianOptions) return;

    const selects = document.querySelectorAll('#studentTableBody select.guardian-select');
    selects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = guardianOptions;
        // 以前の選択を復元（オプションが存在する場合）
        if (select.querySelector(`option[value="${currentValue}"]`)) {
            select.value = currentValue;
        }
    });
}

// HTMLエスケープ
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// 保護者名が変更されたら選択肢を更新
document.getElementById('guardianTableBody').addEventListener('input', function(e) {
    if (e.target.classList.contains('guardian-name-input')) {
        updateGuardianSelects();
    }
});
</script>

<?php renderPageEnd(); ?>
