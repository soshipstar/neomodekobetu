<?php
/**
 * ログアウト処理
 * ミニマム版用
 */

require_once __DIR__ . '/includes/auth.php';

logout();

header('Location: /login.php');
exit;
