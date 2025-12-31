<?php
/**
 * 教室管理（マスター管理者専用）
 * ミニマム版
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// マスター管理者チェック
requireMasterAdmin();

$pdo = getDbConnection();

// 全教室を取得
$stmt = $pdo->query("
    SELECT
        c.*,
        (SELECT COUNT(*) FROM users WHERE classroom_id = c.id AND user_type = 'admin') as admin_count,
        (SELECT COUNT(*) FROM users WHERE classroom_id = c.id AND user_type = 'staff') as staff_count,
        (SELECT COUNT(*) FROM users u INNER JOIN students s ON u.id = s.guardian_id WHERE u.classroom_id = c.id) as student_count
    FROM classrooms c
    ORDER BY c.created_at DESC
");
$classrooms = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

// ページ開始
$currentPage = 'classrooms';
renderPageStart('admin', $currentPage, '教室管理');
?>

<style>
.logo-preview {
    height: 40px;
    width: auto;
    max-width: 80px;
}
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-top: var(--spacing-md);
}
.table-responsive .table {
    min-width: 900px;
    white-space: nowrap;
}
.table-responsive .table th,
.table-responsive .table td {
    padding: 10px 12px;
    font-size: 13px;
    vertical-align: middle;
}
.table-responsive .table th {
    background: var(--md-bg-secondary);
    font-weight: 600;
}
.service-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}
.service-badge.normal {
    background: var(--md-blue);
}
.service-badge.minimum {
    background: linear-gradient(135deg, #f093fb, #f5576c);
}
.count-cell {
    text-align: center;
}
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
}
.action-buttons .btn {
    padding: 4px 10px;
    font-size: 12px;
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">教室管理</h1>
        <p class="page-subtitle">マスター管理者専用 - 全教室の管理</p>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>

<!-- 新規登録ボタン -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: var(--text-headline); color: var(--md-purple);">登録教室一覧</h2>
            <button class="btn btn-primary" onclick="openAddModal()">新規教室登録</button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ロゴ</th>
                        <th>教室名</th>
                        <th>種別</th>
                        <th>住所</th>
                        <th>電話番号</th>
                        <th class="count-cell">管理者</th>
                        <th class="count-cell">スタッフ</th>
                        <th class="count-cell">生徒</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classrooms as $classroom): ?>
                        <tr>
                            <td><?= $classroom['id'] ?></td>
                            <td>
                                <?php if ($classroom['logo_path'] && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
                                    <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="ロゴ" class="logo-preview">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($classroom['classroom_name']) ?></td>
                            <td>
                                <?php if (($classroom['service_type'] ?? 'normal') === 'minimum'): ?>
                                    <span class="service-badge minimum">ミニマム</span>
                                <?php else: ?>
                                    <span class="service-badge normal">通常</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($classroom['address'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($classroom['phone'] ?? '-') ?></td>
                            <td class="count-cell"><?= $classroom['admin_count'] ?></td>
                            <td class="count-cell"><?= $classroom['staff_count'] ?></td>
                            <td class="count-cell"><?= $classroom['student_count'] ?></td>
                            <td><?= date('Y/m/d', strtotime($classroom['created_at'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($classroom), ENT_QUOTES) ?>)">編集</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteClassroom(<?= $classroom['id'] ?>, '<?= htmlspecialchars($classroom['classroom_name'], ENT_QUOTES) ?>')">削除</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($classrooms)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                                登録されている教室がありません
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 新規登録モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">新規教室登録</h2>
        <form action="classrooms_save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">教室名 *</label>
                <input type="text" name="classroom_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">サービス種別 *</label>
                <select name="service_type" class="form-control" required>
                    <option value="normal">通常版</option>
                    <option value="minimum">ミニマム版</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">住所</label>
                <textarea name="address" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">電話番号</label>
                <input type="tel" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">ロゴ画像（2MB以内、JPEG, PNG, GIF）</label>
                <input type="file" name="logo" accept="image/*" class="form-control">
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">登録</button>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">教室情報編集</h2>
        <form action="classrooms_save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="classroom_id" id="edit_classroom_id">
            <div class="form-group">
                <label class="form-label">教室名 *</label>
                <input type="text" name="classroom_name" id="edit_classroom_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">サービス種別 *</label>
                <select name="service_type" id="edit_service_type" class="form-control" required>
                    <option value="normal">通常版</option>
                    <option value="minimum">ミニマム版</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">住所</label>
                <textarea name="address" id="edit_address" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">電話番号</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">ロゴ画像（2MB以内、JPEG, PNG, GIF）</label>
                <input type="file" name="logo" accept="image/*" class="form-control">
                <div id="current_logo" style="margin-top: 10px;"></div>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">更新</button>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openEditModal(classroom) {
        document.getElementById('edit_classroom_id').value = classroom.id;
        document.getElementById('edit_classroom_name').value = classroom.classroom_name;
        document.getElementById('edit_service_type').value = classroom.service_type || 'normal';
        document.getElementById('edit_address').value = classroom.address || '';
        document.getElementById('edit_phone').value = classroom.phone || '';

        const logoDiv = document.getElementById('current_logo');
        if (classroom.logo_path) {
            logoDiv.innerHTML = '<p style="color: var(--text-secondary); font-size: var(--text-caption-1);">現在のロゴ:</p><img src="../' + classroom.logo_path + '" style="max-width: 200px; max-height: 100px;">';
        } else {
            logoDiv.innerHTML = '<p style="color: var(--text-secondary); font-size: var(--text-caption-1);">現在ロゴは未設定です</p>';
        }

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function deleteClassroom(classroomId, classroomName) {
        if (confirm(`本当に「${classroomName}」を削除しますか？\n\nこの操作は取り消せません。この教室に所属する管理者、スタッフ、生徒、およびすべての関連データが削除されます。`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'classrooms_save.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            form.appendChild(actionInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'classroom_id';
            idInput.value = classroomId;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    // モーダル外クリックで閉じる
    let modalMouseDownTarget = null;

    window.addEventListener('mousedown', function(event) {
        modalMouseDownTarget = event.target;
    });

    window.addEventListener('mouseup', function(event) {
        if (event.target.classList.contains('modal') &&
            modalMouseDownTarget === event.target) {
            event.target.style.display = 'none';
        }
        modalMouseDownTarget = null;
    });
</script>

<?php renderPageEnd(); ?>
