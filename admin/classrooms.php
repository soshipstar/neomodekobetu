<?php
/**
 * 教室管理（マスター管理者専用）
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// マスター管理者チェック
requireMasterAdmin();

$pdo = getDbConnection();

// 全教室を取得
$stmt = $pdo->query("
    SELECT
        c.*,
        COUNT(DISTINCT u.id) as admin_count,
        COUNT(DISTINCT s.id) as student_count
    FROM classrooms c
    LEFT JOIN users u ON c.id = u.classroom_id AND u.user_type = 'admin'
    LEFT JOIN students s ON c.id = s.classroom_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$classrooms = $stmt->fetchAll();

$successMessage = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教室管理 - マスター管理者</title>
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
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .classrooms-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .classrooms-table th,
        .classrooms-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .classrooms-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .classrooms-table tr:hover {
            background: #f8f9fa;
        }
        .logo-preview {
            height: 40px;
            width: auto;
            max-width: 100px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-buttons button {
            padding: 6px 12px;
            font-size: 12px;
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
            margin: 5% auto;
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
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🏢 教室管理<span class="master-badge">★マスター専用</span></h1>
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
                <h2>登録教室一覧</h2>
                <button class="btn btn-primary" onclick="openAddModal()">➕ 新規教室登録</button>
            </div>

            <table class="classrooms-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ロゴ</th>
                        <th>教室名</th>
                        <th>住所</th>
                        <th>電話番号</th>
                        <th>管理者数</th>
                        <th>生徒数</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classrooms as $classroom): ?>
                        <tr>
                            <td><?php echo $classroom['id']; ?></td>
                            <td>
                                <?php if ($classroom['logo_path'] && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($classroom['logo_path']); ?>" alt="ロゴ" class="logo-preview">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($classroom['classroom_name']); ?></td>
                            <td><?php echo htmlspecialchars($classroom['address'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($classroom['phone'] ?? '-'); ?></td>
                            <td><?php echo $classroom['admin_count']; ?>人</td>
                            <td><?php echo $classroom['student_count']; ?>人</td>
                            <td><?php echo date('Y/m/d', strtotime($classroom['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($classroom), ENT_QUOTES); ?>)">編集</button>
                                    <button class="btn btn-danger" onclick="deleteClassroom(<?php echo $classroom['id']; ?>, '<?php echo htmlspecialchars($classroom['classroom_name'], ENT_QUOTES); ?>')">削除</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($classrooms)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                登録されている教室がありません
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 新規登録モーダル -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>新規教室登録</h2>
            <form action="classrooms_save.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>教室名 *</label>
                    <input type="text" name="classroom_name" required>
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <textarea name="address"></textarea>
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" name="phone">
                </div>
                <div class="form-group">
                    <label>ロゴ画像（2MB以内のJPEG, PNG, GIF）</label>
                    <input type="file" name="logo" accept="image/*">
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;">登録</button>
            </form>
        </div>
    </div>

    <!-- 編集モーダル -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>教室情報編集</h2>
            <form action="classrooms_save.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="classroom_id" id="edit_classroom_id">
                <div class="form-group">
                    <label>教室名 *</label>
                    <input type="text" name="classroom_name" id="edit_classroom_name" required>
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <textarea name="address" id="edit_address"></textarea>
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" name="phone" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>ロゴ画像（2MB以内のJPEG, PNG, GIF）</label>
                    <input type="file" name="logo" accept="image/*">
                    <div id="current_logo" style="margin-top: 10px;"></div>
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

        function openEditModal(classroom) {
            document.getElementById('edit_classroom_id').value = classroom.id;
            document.getElementById('edit_classroom_name').value = classroom.classroom_name;
            document.getElementById('edit_address').value = classroom.address || '';
            document.getElementById('edit_phone').value = classroom.phone || '';

            const logoDiv = document.getElementById('current_logo');
            if (classroom.logo_path) {
                logoDiv.innerHTML = '<p style="color: #666; font-size: 12px;">現在のロゴ:</p><img src="../' + classroom.logo_path + '" style="max-width: 200px; max-height: 100px;">';
            } else {
                logoDiv.innerHTML = '<p style="color: #666; font-size: 12px;">現在ロゴは未設定です</p>';
            }

            document.getElementById('editModal').style.display = 'block';
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
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
