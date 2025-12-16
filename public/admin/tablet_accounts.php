<?php
/**
 * タブレットユーザーアカウント管理（管理者専用）
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 管理者のみアクセス可能
requireUserType('admin');

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 管理者の教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;
$isMaster = isMasterAdmin();

// タブレットユーザーアカウントを取得
if ($isMaster) {
    // マスター管理者は全てのタブレットユーザーを管理可能
    $stmt = $pdo->query("
        SELECT
            u.*,
            c.classroom_name
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        WHERE u.user_type = 'tablet_user'
        ORDER BY u.created_at DESC
    ");
} else {
    // 通常管理者は自分の教室のタブレットユーザーのみ
    $stmt = $pdo->prepare("
        SELECT
            u.*,
            c.classroom_name
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        WHERE u.user_type = 'tablet_user' AND u.classroom_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$classroomId]);
}
$tabletUsers = $stmt->fetchAll();

// 教室一覧を取得
if ($isMaster) {
    $stmt = $pdo->query("SELECT id, classroom_name FROM classrooms ORDER BY classroom_name");
} else {
    $stmt = $pdo->prepare("SELECT id, classroom_name FROM classrooms WHERE id = ? ORDER BY classroom_name");
    $stmt->execute([$classroomId]);
}
$classrooms = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';

// ページ開始
$currentPage = 'tablet_accounts';
renderPageStart('admin', $currentPage, 'タブレットユーザー管理');
?>

<style>
        .content {
            background: var(--apple-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--apple-green);
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }
        .btn {
            padding: var(--spacing-md) 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: var(--apple-bg-secondary);
            color: var(--text-primary);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-warning {
            background: var(--apple-orange);
            color: var(--text-primary);
        }
        .btn-danger {
            background: var(--apple-red);
            color: white;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--spacing-lg);
        }
        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--apple-gray-5);
        }
        .users-table th {
            background: var(--apple-gray-6);
            font-weight: 600;
            color: var(--text-primary);
        }
        .users-table tr:hover {
            background: var(--apple-gray-6);
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: var(--text-caption-1);
            font-weight: 600;
        }
        .badge-tablet {
            background: #17a2b8;
            color: white;
        }
        .badge-active {
            background: var(--apple-green);
            color: white;
        }
        .badge-inactive {
            background: var(--apple-red);
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: var(--text-footnote);
            text-decoration: none;
            display: inline-block;
        }

        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background: var(--apple-bg-primary);
            margin: 5% auto;
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: var(--spacing-lg);
        }
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-purple);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: var(--spacing-lg);
        }
        .info-text {
            font-size: var(--text-footnote);
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">タブレットユーザー管理</h1>
        <p class="page-subtitle">タブレット用アカウントの作成・管理</p>
    </div>
</div>

        <div class="content">
            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <div class="toolbar">
                <h2>タブレットユーザー一覧</h2>
                <button class="btn btn-primary" onclick="openCreateModal()">➕ 新規作成</button>
            </div>

            <?php if (count($tabletUsers) > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ユーザー名</th>
                            <th>氏名</th>
                            <th>教室</th>
                            <th>状態</th>
                            <th>作成日</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tabletUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['classroom_name'] ?? '未設定'); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $user['is_active'] ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-warning" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            編集
                                        </button>
                                        <?php if ($user['is_active']): ?>
                                            <button class="action-btn btn-danger" onclick="toggleStatus(<?php echo $user['id']; ?>, 0)">
                                                無効化
                                            </button>
                                        <?php else: ?>
                                            <button class="action-btn btn-primary" onclick="toggleStatus(<?php echo $user['id']; ?>, 1)">
                                                有効化
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                    タブレットユーザーが登録されていません。<br>
                    「新規作成」ボタンから作成してください。
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 作成/編集モーダル -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle">タブレットユーザー作成</div>
            <form id="userForm" method="POST" action="tablet_accounts_save.php">
                <input type="hidden" id="userId" name="user_id">
                <input type="hidden" name="action" id="formAction" value="create">

                <div class="form-group">
                    <label for="username">ユーザー名 *</label>
                    <input type="text" id="username" name="username" required>
                    <div class="info-text">ログインに使用します（半角英数字）</div>
                </div>

                <div class="form-group">
                    <label for="full_name">氏名 *</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="classroom_id">教室 *</label>
                    <select id="classroom_id" name="classroom_id" required>
                        <option value="">選択してください</option>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?php echo $classroom['id']; ?>">
                                <?php echo htmlspecialchars($classroom['classroom_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="passwordGroup">
                    <label for="password">パスワード *</label>
                    <input type="password" id="password" name="password">
                    <div class="info-text">編集時は変更する場合のみ入力してください</div>
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">保存</button>
                    <button type="button" class="btn btn-warning" onclick="closeModal()" style="flex: 1;">キャンセル</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'タブレットユーザー作成';
            document.getElementById('formAction').value = 'create';
            document.getElementById('userId').value = '';
            document.getElementById('username').value = '';
            document.getElementById('full_name').value = '';
            document.getElementById('classroom_id').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password').required = true;
            document.getElementById('userModal').style.display = 'flex';
        }

        function openEditModal(user) {
            document.getElementById('modalTitle').textContent = 'タブレットユーザー編集';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('classroom_id').value = user.classroom_id || '';
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('userModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        function toggleStatus(userId, newStatus) {
            const action = newStatus ? '有効化' : '無効化';
            if (confirm(`このユーザーを${action}してもよろしいですか?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'tablet_accounts_toggle.php';

                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;

                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'is_active';
                statusInput.value = newStatus;

                form.appendChild(userIdInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // モーダル外クリックで閉じる（テキスト選択時は閉じない）
        let modalMouseDownTarget = null;

        window.addEventListener('mousedown', function(event) {
            modalMouseDownTarget = event.target;
        });

        window.addEventListener('mouseup', function(event) {
            const modal = document.getElementById('userModal');
            // mousedownとmouseupが同じモーダル背景で発生した場合のみ閉じる
            if (event.target == modal && modalMouseDownTarget === event.target) {
                closeModal();
            }
            modalMouseDownTarget = null;
        });
    </script>

<?php renderPageEnd(); ?>
