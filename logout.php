<?php
/**
 * ログアウト処理
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
