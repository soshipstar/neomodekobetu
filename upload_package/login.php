<?php
/**
 * ログインページ
 */

require_once __DIR__ . '/includes/auth.php';

// すでにログイン済みの場合はダッシュボードへリダイレクト
if (isLoggedIn()) {
    $userType = $_SESSION['user_type'];
    if ($userType === 'admin') {
        header('Location: /admin/index.php');
    } elseif ($userType === 'staff') {
        header('Location: /staff/renrakucho_activities.php');
    } elseif ($userType === 'tablet_user') {
        header('Location: /tablet/index.php');
    } else {
        header('Location: /guardian/dashboard.php');
    }
    exit;
}

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF検証
    requireCsrfToken();

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'ユーザー名とパスワードを入力してください';
    } else {
        $result = login($username, $password);

        if ($result['success']) {
            // ログイン成功 - ユーザータイプに応じてリダイレクト
            $userType = $result['user']['user_type'];
            if ($userType === 'admin') {
                header('Location: /admin/index.php');
            } elseif ($userType === 'staff') {
                header('Location: /staff/renrakucho_activities.php');
            } elseif ($userType === 'tablet_user') {
                header('Location: /tablet/index.php');
            } else {
                header('Location: /guardian/dashboard.php');
            }
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 個別支援連絡帳システム</title>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <?php include __DIR__ . '/includes/pwa_header.php'; ?>
    <style>
        body {
            background: var(--apple-bg-secondary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
        }

        .login-container {
            background: var(--apple-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl);
            width: 100%;
            max-width: 400px;
            animation: fadeIn var(--duration-slow) var(--ease-out);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .login-header h1 {
            color: var(--text-primary);
            font-size: var(--text-title-2);
            font-weight: var(--font-bold);
            margin-bottom: var(--spacing-sm);
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: var(--text-subhead);
        }

        .student-link {
            text-align: center;
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--apple-gray-5);
        }

        .student-link a {
            color: var(--primary-purple);
            text-decoration: none;
            font-size: var(--text-subhead);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-xs);
            transition: all var(--duration-fast) var(--ease-out);
        }

        .student-link a:hover {
            color: var(--primary-purple-dark);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>個別支援連絡帳システム</h1>
            <p>ログインしてください</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfTokenField(); ?>
            <div class="form-group">
                <label for="username" class="form-label">ユーザー名</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    required
                    autofocus
                    value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">パスワード</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary btn-lg">ログイン</button>
        </form>

        <div class="student-link">
            <a href="/student/login.php">
                <span>🎓</span>
                <span>生徒の方はこちら</span>
            </a>
        </div>
    </div>
</body>
</html>
