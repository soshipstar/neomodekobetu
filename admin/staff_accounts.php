<?php
/**
 * スタッフアカウント管理（マスター管理者専用）
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// マスター管理者チェック
requireMasterAdmin();

$pdo = getDbConnection();

// 全スタッフアカウントを取得
$stmt = $pdo->query("
    SELECT
        u.*,
        c.classroom_name
    FROM users u
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    WHERE u.user_type = 'staff'
    ORDER BY u.created_at DESC
");
$staff = $stmt->fetchAll();

// 教室一覧を取得
$stmt = $pdo->query("SELECT id, classroom_name FROM classrooms ORDER BY classroom_name");
$classrooms = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフアカウント管理 - マスター管理者</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .master-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }
        .back-btn:hover {
            background: #5a6268;
        }
        .content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .staff-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .staff-table th,
        .staff-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .staff-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .staff-table tr:hover {
            background: #f8f9fa;
        }
        .badge-staff {
            background: #17a2b8;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
        }
        .badge-active {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
        }
        .badge-inactive {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-buttons button {
            padding: 6px 10px;
            font-size: 11px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 3% auto;
            padding: 30px;
            border: 1px solid #888;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group small {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>👨‍💼 スタッフアカウント管理<span class="master-badge">★マスター専用</span></h1>
            </div>
            <div>
                <a href="index.php" class="back-btn">← 管理者トップに戻る</a>
            </div>
        </div>

        <div class="content">
            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <div class="toolbar">
                <h2>スタッフアカウント一覧</h2>
                <button class="btn btn-primary" onclick="openAddModal()">➕ 新規スタッフ登録</button>
            </div>

            <table class="staff-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ユーザー名</th>
                        <th>氏名</th>
                        <th>メールアドレス</th>
                        <th>所属教室</th>
                        <th>ステータス</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td><?php echo htmlspecialchars($s['username']); ?></td>
                            <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($s['classroom_name'] ?? '-'); ?></td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge-active">有効</span>
                                <?php else: ?>
                                    <span class="badge-inactive">無効</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y/m/d', strtotime($s['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary" onclick='openEditModal(<?php echo json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>編集</button>
                                    <button class="btn btn-warning" onclick="convertToAdmin(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['username'], ENT_QUOTES); ?>')">管理者に変換</button>
                                    <button class="btn btn-danger" onclick="deleteStaff(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['username'], ENT_QUOTES); ?>')">削除</button>
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
            <h2>新規スタッフ登録</h2>
            <form action="staff_accounts_save.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>ユーザー名 *</label>
                    <input type="text" name="username" required>
                    <small>ログイン時に使用します（半角英数字）</small>
                </div>
                <div class="form-group">
                    <label>パスワード *</label>
                    <input type="password" name="password" required minlength="6">
                    <small>6文字以上</small>
                </div>
                <div class="form-group">
                    <label>氏名 *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>所属教室 *</label>
                    <select name="classroom_id" required>
                        <option value="">選択してください</option>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?php echo $classroom['id']; ?>">
                                <?php echo htmlspecialchars($classroom['classroom_name']); ?>
                            </option>
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
            <h2>スタッフ情報編集</h2>
            <form action="staff_accounts_save.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label>ユーザー名</label>
                    <input type="text" id="edit_username" disabled style="background: #f5f5f5;">
                    <small>ユーザー名は変更できません</small>
                </div>
                <div class="form-group">
                    <label>新しいパスワード</label>
                    <input type="password" name="password" minlength="6">
                    <small>変更しない場合は空欄にしてください</small>
                </div>
                <div class="form-group">
                    <label>氏名 *</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label>所属教室 *</label>
                    <select name="classroom_id" id="edit_classroom_id" required>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?php echo $classroom['id']; ?>">
                                <?php echo htmlspecialchars($classroom['classroom_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ステータス *</label>
                    <select name="is_active" id="edit_is_active" required>
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
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(staff) {
            document.getElementById('edit_user_id').value = staff.id;
            document.getElementById('edit_username').value = staff.username;
            document.getElementById('edit_full_name').value = staff.full_name;
            document.getElementById('edit_email').value = staff.email || '';
            document.getElementById('edit_classroom_id').value = staff.classroom_id || '';
            document.getElementById('edit_is_active').value = staff.is_active;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function convertToAdmin(userId, username) {
            if (confirm(`「${username}」をスタッフから管理者アカウントに変換しますか？\n\n管理者権限が付与され、管理者としてログイン可能になります。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'staff_accounts_save.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'convert_to_admin';
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

        function deleteStaff(userId, username) {
            if (confirm(`本当に「${username}」を削除しますか？\n\nこの操作は取り消せません。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'staff_accounts_save.php';

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

        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
