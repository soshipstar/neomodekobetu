<?php
/**
 * Claude API設定
 */

/**
 * Claude APIキーを取得
 * 環境変数から取得、または直接設定
 */
function getClaudeApiKey() {
    // 環境変数から取得を試みる
    $apiKey = getenv('CLAUDE_API_KEY');

    if ($apiKey) {
        return $apiKey;
    }

    // 環境変数がない場合は、ここに直接APIキーを設定できます
    // セキュリティ上、本番環境では環境変数を使用することを推奨します
    // return 'sk-ant-api03-...'; // ここにAPIキーを設定

    return '';
}
