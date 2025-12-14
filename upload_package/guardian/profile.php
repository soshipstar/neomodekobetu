<?php
/**
 * 保護者用 - プロフィール編集ページ
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

// 現在のユーザー情報を取得
$stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE id = ? AND user_type = 'guardian'");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /login.php');
    exit;
}

// プロフィール更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($fullName)) {
        $error = '氏名を入力してください。';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?, email = ?
                WHERE id = ? AND user_type = 'guardian'
            ");
            $stmt->execute([$fullName, $email ?: null, $userId]);
            $success = 'プロフィールを更新しました。';

            $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'データベースエラーが発生しました。';
        }
    }
}

// ページ開始
$currentPage = 'profile';
renderPageStart('guardian', $currentPage, 'プロフィール編集', ['classroom' => $classroom]);
?>

<style>
.profile-form-container {
    max-width: 600px;
    margin: 0 auto;
}

.info-box {
    background: var(--apple-gray-6);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-xl);
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--apple-gray-5);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: var(--text-subhead);
}

.info-value {
    color: var(--text-primary);
    font-size: var(--text-subhead);
}

.form-section-title {
    font-size: var(--text-title-3);
    color: var(--text-primary);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 2px solid var(--apple-purple);
}

.help-text {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-top: 5px;
}

.required {
    color: var(--apple-red);
    margin-left: 4px;
}

.button-group {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
}

.button-group .btn {
    flex: 1;
}

.links-section {
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--apple-gray-5);
}

.link-item {
    display: block;
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-sm);
    background: var(--apple-gray-6);
    border-radius: var(--radius-md);
    color: var(--apple-purple);
    text-decoration: none;
    transition: all var(--duration-fast) var(--ease-out);
    text-align: center;
    font-weight: 600;
}

.link-item:hover {
    background: var(--apple-purple);
    color: white;
    transform: translateY(-2px);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">プロフィール編集</h1>
        <p class="page-subtitle">ご自身の情報を更新できます</p>
    </div>
</div>

<div class="profile-form-container">
    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <!-- ログイン情報（読み取り専用） -->
            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">ユーザーID</span>
                    <span class="info-value"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <!-- プロフィール編集フォーム -->
            <form method="POST" action="">
                <h2 class="form-section-title">基本情報</h2>

                <div class="form-group">
                    <label class="form-label" for="full_name">氏名<span class="required">*</span></label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                        required
                        placeholder="例: 山田太郎"
                    >
                    <p class="help-text">お名前を入力してください</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">メールアドレス</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="例: yamada@example.com"
                    >
                    <p class="help-text">チャットメッセージの通知を受け取るメールアドレスを設定してください</p>
                </div>

                <div class="button-group">
                    <a href="dashboard.php" class="btn btn-secondary">キャンセル</a>
                    <button type="submit" class="btn btn-primary">保存する</button>
                </div>
            </form>

            <!-- その他のリンク -->
            <div class="links-section">
                <a href="change_password.php" class="link-item">🔐 パスワードを変更する</a>
                <a href="dashboard.php" class="link-item">← ダッシュボードに戻る</a>
            </div>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>
