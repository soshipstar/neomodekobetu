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
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@kiduri.xyz'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        // 既定で GPT-5.4 通常版を使用 (旧: gpt-5.4-mini)。
        // 必要に応じて .env の OPENAI_MODEL で上書き可能。
        'model'   => env('OPENAI_MODEL', 'gpt-5.4-2026-03-05'),
    ],

    'external_api' => [
        'key' => env('EXTERNAL_API_KEY'),
    ],

    // 外部システム kiduriacount（国保連請求システム）への SSO 用サーバ間共有シークレット。
    // kiduriacount 側の KIDURI2026_SSO_SECRET と一致させること。
    'kiduriacount' => [
        'sso_secret' => env('KIDURIACOUNT_SSO_SECRET'),
    ],

];
