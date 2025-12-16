<?php
/**
 * 謨吝ｮ､邂｡逅・ｼ医・繧ｹ繧ｿ繝ｼ邂｡逅・・ｰら畑・・ */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 繝槭せ繧ｿ繝ｼ邂｡逅・・メ繧ｧ繝・け
requireMasterAdmin();

$pdo = getDbConnection();

// 蜈ｨ謨吝ｮ､繧貞叙蠕暦ｼ医し繝悶け繧ｨ繝ｪ縺ｧ豁｣遒ｺ縺ｫ繧ｫ繧ｦ繝ｳ繝茨ｼ・$stmt = $pdo->query("
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

// 繝壹・繧ｸ髢句ｧ・$currentPage = 'classrooms';
renderPageStart('admin', $currentPage, '謨吝ｮ､邂｡逅・);
?>

<style>
.logo-preview {
    height: 40px;
    width: auto;
    max-width: 100px;
}
</style>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">謨吝ｮ､邂｡逅・/h1>
        <p class="page-subtitle">繝槭せ繧ｿ繝ｼ邂｡逅・・ｰら畑 - 蜈ｨ謨吝ｮ､縺ｮ邂｡逅・/p>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<!-- 譁ｰ隕冗匳骭ｲ繝懊ち繝ｳ -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: var(--text-headline); color: var(--apple-purple);">逋ｻ骭ｲ謨吝ｮ､荳隕ｧ</h2>
            <button class="btn btn-primary" onclick="openAddModal()">譁ｰ隕乗蕗螳､逋ｻ骭ｲ</button>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>繝ｭ繧ｴ</th>
                    <th>謨吝ｮ､蜷・/th>
                    <th>菴乗園</th>
                    <th>髮ｻ隧ｱ逡ｪ蜿ｷ</th>
                    <th>邂｡逅・・焚</th>
                    <th>繧ｹ繧ｿ繝・ヵ謨ｰ</th>
                    <th>逕溷ｾ呈焚</th>
                    <th>逋ｻ骭ｲ譌･</th>
                    <th>謫堺ｽ・/th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classrooms as $classroom): ?>
                    <tr>
                        <td><?= $classroom['id'] ?></td>
                        <td>
                            <?php if ($classroom['logo_path'] && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
                                <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="繝ｭ繧ｴ" class="logo-preview">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($classroom['classroom_name']) ?></td>
                        <td><?= htmlspecialchars($classroom['address'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($classroom['phone'] ?? '-') ?></td>
                        <td><?= $classroom['admin_count'] ?>莠ｺ</td>
                        <td><?= $classroom['staff_count'] ?>莠ｺ</td>
                        <td><?= $classroom['student_count'] ?>莠ｺ</td>
                        <td><?= date('Y/m/d', strtotime($classroom['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-primary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($classroom), ENT_QUOTES) ?>)">邱ｨ髮・/button>
                                <button class="btn btn-danger btn-sm" onclick="deleteClassroom(<?= $classroom['id'] ?>, '<?= htmlspecialchars($classroom['classroom_name'], ENT_QUOTES) ?>')">蜑企勁</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($classrooms)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            逋ｻ骭ｲ縺輔ｌ縺ｦ縺・ｋ謨吝ｮ､縺後≠繧翫∪縺帙ｓ
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 譁ｰ隕冗匳骭ｲ繝｢繝ｼ繝繝ｫ -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">譁ｰ隕乗蕗螳､逋ｻ骭ｲ</h2>
        <form action="classrooms_save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">謨吝ｮ､蜷・*</label>
                <input type="text" name="classroom_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">菴乗園</label>
                <textarea name="address" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">髮ｻ隧ｱ逡ｪ蜿ｷ</label>
                <input type="tel" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">繝ｭ繧ｴ逕ｻ蜒擾ｼ・MB莉･蜀・・JPEG, PNG, GIF・・/label>
                <input type="file" name="logo" accept="image/*" class="form-control">
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">逋ｻ骭ｲ</button>
        </form>
    </div>
</div>

<!-- 邱ｨ髮・Δ繝ｼ繝繝ｫ -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">謨吝ｮ､諠・ｱ邱ｨ髮・/h2>
        <form action="classrooms_save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="classroom_id" id="edit_classroom_id">
            <div class="form-group">
                <label class="form-label">謨吝ｮ､蜷・*</label>
                <input type="text" name="classroom_name" id="edit_classroom_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">菴乗園</label>
                <textarea name="address" id="edit_address" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">髮ｻ隧ｱ逡ｪ蜿ｷ</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">繝ｭ繧ｴ逕ｻ蜒擾ｼ・MB莉･蜀・・JPEG, PNG, GIF・・/label>
                <input type="file" name="logo" accept="image/*" class="form-control">
                <div id="current_logo" style="margin-top: 10px;"></div>
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

    function openEditModal(classroom) {
        document.getElementById('edit_classroom_id').value = classroom.id;
        document.getElementById('edit_classroom_name').value = classroom.classroom_name;
        document.getElementById('edit_address').value = classroom.address || '';
        document.getElementById('edit_phone').value = classroom.phone || '';

        const logoDiv = document.getElementById('current_logo');
        if (classroom.logo_path) {
            logoDiv.innerHTML = '<p style="color: var(--text-secondary); font-size: var(--text-caption-1);">迴ｾ蝨ｨ縺ｮ繝ｭ繧ｴ:</p><img src="../' + classroom.logo_path + '" style="max-width: 200px; max-height: 100px;">';
        } else {
            logoDiv.innerHTML = '<p style="color: var(--text-secondary); font-size: var(--text-caption-1);">迴ｾ蝨ｨ繝ｭ繧ｴ縺ｯ譛ｪ險ｭ螳壹〒縺・/p>';
        }

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function deleteClassroom(classroomId, classroomName) {
        if (confirm(`譛ｬ蠖薙↓縲・{classroomName}縲阪ｒ蜑企勁縺励∪縺吶°・歃n\n縺薙・謫堺ｽ懊・蜿悶ｊ豸医○縺ｾ縺帙ｓ縲ゅ％縺ｮ謨吝ｮ､縺ｫ謇螻槭☆繧狗ｮ｡逅・・√せ繧ｿ繝・ヵ縲∫函蠕偵√♀繧医・縺吶∋縺ｦ縺ｮ髢｢騾｣繝・・繧ｿ縺悟炎髯､縺輔ｌ縺ｾ縺吶Ａ)) {
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

    // 繝｢繝ｼ繝繝ｫ螟悶け繝ｪ繝・け縺ｧ髢峨§繧・    let modalMouseDownTarget = null;

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
