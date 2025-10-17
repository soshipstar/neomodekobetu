<?php
/**
 * ChatGPT API連携処理
 */

// 環境変数を読み込む
require_once __DIR__ . '/env.php';

// ChatGPT APIキー（環境変数から取得）
define('CHATGPT_API_KEY', env('CHATGPT_API_KEY', ''));
define('CHATGPT_API_URL', 'https://api.openai.com/v1/chat/completions');

/**
 * 活動記録を統合した文章を生成
 *
 * @param string $activityName 活動名
 * @param string $commonActivity 共通活動内容
 * @param string $dailyNote 本日の様子
 * @param array $domains 気になったこと（領域と内容）
 * @return string|false 統合された文章 or false
 */
function generateIntegratedNote($activityName, $commonActivity, $dailyNote, $domains) {
    $prompt = "あなたは個別支援教育の専門家です。以下の情報を元に、保護者に送る連絡帳として自然で読みやすい1つの文章にまとめてください。\n\n";
    $prompt .= "【活動名】\n{$activityName}\n\n";
    $prompt .= "【本日の活動内容】\n{$commonActivity}\n\n";

    if (!empty($dailyNote)) {
        $prompt .= "【本日の様子】\n{$dailyNote}\n\n";
    }

    if (!empty($domains)) {
        $prompt .= "【気になったこと】\n";
        foreach ($domains as $index => $domain) {
            $domainLabel = getDomainLabel($domain['category']);
            $prompt .= ($index + 1) . ". {$domainLabel}: {$domain['content']}\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "上記の情報を、保護者が読みやすいように、敬体（です・ます調）で1つの自然な文章にまとめてください。";
    $prompt .= "箇条書きではなく、文章として流れるように記述してください。";

    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧な連絡帳を書きます。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];

    $ch = curl_init(CHATGPT_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . CHATGPT_API_KEY
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("ChatGPT API cURL Error: " . $error);
        return false;
    }

    if ($httpCode !== 200) {
        error_log("ChatGPT API HTTP Error: " . $httpCode . " Response: " . $response);
        return false;
    }

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return trim($result['choices'][0]['message']['content']);
    }

    error_log("ChatGPT API Invalid Response: " . $response);
    return false;
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
