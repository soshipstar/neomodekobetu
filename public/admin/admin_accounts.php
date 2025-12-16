<?php
/**
 * 邂｡逅・・い繧ｫ繧ｦ繝ｳ繝育ｮ｡逅・ｼ医・繧ｹ繧ｿ繝ｼ邂｡逅・・ｰら畑・・ */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 繝槭せ繧ｿ繝ｼ邂｡逅・・メ繧ｧ繝・け
requireMasterAdmin();

$pdo = getDbConnection();

// 蜈ｨ邂｡逅・・い繧ｫ繧ｦ繝ｳ繝医ｒ蜿門ｾ・$stmt = $pdo->query("
    SELECT
        u.*,
        c.classroom_name
    FROM users u
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    WHERE u.user_type = 'admin'
    ORDER BY u.is_master DESC, u.created_at DESC
");
$admins = $stmt->fetchAll();

// 謨吝ｮ､荳隕ｧ繧貞叙蠕・$stmt = $pdo->query("SELECT id, classroom_name FROM classrooms ORDER BY classroom_name");
$classrooms = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';

// 繝壹・繧ｸ髢句ｧ・$currentPage = 'admin_accounts';
renderPageStart('admin', $currentPage, '邂｡逅・・い繧ｫ繧ｦ繝ｳ繝育ｮ｡逅・);
?>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">邂｡逅・・い繧ｫ繧ｦ繝ｳ繝育ｮ｡逅・/h1>
        <p class="page-subtitle">繝槭せ繧ｿ繝ｼ邂｡逅・・ｰら畑</p>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
            <h2 style="font-size: var(--text-headline); color: var(--apple-purple);">邂｡逅・・い繧ｫ繧ｦ繝ｳ繝井ｸ隕ｧ</h2>
            <button class="btn btn-primary" onclick="openAddModal()">譁ｰ隕冗ｮ｡逅・・匳骭ｲ</button>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>繝ｦ繝ｼ繧ｶ繝ｼ蜷・/th>
                    <th>豌丞錐</th>
                    <th>繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ</th>
                    <th>讓ｩ髯・/th>
                    <th>謇螻樊蕗螳､</th>
                    <th>繧ｹ繝・・繧ｿ繧ｹ</th>
                    <th>逋ｻ骭ｲ譌･</th>
                    <th>謫堺ｽ・/th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?= $admin['id'] ?></td>
                        <td><?= htmlspecialchars($admin['username']) ?></td>
                        <td><?= htmlspecialchars($admin['full_name']) ?></td>
                        <td><?= htmlspecialchars($admin['email'] ?? '-') ?></td>
                        <td>
                            <?php if ($admin['is_master']): ?>
                                <span class="badge" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white;">繝槭せ繧ｿ繝ｼ邂｡逅・・/span>
                            <?php else: ?>
                                <span class="badge badge-secondary">騾壼ｸｸ邂｡逅・・/span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($admin['classroom_name'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $admin['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $admin['is_active'] ? '譛牙柑' : '辟｡蜉ｹ' ?>
                            </span>
                        </td>
                        <td><?= date('Y/m/d', strtotime($admin['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button class="btn btn-primary btn-sm" onclick='openEditModal(<?= json_encode($admin, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>邱ｨ髮・/button>
                                <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-success btn-sm" onclick="convertToStaff(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">繧ｹ繧ｿ繝・ヵ縺ｫ螟画鋤</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteAdmin(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">蜑企勁</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 譁ｰ隕冗匳骭ｲ繝｢繝ｼ繝繝ｫ -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">譁ｰ隕冗ｮ｡逅・・匳骭ｲ</h2>
        <form action="admin_accounts_save.php" method="POST">
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
            <div class="form-group">
                <label class="form-label">讓ｩ髯・*</label>
                <select name="is_master" class="form-control" required>
                    <option value="0">騾壼ｸｸ邂｡逅・・/option>
                    <option value="1">繝槭せ繧ｿ繝ｼ邂｡逅・・/option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">謇螻樊蕗螳､ *</label>
                <select name="classroom_id" class="form-control" required>
                    <option value="">驕ｸ謚槭＠縺ｦ縺上□縺輔＞</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?= $classroom['id'] ?>"><?= htmlspecialchars($classroom['classroom_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">逋ｻ骭ｲ</button>
        </form>
    </div>
</div>

<!-- 邱ｨ髮・Δ繝ｼ繝繝ｫ -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">邂｡逅・・ュ蝣ｱ邱ｨ髮・/h2>
        <form action="admin_accounts_save.php" method="POST">
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
                <label class="form-label">讓ｩ髯・*</label>
                <select name="is_master" id="edit_is_master" class="form-control" required>
                    <option value="0">騾壼ｸｸ邂｡逅・・/option>
                    <option value="1">繝槭せ繧ｿ繝ｼ邂｡逅・・/option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">謇螻樊蕗螳､ *</label>
                <select name="classroom_id" id="edit_classroom_id" class="form-control" required>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?= $classroom['id'] ?>"><?= htmlspecialchars($classroom['classroom_name']) ?></option>
                    <?php endforeach; ?>
                </select>
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

    function openEditModal(admin) {
        document.getElementById('edit_user_id').value = admin.id;
        document.getElementById('edit_username').value = admin.username;
        document.getElementById('edit_full_name').value = admin.full_name;
        document.getElementById('edit_email').value = admin.email || '';
        document.getElementById('edit_is_master').value = admin.is_master;
        document.getElementById('edit_classroom_id').value = admin.classroom_id || '';
        document.getElementById('edit_is_active').value = admin.is_active;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function convertToStaff(userId, username) {
        if (confirm(`縲・{username}縲阪ｒ邂｡逅・・°繧峨せ繧ｿ繝・ヵ繧｢繧ｫ繧ｦ繝ｳ繝医↓蛻・ｊ譖ｿ縺医∪縺吶°・歃n\n邂｡逅・・ｨｩ髯舌′縺ｪ縺上↑繧翫√せ繧ｿ繝・ヵ縺ｨ縺励※縺ｮ縺ｿ繝ｭ繧ｰ繧､繝ｳ蜿ｯ閭ｽ縺ｫ縺ｪ繧翫∪縺吶Ａ)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_accounts_save.php';
            form.innerHTML = `<input type="hidden" name="action" value="convert_to_staff"><input type="hidden" name="user_id" value="${userId}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteAdmin(userId, username) {
        if (confirm(`譛ｬ蠖薙↓縲・{username}縲阪ｒ蜑企勁縺励∪縺吶°・歃n\n縺薙・謫堺ｽ懊・蜿悶ｊ豸医○縺ｾ縺帙ｓ縲Ａ)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_accounts_save.php';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="${userId}">`;
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
