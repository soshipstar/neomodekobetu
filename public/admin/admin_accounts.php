<?php
/**
 * 管理者アカウント管理（マスター管理者専用）
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// マスター管理者チェック
requireMasterAdmin();

$pdo = getDbConnection();

// 全管理者アカウントを取得
$stmt = $pdo->query("
    SELECT
        u.*,
        c.classroom_name
    FROM users u
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    WHERE u.user_type = 'admin'
    ORDER BY u.is_master DESC, u.created_at DESC
");
$admins = $stmt->fetchAll();

// 教室一覧を取得
$stmt = $pdo->query("SELECT id, classroom_name FROM classrooms ORDER BY classroom_name");
$classrooms = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';

// ページ開始
$currentPage = 'admin_accounts';
renderPageStart('admin', $currentPage, '管理者アカウント管理');
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">管理者アカウント管理</h1>
        <p class="page-subtitle">マスター管理者専用</p>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
            <h2 style="font-size: var(--text-headline); color: var(--md-purple);">管理者アカウント一覧</h2>
            <button class="btn btn-primary" onclick="openAddModal()">新規管理者登録</button>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ユーザー名</th>
                    <th>氏名</th>
                    <th>メールアドレス</th>
                    <th>権限</th>
                    <th>所属教室</th>
                    <th>ステータス</th>
                    <th>登録日</th>
                    <th>操作</th>
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
                                <span class="badge" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white;">マスター管理者</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">通常管理者</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($admin['classroom_name'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $admin['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $admin['is_active'] ? '有効' : '無効' ?>
                            </span>
                        </td>
                        <td><?= date('Y/m/d', strtotime($admin['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button class="btn btn-primary btn-sm" onclick='openEditModal(<?= json_encode($admin, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>編集</button>
                                <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-success btn-sm" onclick="convertToStaff(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">スタッフに変換</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteAdmin(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>')">削除</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 新規登録モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">新規管理者登録</h2>
        <form action="admin_accounts_save.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">ユーザー名 *</label>
                <input type="text" name="username" class="form-control" required>
                <small style="color: var(--text-secondary);">ログイン時に使用します（半角英数字）</small>
            </div>
            <div class="form-group">
                <label class="form-label">パスワード *</label>
                <input type="password" name="password" class="form-control" required minlength="6">
                <small style="color: var(--text-secondary);">6文字以上</small>
            </div>
            <div class="form-group">
                <label class="form-label">氏名 *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">メールアドレス</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">権限 *</label>
                <select name="is_master" class="form-control" required>
                    <option value="0">通常管理者</option>
                    <option value="1">マスター管理者</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">所属教室 *</label>
                <select name="classroom_id" class="form-control" required>
                    <option value="">選択してください</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?= $classroom['id'] ?>"><?= htmlspecialchars($classroom['classroom_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">登録</button>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2 style="margin-bottom: var(--spacing-lg);">管理者情報編集</h2>
        <form action="admin_accounts_save.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label class="form-label">ユーザー名</label>
                <input type="text" id="edit_username" class="form-control" disabled style="background: var(--md-gray-6);">
                <small style="color: var(--text-secondary);">ユーザー名は変更できません</small>
            </div>
            <div class="form-group">
                <label class="form-label">新しいパスワード</label>
                <input type="password" name="password" class="form-control" minlength="6">
                <small style="color: var(--text-secondary);">変更しない場合は空欄にしてください</small>
            </div>
            <div class="form-group">
                <label class="form-label">氏名 *</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">メールアドレス</label>
                <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">権限 *</label>
                <select name="is_master" id="edit_is_master" class="form-control" required>
                    <option value="0">通常管理者</option>
                    <option value="1">マスター管理者</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">所属教室 *</label>
                <select name="classroom_id" id="edit_classroom_id" class="form-control" required>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?= $classroom['id'] ?>"><?= htmlspecialchars($classroom['classroom_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ステータス *</label>
                <select name="is_active" id="edit_is_active" class="form-control" required>
                    <option value="1">有効</option>
                    <option value="0">無効</option>
                </select>
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
        if (confirm(`「${username}」を管理者からスタッフアカウントに切り替えますか？\n\n管理者権限がなくなり、スタッフとしてのみログイン可能になります。`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_accounts_save.php';
            form.innerHTML = `<input type="hidden" name="action" value="convert_to_staff"><input type="hidden" name="user_id" value="${userId}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteAdmin(userId, username) {
        if (confirm(`本当に「${username}」を削除しますか？\n\nこの操作は取り消せません。`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_accounts_save.php';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="${userId}">`;
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
        if (event.target.classList.contains('modal') && modalMouseDownTarget === event.target) {
            event.target.style.display = 'none';
        }
        modalMouseDownTarget = null;
    });
</script>

<?php renderPageEnd(); ?>
