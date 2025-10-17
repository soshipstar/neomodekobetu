<?php
/**
 * 緊急ログアウトページ
 * エラーが発生してログアウトできない場合に使用
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// POSTリクエストでログアウト実行
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // セッションをクリア
    $_SESSION = [];

    // セッションクッキーを削除
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // セッションを破棄
    session_destroy();

    // ログインページへリダイレクト
    header('Location: /login.php');
    exit;
}

// 現在のセッション情報を取得
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['full_name'] ?? 'ゲスト';
$userType = $_SESSION['user_type'] ?? '不明';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>緊急ログアウト</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: left;
        }
        .info-box p {
            margin-bottom: 10px;
            color: #333;
        }
        .info-box strong {
            color: #667eea;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .login-link {
            display: block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .login-link:hover {
            text-decoration: underline;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-logged-in {
            background: #d4edda;
            color: #155724;
        }
        .status-logged-out {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚨 緊急ログアウト</h1>

        <?php if ($isLoggedIn): ?>
            <div class="info-box">
                <p><strong>ログイン状態:</strong> <span class="status status-logged-in">ログイン中</span></p>
                <p><strong>ユーザー名:</strong> <?php echo htmlspecialchars($userName); ?></p>
                <p><strong>ユーザータイプ:</strong> <?php echo htmlspecialchars($userType); ?></p>
            </div>

            <p style="margin-bottom: 20px; color: #666;">
                ページエラーでログアウトできない場合は、<br>
                下のボタンを押してログアウトしてください。
            </p>

            <form method="POST">
                <button type="submit" class="logout-btn">ログアウトする</button>
            </form>
        <?php else: ?>
            <div class="info-box">
                <p><strong>ログイン状態:</strong> <span class="status status-logged-out">ログアウト済み</span></p>
            </div>

            <p style="margin-bottom: 20px; color: #666;">
                すでにログアウトしています。
            </p>

            <a href="/login.php" class="logout-btn" style="display: inline-block; text-decoration: none;">ログインページへ</a>
        <?php endif; ?>

        <a href="/login.php" class="login-link">ログインページに戻る</a>
    </div>
</body>
</html>
