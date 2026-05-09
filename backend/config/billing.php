<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 既定プラン (Stripe側に登録済み)
    |--------------------------------------------------------------------------
    |
    | CARE-BRIDGE / CARE-BRIDGE LITE は Stripe Dashboard で事前登録した Product / Price。
    | ここに ID を保持しておき、Subscription 作成時のテンプレとして利用する。
    | デプロイ時に .env の STRIPE_* で実 ID を流し込む。
    |
    */
    'default_product_id' => env('STRIPE_DEFAULT_PRODUCT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | 消費税率
    |--------------------------------------------------------------------------
    |
    | 「税別」で入力された金額を Stripe に送る際、unit_amount = 入力値 * (1 + tax_rate)
    | で換算する。日本の標準税率 10% を使用。
    | Stripe Tax を有効化する場合は、この計算を無効化して Stripe 側に税計算を委ねる。
    |
    */
    'tax_rate' => env('BILLING_TAX_RATE', 0.10),

    'plans' => [
        'standard' => [
            'product_id' => env('STRIPE_PRODUCT_STANDARD', ''),
            'price_id' => env('STRIPE_PRICE_STANDARD', ''),
            'label' => 'Standard',
            'amount' => 25000,
            'currency' => 'jpy',
            'interval' => 'month',
        ],
        'lite' => [
            'product_id' => env('STRIPE_PRODUCT_LITE', ''),
            'price_id' => env('STRIPE_PRICE_LITE', ''),
            'label' => 'Lite',
            'amount' => 5000,
            'currency' => 'jpy',
            'interval' => 'month',
        ],
    ],

];
