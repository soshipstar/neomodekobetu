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
