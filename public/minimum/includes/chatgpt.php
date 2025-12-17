<?php
/**
 * ChatGPT API連携処理
 * ミニマム版用
 */

// 環境変数を読み込む
require_once __DIR__ . '/env.php';

// ChatGPT APIキー（環境変数から取得）
define('CHATGPT_API_KEY', env('CHATGPT_API_KEY', ''));
define('CHATGPT_API_URL', 'https://api.openai.com/v1/chat/completions');

// 強力なtrim処理関数
if (!function_exists('powerTrim')) {
    function powerTrim($text) {
        if ($text === null || $text === '') {
            return '';
        }
        return preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $text);
    }
}

/**
 * 領域キーから日本語ラベルを取得
 *
 * @param string $key
 * @return string
 */
function getDomainLabel($key) {
    $labels = [
        'health_life' => '健康・生活',
        'motor_sensory' => '運動・感覚',
        'cognitive_behavior' => '認知・行動',
        'language_communication' => '言語・コミュニケーション',
        'social_relations' => '人間関係・社会性'
    ];

    return $labels[$key] ?? $key;
}
