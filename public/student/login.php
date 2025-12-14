<?php
/**
 * 生徒用ログインページ
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../config/database.php';

// セキュアなセッション開始
secureSessionStart();

// すでにログイン済みの場合はダッシュボードへリダイレクト
if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF検証
    requireCsrfToken();

    // レート制限チェック
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('student_login_' . $clientIp, 5, 15)) {
        $error = 'ログイン試行回数が上限に達しました。15分後に再度お試しください。';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'ユーザー名とパスワードを入力してください';
        } else {
            $pdo = getDbConnection();

            // 生徒アカウントを検索
            $stmt = $pdo->prepare("
                SELECT id, student_name, username, password_hash, guardian_id
                FROM students
                WHERE username = ? AND password_hash IS NOT NULL
            ");
            $stmt->execute([$username]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password_hash'])) {
                // ログイン成功 - セッションID再生成
                session_regenerate_id(true);
                resetRateLimit('student_login_' . $clientIp);

                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['student_name'];
                $_SESSION['student_username'] = $student['username'];
                $_SESSION['guardian_id'] = $student['guardian_id'];
                $_SESSION['user_type'] = 'student';

                // 最終ログイン日時を更新
                $stmt = $pdo->prepare("UPDATE students SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$student['id']]);

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'ユーザー名またはパスワードが正しくありません';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒ログイン - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
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
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .login-header h1 {
            color: var(--text-primary);
            font-size: var(--text-title-2);
            margin-bottom: var(--spacing-md);
        }

        .login-header p {
            color: var(--primary-purple);
            font-size: var(--text-callout);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: var(--text-subhead);
        }

        .form-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .error-message {
            background: rgba(255, 59, 48, 0.1);
            color: #c33;
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            font-size: var(--text-subhead);
            border-left: 4px solid #c33;
        }

        .login-button {
            width: 100%;
            padding: var(--spacing-md);
            background: var(--apple-bg-secondary);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: 600;
            cursor: pointer;
            transition: transform var(--duration-fast) var(--ease-out);
        }

        .login-button:hover {
            transform: translateY(-2px);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .back-link {
            margin-top: var(--spacing-lg);
            text-align: center;
        }

        .back-link a {
            color: var(--primary-purple);
            text-decoration: none;
            font-size: var(--text-subhead);
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .student-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="student-icon">🎓</div>

        <div class="login-header">
            <h1>生徒用ログイン</h1>
            <p>個別支援連絡帳システム</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfTokenField(); ?>
            <div class="form-group">
                <label for="username">ユーザー名</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    autofocus
                    value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="login-button">ログイン</button>
        </form>

        <div class="back-link">
            <a href="../login.php">← スタッフ・保護者ログイン</a>
        </div>
    </div>
</body>
</html>
