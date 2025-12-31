<?php
/**
 * スタッフ用 - プロフィール編集ページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireLogin();
checkUserType('staff');

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// 現在のユーザー情報を取得
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.full_name, u.email, u.classroom_id, c.classroom_name
    FROM users u
    LEFT JOIN classrooms c ON u.classroom_id = c.id
    WHERE u.id = ? AND u.user_type = 'staff'
");
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
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // バリデーション
    if (empty($fullName)) {
        $error = '氏名を入力してください。';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } elseif (!empty($newPassword) || !empty($confirmPassword)) {
        // パスワード変更がある場合
        if (empty($currentPassword)) {
            $error = '現在のパスワードを入力してください。';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '新しいパスワードが一致しません。';
        } elseif (strlen($newPassword) < 8) {
            $error = 'パスワードは8文字以上で設定してください。';
        } else {
            // 現在のパスワードを確認
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userPass = $stmt->fetch();

            if (!password_verify($currentPassword, $userPass['password'])) {
                $error = '現在のパスワードが正しくありません。';
            }
        }
    }

    if (!$error) {
        try {
            if (!empty($newPassword)) {
                // パスワードも更新
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, password = ?, password_plain = ?
                    WHERE id = ? AND user_type = 'staff'
                ");
                $stmt->execute([$fullName, $email ?: null, $hashedPassword, $newPassword, $userId]);
                $success = 'プロフィールとパスワードを更新しました。';
            } else {
                // プロフィールのみ更新
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?
                    WHERE id = ? AND user_type = 'staff'
                ");
                $stmt->execute([$fullName, $email ?: null, $userId]);
                $success = 'プロフィールを更新しました。';
            }

            // 更新後の情報を再取得
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.full_name, u.email, u.classroom_id, c.classroom_name
                FROM users u
                LEFT JOIN classrooms c ON u.classroom_id = c.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'データベースエラーが発生しました。';
        }
    }
}

// ページ開始
$currentPage = 'profile';
renderPageStart('staff', $currentPage, 'プロフィール編集');
?>

<style>
.profile-form-container {
    max-width: 600px;
    margin: 0 auto;
}

.info-box {
    background: var(--md-gray-6);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-xl);
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--md-gray-5);
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

.form-section {
    margin-bottom: var(--spacing-xl);
}

.form-section-title {
    font-size: var(--text-headline);
    color: var(--md-blue);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 2px solid var(--md-blue);
}

.divider {
    height: 1px;
    background: var(--md-gray-5);
    margin: var(--spacing-xl) 0;
}

.password-section {
    background: var(--md-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--md-purple);
}

.button-group {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
}

.button-group .btn {
    flex: 1;
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--md-gray-5); }
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">プロフィール編集</h1>
        <p class="page-subtitle">ご自身の情報を更新できます</p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理へ戻る</a>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="profile-form-container">
    <div class="card">
        <div class="card-body">
            <!-- ログイン情報（読み取り専用） -->
            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">ユーザーID</span>
                    <span class="info-value"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">所属教室</span>
                    <span class="info-value"><?= htmlspecialchars($user['classroom_name'] ?? '未設定', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <!-- プロフィール編集フォーム -->
            <form method="POST" action="">
                <div class="form-section">
                    <h2 class="form-section-title">基本情報</h2>

                    <div class="form-group">
                        <label class="form-label" for="full_name">氏名 <span style="color: var(--md-red);">*</span></label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-control"
                            value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                            required
                            placeholder="例: 山田太郎"
                        >
                        <div class="help-text" style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 5px;">お名前を入力してください</div>
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
                        <div class="help-text" style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 5px;">チャットメッセージの通知を受け取るメールアドレスを設定してください</div>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- パスワード変更セクション -->
                <div class="form-section">
                    <h2 class="form-section-title">パスワード変更（任意）</h2>
                    <div class="password-section">
                        <p style="margin-bottom: var(--spacing-lg); color: var(--text-secondary); font-size: var(--text-subhead);">
                            パスワードを変更する場合のみ入力してください
                        </p>

                        <div class="form-group">
                            <label class="form-label" for="current_password">現在のパスワード</label>
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-control"
                                placeholder="パスワードを変更する場合のみ入力"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">新しいパスワード</label>
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                class="form-control"
                                placeholder="8文字以上"
                            >
                            <div class="help-text" style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 5px;">8文字以上で設定してください</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">新しいパスワード（確認）</label>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                placeholder="もう一度入力してください"
                            >
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <a href="renrakucho_activities.php" class="btn btn-secondary">キャンセル</a>
                    <button type="submit" class="btn btn-primary">保存する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>
