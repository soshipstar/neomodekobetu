<?php
/**
 * 環境変数読み込み処理
 * .envファイルから環境変数を読み込む
 */

/**
 * .envファイルを読み込んで環境変数として設定
 *
 * @param string $path .envファイルのパス
 * @return void
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // コメント行をスキップ
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // KEY=VALUE の形式をパース
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 既に環境変数が設定されている場合はスキップ
            if (!array_key_exists($key, $_ENV) && !getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

/**
 * 環境変数を取得
 *
 * @param string $key 変数名
 * @param mixed $default デフォルト値
 * @return mixed
 */
function env($key, $default = null) {
    // $_ENVから取得
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }

    // getenv()から取得
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    // デフォルト値を返す
    return $default;
}

// .envファイルを自動読み込み
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
