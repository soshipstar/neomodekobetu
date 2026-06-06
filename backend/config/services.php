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
        // 全箇所で同じモデルを使用 (元の挙動と同じ)。
        // env OPENAI_MODEL で上書き可能。指定なければコード側のハードコード値が使われる。
        'model'   => env('OPENAI_MODEL', 'gpt-5.4-mini-2026-03-17'),

        // AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R6 (2026-05-17)
        // OpenAI Enterprise + Zero Data Retention 契約用の設定。
        //  - organization: 契約済 Org ID。設定するとリクエストヘッダ OpenAI-Organization に乗る。
        //  - project: 任意のプロジェクト ID。
        //  - zero_data_retention: true なら ZDR 契約済とみなし、AI 呼出を許可。
        //    false (デフォルト) の場合は Log::warning を出力する (本番設定で阻止に切替可能)。
        //  - dpa_url: 締結済の Data Processing Agreement の保管場所 (規約画面/監査資料向け)。
        'organization'        => env('OPENAI_ORGANIZATION'),
        'project'             => env('OPENAI_PROJECT'),
        'zero_data_retention' => filter_var(env('OPENAI_ZDR', false), FILTER_VALIDATE_BOOLEAN),
        'dpa_url'             => env('OPENAI_DPA_URL'),
    ],

    'external_api' => [
        'key' => env('EXTERNAL_API_KEY'),
    ],

];
