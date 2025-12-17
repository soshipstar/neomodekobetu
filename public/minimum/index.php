<?php
/**
 * インデックスページ（リダイレクト用）
 * ミニマム版用
 */

require_once __DIR__ . '/includes/auth.php';

// ログイン済みの場合は各ダッシュボードへ
if (isLoggedIn()) {
    $userType = $_SESSION['user_type'] ?? '';
    if ($userType === 'admin') {
        header('Location: /minimum/admin/index.php');
    } elseif ($userType === 'staff') {
        header('Location: /minimum/staff/index.php');
    } else {
        header('Location: /minimum/guardian/dashboard.php');
    }
    exit;
}

// 未ログインの場合はログインページへ
header('Location: /login.php');
exit;
