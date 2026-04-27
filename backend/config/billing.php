<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 既定プラン (Stripe側に登録済み)
    |--------------------------------------------------------------------------
    |
    | KIDURI / KIDURI LITE は Stripe Dashboard で事前登録した Product / Price。
    | ここに ID を保持しておき、Subscription 作成時のテンプレとして利用する。
    |
    */
    'default_product_id' => env('STRIPE_DEFAULT_PRODUCT_ID', 'prod_UOBq57TfPCbC63'),

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
        'kiduri' => [
            'product_id' => env('STRIPE_PRODUCT_KIDURI', 'prod_UOBq57TfPCbC63'),
            'price_id' => env('STRIPE_PRICE_KIDURI', 'price_1TPPT7RS8n8ZQsVRqavow5kX'),
            'label' => 'KIDURI',
            'amount' => 25000,
            'currency' => 'jpy',
            'interval' => 'month',
        ],
        'kiduri_lite' => [
            'product_id' => env('STRIPE_PRODUCT_KIDURI_LITE', 'prod_UOBoiRxOfNkCpn'),
            'price_id' => env('STRIPE_PRICE_KIDURI_LITE', 'price_1TPPQXRS8n8ZQsVRaWhDGawV'),
            'label' => 'KIDURI LITE',
            'amount' => 5000,
            'currency' => 'jpy',
            'interval' => 'month',
        ],
    ],

];
