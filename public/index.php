<?php
/**
 * トップページ - ログインページへリダイレクト
 */

require_once __DIR__ . '/../includes/auth.php';

// ログイン済みの場合はユーザータイプに応じてリダイレクト
if (isLoggedIn()) {
    $userType = $_SESSION['user_type'];

    if ($userType === 'staff' || $userType === 'admin') {
        header('Location: /staff/renrakucho_activities.php');
    } else if ($userType === 'guardian') {
        header('Location: /guardian/dashboard.php');
    } else {
        header('Location: /login.php');
    }
} else {
    // 未ログインの場合はログインページへ
    header('Location: /login.php');
}

exit;
