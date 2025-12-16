<?php
/**
 * 繧ｹ繧ｿ繝・ヵ邂｡逅・ｼ育ｮ｡逅・・畑・・ */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 邂｡逅・・メ繧ｧ繝・け
requireUserType('admin');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $currentUser['classroom_id'];

// 繝槭せ繧ｿ繝ｼ邂｡逅・・・蝣ｴ蜷医・蟆ら畑繝壹・繧ｸ縺ｫ繝ｪ繝繧､繝ｬ繧ｯ繝・if (isMasterAdmin()) {
    header('Location: staff_accounts.php');
    exit;
}

// 謨吝ｮ､蜷阪ｒ蜿門ｾ・$stmt = $pdo->prepare("SELECT classroom_name FROM classrooms WHERE id = ?");
$stmt->execute([$classroomId]);
$classroom = $stmt->fetch();
$classroomName = $classroom ? $classroom['classroom_name'] : '';

// 閾ｪ蛻・・謨吝ｮ､縺ｮ繧ｹ繧ｿ繝・ヵ繧｢繧ｫ繧ｦ繝ｳ繝医・縺ｿ繧貞叙蠕・$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE user_type = 'staff' AND classroom_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$classroomId]);
$staff = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';

// 繝壹・繧ｸ髢句ｧ・$currentPage = 'staff_management';
renderPageStart('admin', $currentPage, '繧ｹ繧ｿ繝・ヵ邂｡逅・);
?>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">繧ｹ繧ｿ繝・ヵ邂｡逅・/h1>
        <p class="page-subtitle"><?= htmlspecialchars($classroomName) ?></p>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
            <h2 style="font-size: var(--text-headline); color: var(--apple-purple);">繧ｹ繧ｿ繝・ヵ繧｢繧ｫ繧ｦ繝ｳ繝井ｸ隕ｧ</h2>
            <button class="btn btn-primary" onclick="openAddModal()">譁ｰ隕上せ繧ｿ繝・ヵ逋ｻ骭ｲ</button>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>繝ｦ繝ｼ繧ｶ繝ｼ蜷・/th>
                    <th>豌丞錐</th>
                    <th>繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ</th>
                    <th>繧ｹ繝・・繧ｿ繧ｹ</th>
                    <th>逋ｻ骭ｲ譌･</th>
                    <th>謫堺ｽ・/th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            繧ｹ繧ｿ繝・ヵ繧｢繧ｫ繧ｦ繝ｳ繝医′逋ｻ骭ｲ縺輔ｌ縺ｦ縺・∪縺帙ｓ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staff as $s): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><?= htmlspecialchars($s['username']) ?></td>
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $s['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $s['is_active'] ? '譛牙柑' : '辟｡蜉ｹ' ?>
                                </span>
                            </td>
                            <td><?= date('Y/m/d', strtotime($s['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-primary btn-sm" onclick='openEditModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>邱ｨ髮・/button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteStaff(<?= $s['id'] ?>, '<?= htmlspecialchars($s['username'], ENT_QUOTES) ?>')">蜑企勁</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 譁ｰ隕冗匳骭ｲ繝｢繝ｼ繝繝ｫ -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">譁ｰ隕上せ繧ｿ繝・ヵ逋ｻ骭ｲ</h2>
        <form action="staff_management_save.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">繝ｦ繝ｼ繧ｶ繝ｼ蜷・*</label>
                <input type="text" name="username" class="form-control" required>
                <small style="color: var(--text-secondary);">繝ｭ繧ｰ繧､繝ｳ譎ゅ↓菴ｿ逕ｨ縺励∪縺呻ｼ亥濠隗定恭謨ｰ蟄暦ｼ・/small>
            </div>
            <div class="form-group">
                <label class="form-label">繝代せ繝ｯ繝ｼ繝・*</label>
                <input type="password" name="password" class="form-control" required minlength="6">
                <small style="color: var(--text-secondary);">6譁・ｭ嶺ｻ･荳・/small>
            </div>
            <div class="form-group">
                <label class="form-label">豌丞錐 *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ</label>
                <input type="email" name="email" class="form-control">
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">逋ｻ骭ｲ</button>
        </form>
    </div>
</div>

<!-- 邱ｨ髮・Δ繝ｼ繝繝ｫ -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">繧ｹ繧ｿ繝・ヵ諠・ｱ邱ｨ髮・/h2>
        <form action="staff_management_save.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label class="form-label">繝ｦ繝ｼ繧ｶ繝ｼ蜷・/label>
                <input type="text" id="edit_username" class="form-control" disabled style="background: var(--apple-gray-6);">
                <small style="color: var(--text-secondary);">繝ｦ繝ｼ繧ｶ繝ｼ蜷阪・螟画峩縺ｧ縺阪∪縺帙ｓ</small>
            </div>
            <div class="form-group">
                <label class="form-label">譁ｰ縺励＞繝代せ繝ｯ繝ｼ繝・/label>
                <input type="password" name="password" class="form-control" minlength="6">
                <small style="color: var(--text-secondary);">螟画峩縺励↑縺・ｴ蜷医・遨ｺ谺・↓縺励※縺上□縺輔＞</small>
            </div>
            <div class="form-group">
                <label class="form-label">豌丞錐 *</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ</label>
                <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">繧ｹ繝・・繧ｿ繧ｹ *</label>
                <select name="is_active" id="edit_is_active" class="form-control" required>
                    <option value="1">譛牙柑</option>
                    <option value="0">辟｡蜉ｹ</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">譖ｴ譁ｰ</button>
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

    function openEditModal(staff) {
        document.getElementById('edit_user_id').value = staff.id;
        document.getElementById('edit_username').value = staff.username;
        document.getElementById('edit_full_name').value = staff.full_name;
        document.getElementById('edit_email').value = staff.email || '';
        document.getElementById('edit_is_active').value = staff.is_active;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function deleteStaff(userId, username) {
        if (confirm(`譛ｬ蠖薙↓縲・{username}縲阪ｒ蜑企勁縺励∪縺吶°・歃n\n縺薙・謫堺ｽ懊・蜿悶ｊ豸医○縺ｾ縺帙ｓ縲Ａ)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'staff_management_save.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            form.appendChild(actionInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'user_id';
            idInput.value = userId;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    // 繝｢繝ｼ繝繝ｫ螟悶け繝ｪ繝・け縺ｧ髢峨§繧・    let modalMouseDownTarget = null;
    window.addEventListener('mousedown', function(event) {
        modalMouseDownTarget = event.target;
    });
    window.addEventListener('mouseup', function(event) {
        if (event.target.classList.contains('modal') && modalMouseDownTarget === event.target) {
            event.target.style.display = 'none';
        }
        modalMouseDownTarget = null;
    });
</script>

<?php renderPageEnd(); ?>
