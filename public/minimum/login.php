<?php
/**
 * ログインページ
 * かけはし（minimum版）専用
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

/**
 * 教室のservice_typeを取得
 */
function getClassroomServiceType($pdo, $classroomId) {
    if (!$classroomId) return 'normal';
    $stmt = $pdo->prepare("SELECT service_type FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $result = $stmt->fetch();
    return $result ? ($result['service_type'] ?? 'normal') : 'normal';
}

/**
 * ユーザータイプに応じたリダイレクト先を取得
 */
function getRedirectUrl($userType, $isMaster = false) {
    // マスター管理者は通常版の管理画面へ
    if ($isMaster) {
        return '/admin/index.php';
    }

    switch ($userType) {
        case 'admin':
            return '/minimum/admin/index.php';
        case 'staff':
            return '/minimum/staff/index.php';
        case 'guardian':
            return '/minimum/guardian/dashboard.php';
        default:
            return '/minimum/login.php';
    }
}

// すでにログイン済みの場合はダッシュボードへリダイレクト
if (isLoggedIn()) {
    $userType = $_SESSION['user_type'] ?? '';
    $isMaster = $_SESSION['is_master'] ?? false;

    header('Location: ' . getRedirectUrl($userType, $isMaster));
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
            // ログイン成功 - 教室のservice_typeを確認
            $pdo = getDbConnection();
            $userType = $result['user']['user_type'];
            $classroomId = $result['user']['classroom_id'] ?? null;
            $isMaster = $result['user']['is_master'] ?? false;
            $serviceType = getClassroomServiceType($pdo, $classroomId);

            // マスター管理者以外で通常版教室のユーザーはログイン不可
            if (!$isMaster && $serviceType !== 'minimum') {
                // ログアウト処理
                logout();
                $error = 'このアカウントは「かけはし」ではご利用いただけません。通常版ログイン画面からログインしてください。';
            } else {
                header('Location: ' . getRedirectUrl($userType, $isMaster));
                exit;
            }
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
    <meta name="color-scheme" content="light dark">
    <style>@media(prefers-color-scheme:dark){html,body{background:#1E1E1E;color:rgba(255,255,255,0.87)}}</style>
    <title>ログイン - かけはし</title>
    <link rel="stylesheet" href="/assets/css/google-design.css">
    <?php include __DIR__ . '/../../includes/pwa_header.php'; ?>
    <style>
        body {
            background: var(--md-bg-secondary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
        }

        .login-container {
            background: var(--md-bg-primary);
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>かけはし</h1>
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
    </div>
</body>
</html>
