<?php
/**
 * ChatGPT API連携処理
 */

// 環境変数を読み込む
require_once __DIR__ . '/env.php';

// ChatGPT APIキー（環境変数から取得）
// CHATGPT_API_KEYまたはOPENAI_API_KEYから取得
$apiKey = env('CHATGPT_API_KEY', '');
if (empty($apiKey)) {
    $apiKey = env('OPENAI_API_KEY', '');
}
define('CHATGPT_API_KEY', $apiKey);
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
 * 活動記録を統合した文章を生成
 *
 * @param string $activityName 活動名
 * @param string $commonActivity 共通活動内容
 * @param string $dailyNote 本日の様子
 * @param array $domains 気になったこと（領域と内容）
 * @param array|null $supportPlan 支援案情報（purpose, content, domains, other）
 * @return string|false 統合された文章 or false
 */
function generateIntegratedNote($activityName, $commonActivity, $dailyNote, $domains, $supportPlan = null) {
    $prompt = "あなたは個別支援教育の専門家です。以下の情報を元に、保護者に送る連絡帳として自然で読みやすい1つの文章にまとめてください。\n\n";

    // 支援案情報がある場合は最初に記載
    if (!empty($supportPlan)) {
        $prompt .= "【支援案（事前計画）】\n";
        if (!empty($supportPlan['purpose'])) {
            $prompt .= "・活動の目的: {$supportPlan['purpose']}\n";
        }
        if (!empty($supportPlan['content'])) {
            $prompt .= "・活動の計画内容: {$supportPlan['content']}\n";
        }
        if (!empty($supportPlan['domains'])) {
            $prompt .= "・五領域への配慮: {$supportPlan['domains']}\n";
        }
        if (!empty($supportPlan['other'])) {
            $prompt .= "・その他: {$supportPlan['other']}\n";
        }
        $prompt .= "\n";
    }

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
    if (!empty($supportPlan)) {
        $prompt .= "支援案の目的や配慮事項を踏まえつつ、実際の活動の様子を中心に記述してください。";
    }
    $prompt .= "箇条書きではなく、文章として流れるように記述してください。\n\n";
    $prompt .= "【重要な指示】\n";
    $prompt .= "・ポジティブで前向きな表現を使用してください。\n";
    $prompt .= "・「しかし」「ですが」「気になった点」などのネガティブな接続詞や表現は避けてください。\n";
    $prompt .= "・課題や改善点は「次のステップとして」「さらに成長するために」「これから挑戦できること」など、成長の機会として前向きに表現してください。\n";
    $prompt .= "・子どもの頑張りや成長、良かった点を中心に記述してください。\n";
    $prompt .= "・保護者が読んで嬉しくなるような、温かく励みになる文章にしてください。";

    $data = [
        'model' => 'gpt-5.2',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧で、前向きでポジティブな連絡帳を書きます。子どもの良い面や成長を見つけ、課題も成長の機会として前向きに伝えます。「しかし」「ですが」などのネガティブな接続詞は使わず、常にポジティブな表現を心がけます。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_completion_tokens' => 1000
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
        return powerTrim($result['choices'][0]['message']['content']);
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
