<?php
/**
 * 生徒用認証システム
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * 生徒がログインしているか確認
 *
 * @return bool
 */
function isStudentLoggedIn() {
    return isset($_SESSION['student_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student';
}

/**
 * 現在ログイン中の生徒情報を取得
 *
 * @return array|null
 */
function getCurrentStudent() {
    if (!isStudentLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['student_id'],
        'student_name' => $_SESSION['student_name'],
        'username' => $_SESSION['student_username'],
        'guardian_id' => $_SESSION['guardian_id'] ?? null
    ];
}

/**
 * 生徒ログインを必須にする（ログインしていない場合はログインページへリダイレクト）
 */
function requireStudentLogin() {
    if (!isStudentLoggedIn()) {
        header('Location: /student/login.php');
        exit;
    }
}

/**
 * 生徒をログアウトさせる
 */
function studentLogout() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * 生徒が指定された生徒IDのデータにアクセスできるか確認
 *
 * @param int $studentId
 * @return bool
 */
function canAccessStudentData($studentId) {
    if (!isStudentLoggedIn()) {
        return false;
    }

    return $_SESSION['student_id'] == $studentId;
}
