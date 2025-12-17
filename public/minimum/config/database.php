<?php
/**
 * データベース接続設定
 * ミニマム版用
 */

// 環境変数を読み込む
require_once __DIR__ . '/../includes/env.php';

// データベース接続情報（環境変数から取得）
define('DB_HOST', env('DB_HOST', 'mysql320.phy.heteml.lan'));
define('DB_NAME', env('DB_NAME', '_kobetudb'));
define('DB_USER', env('DB_USER', '_kobetudb'));
define('DB_PASS', env('DB_PASS', 'kobetu1234'));
define('DB_CHARSET', 'utf8mb4');

/**
 * データベース接続を取得
 * @return PDO|null
 */
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("データベース接続エラーが発生しました。管理者にお問い合わせください。");
        }
    }

    return $pdo;
}

/**
 * データベース接続をテスト
 * @return bool
 */
function testDbConnection() {
    try {
        $pdo = getDbConnection();
        return $pdo !== null;
    } catch (Exception $e) {
        return false;
    }
}
