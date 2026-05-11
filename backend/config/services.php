<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),

        // 全般のフォールバック (用途別に未設定の場合に使用)
        'model'   => env('OPENAI_MODEL', 'gpt-5.4-mini'),

        // 用途別モデル (旧 soship での使い分けを踏襲、gpt-5 世代に更新)
        // 高品質 (構造化された長文生成):
        'model_plan'        => env('OPENAI_MODEL_PLAN',        'gpt-5.4'),         // 個別支援計画
        'model_monitoring'  => env('OPENAI_MODEL_MONITORING',  'gpt-5.4'),         // モニタリング
        'model_assessment'  => env('OPENAI_MODEL_ASSESSMENT',  'gpt-5.4'),         // アセスメント
        // 中品質 (テキスト生成、コスト重視):
        'model_newsletter'  => env('OPENAI_MODEL_NEWSLETTER',  'gpt-5.4-mini'),    // ニュースレター
        'model_renrakucho'  => env('OPENAI_MODEL_RENRAKUCHO',  'gpt-5.4-mini'),    // 連絡帳 補助
        'model_meeting'     => env('OPENAI_MODEL_MEETING',     'gpt-5.4-mini'),    // 面談記録/希望抽出
        'model_summary'     => env('OPENAI_MODEL_SUMMARY',     'gpt-5.4-mini'),    // 事業所評価サマリ等
        // 構造化抽出 (CSV/JSON 解析):
        'model_extract'     => env('OPENAI_MODEL_EXTRACT',     'gpt-5.4'),         // 一括登録 AI パース
        // 対話 (低レイテンシ):
        'model_chatbot'     => env('OPENAI_MODEL_CHATBOT',     'gpt-5-mini'),      // チャット応答
    ],

    'external_api' => [
        'key' => env('EXTERNAL_API_KEY'),
    ],

];
