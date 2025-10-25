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

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 検索・並び替えパラメータ
$searchName = $_GET['search_name'] ?? '';
$searchUsername = $_GET['search_username'] ?? '';
$searchEmail = $_GET['search_email'] ?? '';
$searchStatus = $_GET['search_status'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'status_name';

// WHERE句の構築
$where = ["u.user_type = 'guardian'"];
$params = [];

if ($classroomId) {
    $where[] = "u.classroom_id = ?";
    $params[] = $classroomId;
}

if (!empty($searchName)) {
    $where[] = "u.full_name LIKE ?";
    $params[] = "%{$searchName}%";
}

if (!empty($searchUsername)) {
    $where[] = "u.username LIKE ?";
    $params[] = "%{$searchUsername}%";
}

if (!empty($searchEmail)) {
    $where[] = "u.email LIKE ?";
    $params[] = "%{$searchEmail}%";
}

if ($searchStatus !== '') {
    $where[] = "u.is_active = ?";
    $params[] = (int)$searchStatus;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// ORDER BY句の構築
$orderBy = "ORDER BY u.is_active DESC, u.full_name";
switch ($sortBy) {
    case 'name':
        $orderBy = "ORDER BY u.full_name";
        break;
    case 'username':
        $orderBy = "ORDER BY u.username";
        break;
    case 'email':
        $orderBy = "ORDER BY u.email";
        break;
    case 'student_count':
        $orderBy = "ORDER BY student_count DESC, u.full_name";
        break;
    case 'status':
        $orderBy = "ORDER BY u.is_active DESC, u.full_name";
        break;
    case 'created':
        $orderBy = "ORDER BY u.created_at DESC";
        break;
    case 'status_name':
    default:
        $orderBy = "ORDER BY u.is_active DESC, u.full_name";
        break;
}

// 保護者を取得
$sql = "
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.is_active,
        u.created_at,
        u.classroom_id,
        c.classroom_name,
        COUNT(s.id) as student_count
    FROM users u
    LEFT JOIN students s ON u.id = s.guardian_id
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    {$whereClause}
    GROUP BY u.id
    {$orderBy}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: white;
            font-size: 24px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
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
        select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        select:focus {
            outline: none;
            border-color: #667eea;
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

        /* デスクトップ用レイアウト（デフォルト） */
        @media (min-width: 769px) {
            .header-actions {
                display: flex !important;
                flex-direction: row !important;
            }
        }

        /* レスポンシブデザイン */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions .btn {
                width: 100%;
                text-align: center;
            }

            .content-box {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            table th,
            table td {
                padding: 8px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }

            .modal-content {
                padding: 20px;
                width: 95%;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 18px;
            }

            table {
                font-size: 12px;
            }
        }

        /* ドロップダウンメニュー */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            font-family: inherit;
            transition: all 0.3s;
        }

        .dropdown-toggle:hover {
            background: rgba(255,255,255,0.3);
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .dropdown.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 200px;
            margin-top: 5px;
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown.open .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
        }

        .dropdown-menu a .menu-icon {
            margin-right: 8px;
        }

        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍👩‍👧 保護者管理</h1>
            <div class="header-actions">
                <!-- 保護者ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        👨‍👩‍👧 保護者
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="chat.php">
                            <span class="menu-icon">💬</span>保護者チャット
                        </a>
                        <a href="submission_management.php">
                            <span class="menu-icon">📮</span>提出期限管理
                        </a>
                    </div>
                </div>

                <!-- 生徒ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🎓 生徒
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="student_chats.php">
                            <span class="menu-icon">💬</span>生徒チャット
                        </a>
                        <a href="student_weekly_plans.php">
                            <span class="menu-icon">📝</span>週間計画表
                        </a>
                    </div>
                </div>

                <!-- かけはし管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🌉 かけはし管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="kakehashi_staff.php">
                            <span class="menu-icon">✏️</span>スタッフかけはし入力
                        </a>
                        <a href="kakehashi_guardian_view.php">
                            <span class="menu-icon">📋</span>保護者かけはし確認
                        </a>
                        <a href="kobetsu_plan.php">
                            <span class="menu-icon">📄</span>個別支援計画書作成
                        </a>
                        <a href="kobetsu_monitoring.php">
                            <span class="menu-icon">📊</span>モニタリング表作成
                        </a>
                        <a href="newsletter_create.php">
                            <span class="menu-icon">📰</span>施設通信を作成
                        </a>
                    </div>
                </div>

                <!-- マスタ管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        ⚙️ マスタ管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="students.php">
                            <span class="menu-icon">👥</span>生徒管理
                        </a>
                        <a href="guardians.php">
                            <span class="menu-icon">👨‍👩‍👧</span>保護者管理
                        </a>
                        <a href="holidays.php">
                            <span class="menu-icon">🗓️</span>休日管理
                        </a>
                        <a href="events.php">
                            <span class="menu-icon">🎉</span>イベント管理
                        </a>
                    </div>
                </div>

                <a href="renrakucho_activities.php" class="logout-btn">← 活動管理</a>
                <a href="/logout.php" class="logout-btn">ログアウト</a>
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
                    case 'deleted':
                        echo '保護者を削除しました。';
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

        <!-- 検索・絞り込みフォーム -->
        <div class="content-box">
            <h2 class="section-title">🔍 検索・絞り込み</h2>
            <form method="GET" action="">
                <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="form-group">
                        <label>氏名</label>
                        <input type="text" name="search_name" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="部分一致で検索">
                    </div>
                    <div class="form-group">
                        <label>ユーザー名</label>
                        <input type="text" name="search_username" value="<?php echo htmlspecialchars($searchUsername); ?>" placeholder="部分一致で検索">
                    </div>
                    <div class="form-group">
                        <label>メールアドレス</label>
                        <input type="text" name="search_email" value="<?php echo htmlspecialchars($searchEmail); ?>" placeholder="部分一致で検索">
                    </div>
                    <div class="form-group">
                        <label>状態</label>
                        <select name="search_status">
                            <option value="">すべて</option>
                            <option value="1" <?php echo $searchStatus === '1' ? 'selected' : ''; ?>>有効</option>
                            <option value="0" <?php echo $searchStatus === '0' ? 'selected' : ''; ?>>無効</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>並び替え</label>
                        <select name="sort_by">
                            <option value="status_name" <?php echo $sortBy === 'status_name' ? 'selected' : ''; ?>>状態→氏名</option>
                            <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>氏名</option>
                            <option value="username" <?php echo $sortBy === 'username' ? 'selected' : ''; ?>>ユーザー名</option>
                            <option value="email" <?php echo $sortBy === 'email' ? 'selected' : ''; ?>>メールアドレス</option>
                            <option value="student_count" <?php echo $sortBy === 'student_count' ? 'selected' : ''; ?>>生徒数</option>
                            <option value="created" <?php echo $sortBy === 'created' ? 'selected' : ''; ?>>登録日</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary">検索</button>
                    <a href="guardians.php" class="btn btn-secondary">クリア</a>
                </div>
            </form>
        </div>

        <!-- 保護者一覧 -->
        <div class="content-box">
            <h2 class="section-title">保護者一覧（<?php echo count($guardians); ?>名）</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>氏名</th>
                        <th>ユーザー名</th>
                        <th>メールアドレス</th>
                        <th>所属教室</th>
                        <th>紐づく生徒</th>
                        <th>状態</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guardians)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
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
                                <td><?php echo $guardian['classroom_name'] ? htmlspecialchars($guardian['classroom_name']) : '-'; ?></td>
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
                                        <a href="guardian_manual.php?guardian_id=<?php echo $guardian['id']; ?>" target="_blank" class="btn btn-secondary btn-sm">📄 マニュアル印刷</a>
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
                    <div style="flex: 1;"></div>
                    <button type="button" onclick="deleteGuardian()" class="btn btn-danger" style="margin-right: 10px;">削除</button>
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

        function deleteGuardian() {
            const guardianId = document.getElementById('edit_guardian_id').value;
            const guardianName = document.getElementById('edit_full_name').value;

            if (confirm(`本当に「${guardianName}」を削除しますか？\n\nこの操作は取り消せません。関連する生徒との紐付けも解除されます。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'guardians_save.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'guardian_id';
                idInput.value = guardianId;
                form.appendChild(idInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // モーダル外クリックで閉じる
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ドロップダウンメニューのトグル
        function toggleDropdown(event, button) {
            event.stopPropagation();
            const dropdown = button.closest('.dropdown');
            const isOpen = dropdown.classList.contains('open');

            // 他のドロップダウンを閉じる
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });

            // このドロップダウンをトグル
            if (!isOpen) {
                dropdown.classList.add('open');
            }
        }

        // ドロップダウン外をクリックしたら閉じる
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });
        });

        // ドロップダウン内のクリックで伝播を止める
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
