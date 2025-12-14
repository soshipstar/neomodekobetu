<?php
/**
 * 管理者用 - 保護者管理ページ
 * 保護者の登録・編集
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['admin', 'staff']);

$pdo = getDbConnection();

// 管理者の場合、教室IDを取得
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

if ($classroomId && !isMasterAdmin()) {
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
        u.password_plain,
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

// ページ開始
$currentPage = 'guardians';
renderPageStart('admin', $currentPage, '保護者管理');
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">保護者管理</h1>
        <p class="page-subtitle">保護者アカウントの登録・編集</p>
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
    <div class="alert alert-danger">
        エラー: <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<!-- 新規登録フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">新規保護者登録</h2>
        <form action="guardians_save.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">保護者氏名 *</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="例: 山田 花子">
                </div>
                <div class="form-group">
                    <label class="form-label">ユーザー名（ログインID） *</label>
                    <input type="text" name="username" class="form-control" required placeholder="例: yamada_h">
                    <small style="color: var(--text-secondary);">半角英数字とアンダースコアのみ使用可能</small>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">パスワード *</label>
                    <input type="password" name="password" class="form-control" required placeholder="8文字以上">
                    <small style="color: var(--text-secondary);">8文字以上で設定してください</small>
                </div>
                <div class="form-group">
                    <label class="form-label">パスワード（確認） *</label>
                    <input type="password" name="password_confirm" class="form-control" required placeholder="もう一度入力">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">メールアドレス（任意）</label>
                <input type="email" name="email" class="form-control" placeholder="例: yamada@example.com">
            </div>
            <div style="text-align: right;">
                <button type="submit" class="btn btn-success">登録する</button>
            </div>
        </form>
    </div>
</div>

<!-- 検索・絞り込みフォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">検索・絞り込み</h2>
        <form method="GET" action="">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label class="form-label">氏名</label>
                    <input type="text" name="search_name" class="form-control" value="<?= htmlspecialchars($searchName) ?>" placeholder="部分一致で検索">
                </div>
                <div class="form-group">
                    <label class="form-label">ユーザー名</label>
                    <input type="text" name="search_username" class="form-control" value="<?= htmlspecialchars($searchUsername) ?>" placeholder="部分一致で検索">
                </div>
                <div class="form-group">
                    <label class="form-label">メールアドレス</label>
                    <input type="text" name="search_email" class="form-control" value="<?= htmlspecialchars($searchEmail) ?>" placeholder="部分一致で検索">
                </div>
                <div class="form-group">
                    <label class="form-label">状態</label>
                    <select name="search_status" class="form-control">
                        <option value="">すべて</option>
                        <option value="1" <?= $searchStatus === '1' ? 'selected' : '' ?>>有効</option>
                        <option value="0" <?= $searchStatus === '0' ? 'selected' : '' ?>>無効</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">並び替え</label>
                    <select name="sort_by" class="form-control">
                        <option value="status_name" <?= $sortBy === 'status_name' ? 'selected' : '' ?>>状態→氏名</option>
                        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>氏名</option>
                        <option value="username" <?= $sortBy === 'username' ? 'selected' : '' ?>>ユーザー名</option>
                        <option value="email" <?= $sortBy === 'email' ? 'selected' : '' ?>>メールアドレス</option>
                        <option value="student_count" <?= $sortBy === 'student_count' ? 'selected' : '' ?>>生徒数</option>
                        <option value="created" <?= $sortBy === 'created' ? 'selected' : '' ?>>登録日</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end;">
                <a href="guardians.php" class="btn btn-secondary">クリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>
</div>

<!-- 保護者一覧 -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">保護者一覧（<?= count($guardians) ?>名）</h2>
        <table class="table">
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
                        <td colspan="9" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            登録されている保護者がいません
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($guardians as $guardian): ?>
                        <tr>
                            <td><?= $guardian['id'] ?></td>
                            <td><?= htmlspecialchars($guardian['full_name']) ?></td>
                            <td><?= htmlspecialchars($guardian['username']) ?></td>
                            <td><?= $guardian['email'] ? htmlspecialchars($guardian['email']) : '-' ?></td>
                            <td><?= $guardian['classroom_name'] ? htmlspecialchars($guardian['classroom_name']) : '-' ?></td>
                            <td><?= $guardian['student_count'] ?>名</td>
                            <td>
                                <span class="badge <?= $guardian['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $guardian['is_active'] ? '有効' : '無効' ?>
                                </span>
                            </td>
                            <td><?= date('Y/m/d', strtotime($guardian['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button onclick="editGuardian(<?= htmlspecialchars(json_encode($guardian)) ?>)" class="btn btn-primary btn-sm">編集</button>
                                    <a href="../staff/guardian_manual.php?guardian_id=<?= $guardian['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">マニュアル印刷</a>
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
        <h3 style="margin-bottom: var(--spacing-lg);">保護者情報の編集</h3>
        <form action="guardians_save.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="guardian_id" id="edit_guardian_id">
            <div class="form-group">
                <label class="form-label">保護者氏名 *</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">ユーザー名 *</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">メールアドレス</label>
                <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <?php if ($_SESSION['user_type'] === 'admin'): ?>
            <div class="form-group">
                <label class="form-label">現在のパスワード（管理者のみ閲覧可能）</label>
                <div style="padding: var(--spacing-md); background: var(--apple-gray-6); border: 1px solid var(--apple-gray-5); border-radius: var(--radius-sm); font-family: monospace;">
                    <span id="edit_password_display">-</span>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label">新しいパスワード（変更する場合のみ）</label>
                <input type="password" name="password" class="form-control" placeholder="変更しない場合は空欄">
                <small style="color: var(--text-secondary);">8文字以上で設定してください</small>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: var(--spacing-lg);">
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

        // パスワード表示（管理者のみ）
        <?php if ($_SESSION['user_type'] === 'admin'): ?>
        document.getElementById('edit_password_display').textContent = guardian.password_plain || '未設定';
        <?php endif; ?>

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

<?php renderPageEnd(); ?>
