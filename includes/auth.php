<?php
/**
 * ユーザー認証システム
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';

// セキュアなセッション開始
secureSessionStart();

/**
 * ログイン処理（スタッフ・管理者・保護者・生徒統合）
 * @param string $username ユーザー名
 * @param string $password パスワード
 * @return array 成功時: ['success' => true, 'user' => ユーザー情報], 失敗時: ['success' => false, 'error' => エラーメッセージ]
 */
function login($username, $password) {
    // レート制限チェック（IPアドレスベース）
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('login_' . $clientIp, 5, 15)) {
        return ['success' => false, 'error' => 'ログイン試行回数が上限に達しました。15分後に再度お試しください。'];
    }

    $pdo = getDbConnection();

    try {
        // まずusersテーブルで検索（スタッフ・管理者・保護者）
        $stmt = $pdo->prepare("
            SELECT id, username, password, full_name, user_type, email, is_active, is_master, classroom_id
            FROM users
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // usersテーブルでログイン成功
            session_regenerate_id(true);
            resetRateLimit('login_' . $clientIp);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_master'] = $user['is_master'] ?? 0;

            if ($user['is_master'] == 1) {
                $_SESSION['classroom_id'] = null;
            } else {
                $_SESSION['classroom_id'] = $user['classroom_id'];
            }

            $_SESSION['login_time'] = time();
            $_SESSION['last_regeneration'] = time();

            unset($user['password']);
            return ['success' => true, 'user' => $user];
        }

        // usersテーブルで見つからない場合、studentsテーブルで検索
        $stmt = $pdo->prepare("
            SELECT id, student_name, username, password_hash, guardian_id
            FROM students
            WHERE username = ? AND password_hash IS NOT NULL
        ");
        $stmt->execute([$username]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password_hash'])) {
            // 生徒としてログイン成功
            session_regenerate_id(true);
            resetRateLimit('login_' . $clientIp);

            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['student_name'];
            $_SESSION['student_username'] = $student['username'];
            $_SESSION['guardian_id'] = $student['guardian_id'];
            $_SESSION['user_type'] = 'student';
            $_SESSION['login_time'] = time();
            $_SESSION['last_regeneration'] = time();

            // 最終ログイン日時を更新
            $stmt = $pdo->prepare("UPDATE students SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$student['id']]);

            return ['success' => true, 'user' => [
                'id' => $student['id'],
                'username' => $student['username'],
                'full_name' => $student['student_name'],
                'user_type' => 'student'
            ]];
        }

        return ['success' => false, 'error' => 'ユーザー名またはパスワードが正しくありません'];
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
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';

        // 無限リダイレクトを防ぐため、ログインページへのリダイレクトをチェック
        if (strpos($currentPath, '/login.php') !== false) {
            http_response_code(500);
            die('セッションエラー: ログイン状態を保持できません。ブラウザのCookieとキャッシュをクリアして再度お試しください。');
        }

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
        // 無限リダイレクトを防ぐため、現在のURLをチェック
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';

        // 権限がない場合は、ユーザータイプに応じた適切なページへリダイレクト
        $redirectPath = '';
        switch ($_SESSION['user_type'] ?? '') {
            case 'admin':
                $redirectPath = '/admin/index.php';
                break;
            case 'staff':
                $redirectPath = '/staff/renrakucho_activities.php';
                break;
            case 'guardian':
                $redirectPath = '/guardian/dashboard.php';
                break;
            case 'tablet_user':
                $redirectPath = '/tablet/index.php';
                break;
            default:
                $redirectPath = '/login.php';
                break;
        }

        // 現在のページとリダイレクト先が同じ場合は、無限ループを防ぐためエラーを表示
        if (strpos($currentPath, $redirectPath) !== false) {
            $allowedTypesStr = is_array($allowedTypes) ? implode(',', $allowedTypes) : $allowedTypes;
            http_response_code(403);
            die('アクセス権限がありません。<br>ユーザータイプ: ' . ($_SESSION['user_type'] ?? 'NOT SET') . '<br>要求された権限: ' . $allowedTypesStr . '<br>現在のパス: ' . htmlspecialchars($currentPath));
        }

        header('Location: ' . $redirectPath);
        exit;
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
        'is_master' => $_SESSION['is_master'] ?? 0,
        'classroom_id' => $_SESSION['classroom_id'] ?? null
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
        'guardian' => '保護者',
        'tablet_user' => 'タブレットユーザー'
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
        // 無限リダイレクトを防ぐため、現在のURLをチェック
        $currentPath = $_SERVER['REQUEST_URI'];

        // マスター管理者でない場合は適切なページへリダイレクト
        $redirectPath = '';
        if ($_SESSION['user_type'] === 'admin') {
            // 通常管理者は管理者トップへ
            $redirectPath = '/admin/index.php';
        } else {
            // その他のユーザータイプは各トップページへ
            switch ($_SESSION['user_type']) {
                case 'staff':
                    $redirectPath = '/staff/renrakucho_activities.php';
                    break;
                case 'guardian':
                    $redirectPath = '/guardian/dashboard.php';
                    break;
                default:
                    $redirectPath = '/login.php';
                    break;
            }
        }

        // 現在のページとリダイレクト先が同じ場合は、無限ループを防ぐためエラーを表示
        if (strpos($currentPath, $redirectPath) !== false) {
            http_response_code(403);
            die('マスター管理者権限が必要です。セッション情報を確認してください。');
        }

        header('Location: ' . $redirectPath);
        exit;
    }
}
