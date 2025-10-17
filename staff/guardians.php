<?php
/**
 * スタッフ用 - 保護者管理ページ
 * 保護者の登録・編集
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();

// 保護者一覧を取得
$stmt = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.is_active,
        u.created_at,
        COUNT(s.id) as student_count
    FROM users u
    LEFT JOIN students s ON u.id = s.guardian_id
    WHERE u.user_type = 'guardian'
    GROUP BY u.id
    ORDER BY u.is_active DESC, u.full_name
");
$guardians = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保護者管理 - スタッフページ</title>
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
            max-width: 1200px;
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
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
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
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        table tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👤 保護者管理</h1>
            <div class="header-actions">
                <span class="user-info"><?php echo htmlspecialchars($_SESSION['full_name']); ?>（<?php echo $_SESSION['user_type'] === 'admin' ? '管理者' : 'スタッフ'; ?>）</span>
                <a href="<?php echo $_SESSION['user_type'] === 'admin' ? '/admin/index.php' : 'renrakucho_activities.php'; ?>" class="btn btn-secondary btn-sm">戻る</a>
                <a href="/logout.php" class="btn btn-danger btn-sm">ログアウト</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php
                switch ($_GET['success']) {
                    case 'created':
                        echo '保護者を登録しました。';
                        break;
                    case 'updated':
                        echo '保護者情報を更新しました。';
                        break;
                    default:
                        echo '処理が完了しました。';
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                エラー: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- 新規登録フォーム -->
        <div class="content-box">
            <h2 class="section-title">新規保護者登録</h2>
            <form action="guardians_save.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>保護者氏名 *</label>
                        <input type="text" name="full_name" required placeholder="例: 山田 花子">
                    </div>
                    <div class="form-group">
                        <label>ユーザー名（ログインID） *</label>
                        <input type="text" name="username" required placeholder="例: yamada_h">
                        <div class="help-text">半角英数字とアンダースコアのみ使用可能</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>パスワード *</label>
                        <input type="password" name="password" required placeholder="8文字以上">
                        <div class="help-text">8文字以上で設定してください</div>
                    </div>
                    <div class="form-group">
                        <label>パスワード（確認） *</label>
                        <input type="password" name="password_confirm" required placeholder="もう一度入力">
                    </div>
                </div>
                <div class="form-group">
                    <label>メールアドレス（任意）</label>
                    <input type="email" name="email" placeholder="例: yamada@example.com">
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-success">登録する</button>
                </div>
            </form>
        </div>

        <!-- 保護者一覧 -->
        <div class="content-box">
            <h2 class="section-title">保護者一覧</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>氏名</th>
                        <th>ユーザー名</th>
                        <th>メールアドレス</th>
                        <th>紐づく生徒</th>
                        <th>状態</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guardians)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                登録されている保護者がいません
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($guardians as $guardian): ?>
                            <tr>
                                <td><?php echo $guardian['id']; ?></td>
                                <td><?php echo htmlspecialchars($guardian['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($guardian['username']); ?></td>
                                <td><?php echo $guardian['email'] ? htmlspecialchars($guardian['email']) : '-'; ?></td>
                                <td><?php echo $guardian['student_count']; ?>名</td>
                                <td>
                                    <span class="status-badge <?php echo $guardian['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $guardian['is_active'] ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($guardian['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="editGuardian(<?php echo htmlspecialchars(json_encode($guardian)); ?>)" class="btn btn-primary btn-sm">編集</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 編集モーダル -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-header">保護者情報の編集</h3>
            <form action="guardians_save.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="guardian_id" id="edit_guardian_id">
                <div class="form-group">
                    <label>保護者氏名 *</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="form-group">
                    <label>ユーザー名 *</label>
                    <input type="text" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label>新しいパスワード（変更する場合のみ）</label>
                    <input type="password" name="password" placeholder="変更しない場合は空欄">
                    <div class="help-text">8文字以上で設定してください</div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">キャンセル</button>
                    <button type="submit" class="btn btn-primary">更新する</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editGuardian(guardian) {
            document.getElementById('edit_guardian_id').value = guardian.id;
            document.getElementById('edit_full_name').value = guardian.full_name;
            document.getElementById('edit_username').value = guardian.username;
            document.getElementById('edit_email').value = guardian.email || '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // モーダル外クリックで閉じる
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
