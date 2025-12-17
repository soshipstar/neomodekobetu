<?php
/**
 * セキュリティ機能
 * CSRF対策、セッションセキュリティ、入力検証
 * ミニマム版用
 */

/**
 * セキュアなセッション開始
 */
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        // セッションセキュリティ設定
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        // HTTPS時のみSecure属性を設定
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        session_start();

        // セッションIDの定期的な再生成（30分ごと）
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * CSRFトークンを生成
 * @return string
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        secureSessionStart();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークンを検証
 * @param string $token 検証するトークン
 * @return bool
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        secureSessionStart();
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRFトークンをリセット（ログアウト時などに使用）
 */
function resetCsrfToken() {
    if (session_status() !== PHP_SESSION_NONE) {
        unset($_SESSION['csrf_token']);
    }
}

/**
 * CSRFトークン入力フィールドを出力
 * @return string HTML input要素
 */
function csrfTokenField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * POSTリクエストのCSRF検証
 * 検証失敗時は403エラーを返す
 */
function requireCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!validateCsrfToken($token)) {
            http_response_code(403);
            die('不正なリクエストです（CSRFトークンが無効です）');
        }
    }
}

/**
 * XSS対策済みの出力
 * @param string $string
 * @return string
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * JSONレスポンスを返す
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * レート制限チェック（シンプル実装）
 * @param string $key 識別キー（例：IPアドレス）
 * @param int $maxAttempts 最大試行回数
 * @param int $decayMinutes 制限解除までの分数
 * @return bool 制限内ならtrue
 */
function checkRateLimit($key, $maxAttempts = 5, $decayMinutes = 15) {
    if (session_status() === PHP_SESSION_NONE) {
        secureSessionStart();
    }

    $rateLimitKey = 'rate_limit_' . md5($key);
    $now = time();

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [
            'attempts' => 0,
            'first_attempt' => $now
        ];
    }

    $data = $_SESSION[$rateLimitKey];

    // 制限時間を過ぎていたらリセット
    if ($now - $data['first_attempt'] > $decayMinutes * 60) {
        $_SESSION[$rateLimitKey] = [
            'attempts' => 1,
            'first_attempt' => $now
        ];
        return true;
    }

    // 試行回数をインクリメント
    $_SESSION[$rateLimitKey]['attempts']++;

    return $_SESSION[$rateLimitKey]['attempts'] <= $maxAttempts;
}

/**
 * レート制限をリセット
 * @param string $key
 */
function resetRateLimit($key) {
    if (session_status() !== PHP_SESSION_NONE) {
        $rateLimitKey = 'rate_limit_' . md5($key);
        unset($_SESSION[$rateLimitKey]);
    }
}
