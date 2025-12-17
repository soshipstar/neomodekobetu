<?php
/**
 * OpenAI API ヘルパー関数
 * ミニマム版用
 */

// OpenAI APIキーの設定
// .envファイルまたは環境変数から取得することを推奨
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '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');

/**
 * OpenAI APIを呼び出してテキストを生成
 *
 * @param string $prompt プロンプトテキスト
 * @param string $model モデル名（デフォルト: gpt-5.2）
 * @param int $maxTokens 最大トークン数（デフォルト: 1000）
 * @return string 生成されたテキスト
 * @throws Exception APIエラーが発生した場合
 */
function callOpenAI($prompt, $model = 'gpt-5.2', $maxTokens = 1000) {
    $apiKey = OPENAI_API_KEY;

    if (!$apiKey || $apiKey === 'YOUR_OPENAI_API_KEY_HERE') {
        throw new Exception('OpenAI APIキーが設定されていません。includes/openai_helper.php を確認してください。');
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_completion_tokens' => $maxTokens,
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('cURLエラー: ' . $curlError);
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
        throw new Exception('OpenAI APIエラー (HTTP ' . $httpCode . '): ' . $errorMessage);
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSONパースエラー: ' . json_last_error_msg());
    }

    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIレスポンスが不正です');
    }

    return $result['choices'][0]['message']['content'];
}

/**
 * OpenAI APIを使用して要約を生成
 *
 * @param string $text 要約対象のテキスト
 * @param int $maxLength 要約の最大文字数
 * @return string 要約されたテキスト
 */
function summarizeText($text, $maxLength = 200) {
    $prompt = "以下のテキストを{$maxLength}文字程度で要約してください：\n\n" . $text;
    return callOpenAI($prompt, 'gpt-5.2', 500);
}

/**
 * OpenAI APIを使用してテキストを分類
 *
 * @param string $text 分類対象のテキスト
 * @param array $categories カテゴリーのリスト
 * @return string 最も適切なカテゴリー
 */
function classifyText($text, $categories) {
    $categoriesStr = implode('、', $categories);
    $prompt = "以下のテキストを次のカテゴリーのいずれかに分類してください：" . $categoriesStr . "\n\nテキスト：" . $text . "\n\nカテゴリー名のみを返してください。";
    return trim(callOpenAI($prompt, 'gpt-5.2', 100));
}
