<?php
/**
 * 生徒用ログアウト
 */

require_once __DIR__ . '/../includes/student_auth.php';

studentLogout();

header('Location: login.php');
exit;
