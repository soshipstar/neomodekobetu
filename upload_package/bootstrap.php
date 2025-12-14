<?php
/**
 * アプリケーションブートストラップ
 * パス定義とセキュリティ設定を一元管理
 */

// エラーレポート設定（本番では無効化）
if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// パス定義
define('ROOT_PATH', __DIR__);
define('PUBLIC_PATH', ROOT_PATH);  // 本番環境ではルート直下
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// セッションセキュリティ設定
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

// セッションIDの再生成間隔（秒）
define('SESSION_REGENERATE_INTERVAL', 1800); // 30分

// 環境変数読み込み
require_once INCLUDES_PATH . '/env.php';

/**
 * ベースURLを取得
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * アセットURLを取得
 */
function asset($path) {
    return '/assets/' . ltrim($path, '/');
}

/**
 * リダイレクト
 */
function redirect($path) {
    header('Location: ' . $path);
    exit;
}
