<?php
/**
 * 保護者用 - パスワード変更ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireLogin();
checkUserType('guardian');

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// 教室情報を取得
$classroom = null;
$classroomStmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$classroomStmt->execute([$userId]);
$classroom = $classroomStmt->fetch();

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // バリデーション
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '全ての項目を入力してください。';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '新しいパスワードが一致しません。';
    } elseif (strlen($newPassword) < 8) {
        $error = 'パスワードは8文字以上で設定してください。';
    } else {
        // 現在のパスワードを確認
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND user_type = 'guardian'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $error = '現在のパスワードが正しくありません。';
        } else {
            // パスワードを更新
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?, password_plain = ?
                WHERE id = ? AND user_type = 'guardian'
            ");
            $stmt->execute([$hashedPassword, $newPassword, $userId]);
            $success = 'パスワードを変更しました。';
        }
    }
}

// ページ開始
$currentPage = 'change_password';
renderPageStart('guardian', $currentPage, 'パスワード変更', ['classroom' => $classroom]);
?>

<style>
.password-form-container {
    max-width: 500px;
    margin: 0 auto;
}

.help-text {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-top: 5px;
}

.button-group {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
}

.button-group .btn {
    flex: 1;
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">パスワード変更</h1>
        <p class="page-subtitle">新しいパスワードを設定してください</p>
    </div>
</div>

<div class="password-form-container">
    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="current_password">現在のパスワード *</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">新しいパスワード *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                    <div class="help-text">8文字以上で設定してください</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">新しいパスワード（確認） *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <div class="button-group">
                    <a href="dashboard.php" class="btn btn-secondary">キャンセル</a>
                    <button type="submit" class="btn btn-primary">変更する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>
