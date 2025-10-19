<?php
/**
 * ユーザー認証システム
 */

// セッション開始（まだ開始されていない場合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * ログイン処理
 * @param string $username ユーザー名
 * @param string $password パスワード
 * @return array 成功時: ['success' => true, 'user' => ユーザー情報], 失敗時: ['success' => false, 'error' => エラーメッセージ]
 */
function login($username, $password) {
    $pdo = getDbConnection();

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password, full_name, user_type, email, is_active, is_master
            FROM users
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'ユーザー名またはパスワードが正しくありません'];
        }

        // パスワード検証
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'ユーザー名またはパスワードが正しくありません'];
        }

        // セッションに保存
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_master'] = $user['is_master'] ?? 0;
        $_SESSION['login_time'] = time();

        // パスワードを除外して返す
        unset($user['password']);

        return ['success' => true, 'user' => $user];
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'error' => 'ログイン処理中にエラーが発生しました'];
    }
}

/**
 * ログアウト処理
 */
function logout() {
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
 * ログイン確認
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * ログイン必須チェック（未ログインの場合はログインページへリダイレクト）
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * 権限チェック
 * @param string|array $allowedTypes 許可するユーザータイプ（文字列または配列）
 * @return bool
 */
function checkUserType($allowedTypes) {
    if (!isLoggedIn()) {
        return false;
    }

    // 文字列の場合は配列に変換
    if (is_string($allowedTypes)) {
        $allowedTypes = [$allowedTypes];
    }

    return in_array($_SESSION['user_type'], $allowedTypes);
}

/**
 * 権限必須チェック（権限がない場合はエラーページへ）
 * @param string|array $allowedTypes 許可するユーザータイプ（文字列または配列）
 */
function requireUserType($allowedTypes) {
    requireLogin();

    if (!checkUserType($allowedTypes)) {
        // 権限がない場合は、ユーザータイプに応じた適切なページへリダイレクト
        switch ($_SESSION['user_type']) {
            case 'admin':
                header('Location: /admin/index.php');
                exit;
            case 'staff':
                header('Location: /staff/renrakucho_activities.php');
                exit;
            case 'guardian':
                header('Location: /guardian/dashboard.php');
                exit;
            default:
                header('Location: /login.php');
                exit;
        }
    }
}

/**
 * 現在のユーザー情報を取得
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'user_type' => $_SESSION['user_type'],
        'email' => $_SESSION['email'],
        'is_master' => $_SESSION['is_master'] ?? 0
    ];
}

/**
 * ユーザータイプの日本語名を取得
 * @param string $userType
 * @return string
 */
function getUserTypeName($userType) {
    $types = [
        'admin' => '管理者',
        'staff' => 'スタッフ',
        'guardian' => '保護者'
    ];

    return $types[$userType] ?? '不明';
}

/**
 * マスター管理者かどうかをチェック
 * @return bool
 */
function isMasterAdmin() {
    return isLoggedIn() &&
           $_SESSION['user_type'] === 'admin' &&
           isset($_SESSION['is_master']) &&
           $_SESSION['is_master'] == 1;
}

/**
 * マスター管理者必須チェック（マスター管理者以外はリダイレクト）
 */
function requireMasterAdmin() {
    requireLogin();

    if (!isMasterAdmin()) {
        // マスター管理者でない場合は適切なページへリダイレクト
        if ($_SESSION['user_type'] === 'admin') {
            // 通常管理者は管理者トップへ
            header('Location: /admin/index.php');
        } else {
            // その他のユーザータイプは各トップページへ
            switch ($_SESSION['user_type']) {
                case 'staff':
                    header('Location: /staff/renrakucho_activities.php');
                    exit;
                case 'guardian':
                    header('Location: /guardian/dashboard.php');
                    exit;
                default:
                    header('Location: /login.php');
                    exit;
            }
        }
        exit;
    }
}
