<?php
/**
 * 生徒用 - パスワード変更ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $_SESSION['student_id'];
$error = '';
$success = '';

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '全ての項目を入力してください。';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '新しいパスワードが一致しません。';
    } elseif (strlen($newPassword) < 6) {
        $error = 'パスワードは6文字以上で設定してください。';
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $studentData = $stmt->fetch();

        if (!$studentData || !password_verify($currentPassword, $studentData['password_hash'])) {
            $error = '現在のパスワードが正しくありません。';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE students SET password_hash = ?, password_plain = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $newPassword, $studentId]);
            $success = 'パスワードを変更しました。';
        }
    }
}

$_SESSION['user_type'] = 'student';
$_SESSION['full_name'] = $student['student_name'];

// ページ開始
$currentPage = 'change_password';
renderPageStart('student', $currentPage, 'パスワード変更');
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
                    <div class="help-text">6文字以上で設定してください</div>
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
