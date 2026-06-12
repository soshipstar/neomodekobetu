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
        // ベクトル埋め込み用モデル。vector_embeddings.embedding は vector(1536) のため
        // 1536 次元を返す text-embedding-3-small を既定とする。
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),

        // --- データ取扱いの確定状態を記録するコンプライアンス設定 ---
        // AIセーフティ ガイドライン 観点5(プライバシー保護)/観点10(検証可能性)対応。
        // OpenAI API は既定で送信データを学習に使用しないが、要配慮個人情報を扱うため
        // ZDR(ゼロデータ保持)契約・DPA(データ処理契約)の締結状態を運用で確定し、ここに記録する。
        // 詳細・確認手順: docs/ai-data-handling.md を参照。
        // (※これらは「確定状態の記録」であり、AI実行の挙動は変えない)
        'data_processing' => [
            // API 経由のデータがモデル学習に使われない設定であること (既定 true)
            'training_opt_out'    => env('OPENAI_TRAINING_OPT_OUT', true),
            // ZDR(ゼロデータ保持)が適用されていること (要 OpenAI への申請・確認)
            'zero_data_retention' => env('OPENAI_ZERO_DATA_RETENTION', false),
            // DPA(データ処理契約)を締結済みであること
            'dpa_signed'          => env('OPENAI_DPA_SIGNED', false),
            // 直近に確認した日付 (YYYY-MM-DD)
            'reviewed_at'         => env('OPENAI_DATA_POLICY_REVIEWED_AT', null),
        ],
    ],

    'external_api' => [
        'key' => env('EXTERNAL_API_KEY'),
    ],

    // 外部システム kiduriacount（国保連請求システム）への SSO 用サーバ間共有シークレット。
    // kiduriacount 側の KIDURI2026_SSO_SECRET と一致させること。
    'kiduriacount' => [
        'sso_secret' => env('KIDURIACOUNT_SSO_SECRET'),
    ],

    // 外部システム mynameis（本人の主観自己評価, fesvol.xyz）からの主観プロフィール受信用
    // サーバ間共有シークレット。mynameis 側の push 設定と一致させること。
    'mynameis' => [
        'shared_secret' => env('MYNAMEIS_SHARED_SECRET'),
        // メンバーID(member_code)→教室(組織)名の照会先。教室名の一致確認に使う。
        'resolve_url' => env('MYNAMEIS_RESOLVE_URL'),
    ],

];
