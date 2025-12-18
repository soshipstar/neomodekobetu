<?php
/**
 * ログインページ
 * 教室のservice_typeに応じて通常版またはminimum版にリダイレクト
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

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
 * ユーザータイプとservice_typeに応じたリダイレクト先を取得
 */
function getRedirectUrl($userType, $serviceType, $isMaster = false) {
    // マスター管理者は常に通常版の管理画面へ（両方にアクセス可能）
    if ($isMaster) {
        return '/admin/index.php';
    }

    // minimum版の場合
    if ($serviceType === 'minimum') {
        switch ($userType) {
            case 'admin':
                return '/minimum/admin/index.php';
            case 'staff':
                return '/minimum/staff/index.php';
            case 'guardian':
                return '/minimum/guardian/dashboard.php';
            default:
                return '/login.php';
        }
    }

    // 通常版の場合
    switch ($userType) {
        case 'admin':
            return '/admin/index.php';
        case 'staff':
            return '/staff/renrakucho_activities.php';
        case 'tablet_user':
            return '/tablet/index.php';
        case 'student':
            return '/student/dashboard.php';
        case 'guardian':
            return '/guardian/dashboard.php';
        default:
            return '/login.php';
    }
}

// すでにログイン済みの場合はダッシュボードへリダイレクト
if (isLoggedIn() || isset($_SESSION['student_id'])) {
    $pdo = getDbConnection();
    $userType = $_SESSION['user_type'] ?? '';
    $classroomId = $_SESSION['classroom_id'] ?? null;
    $isMaster = $_SESSION['is_master'] ?? false;
    $serviceType = getClassroomServiceType($pdo, $classroomId);

    header('Location: ' . getRedirectUrl($userType, $serviceType, $isMaster));
    exit;
}

$error = '';

// デバッグモード（URLに?debug=1がある場合のみ）
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo '<pre>';
    echo 'Session Status: ' . session_status() . "\n";
    echo 'Session ID: ' . session_id() . "\n";
    echo 'Session Save Path: ' . session_save_path() . "\n";
    echo 'CSRF Token in Session: ' . (isset($_SESSION['csrf_token']) ? 'SET (' . substr($_SESSION['csrf_token'], 0, 10) . '...)' : 'NOT SET') . "\n";
    echo 'POST csrf_token: ' . (isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 10) . '...' : 'NOT SET') . "\n";
    echo '</pre>';
}

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
            // ログイン成功 - 教室のservice_typeを確認してリダイレクト
            $pdo = getDbConnection();
            $userType = $result['user']['user_type'];
            $classroomId = $result['user']['classroom_id'] ?? null;
            $isMaster = $result['user']['is_master'] ?? false;
            $serviceType = getClassroomServiceType($pdo, $classroomId);

            header('Location: ' . getRedirectUrl($userType, $serviceType, $isMaster));
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
    <title>ログイン - 個別支援連絡帳システム きづり</title>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <?php include __DIR__ . '/../includes/pwa_header.php'; ?>
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>個別支援連絡帳システム<br>きづり</h1>
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
