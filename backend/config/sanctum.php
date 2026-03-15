<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | SPA認証でCookieベースの認証を許可するドメイン。
    | Next.jsフロントエンド (localhost:3000) と本番ドメインを設定。
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', implode(',', [
        'localhost',
        'localhost:3000',
        'localhost:8000',
        '127.0.0.1',
        '127.0.0.1:3000',
        '127.0.0.1:8000',
        env('APP_URL') ? parse_url(env('APP_URL'), PHP_URL_HOST) : '',
        env('FRONTEND_URL') ? parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : '',
    ]))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | トークンの有効期限（分単位）。
    | nullの場合はトークンが無期限。
    | SPA認証の場合はセッション有効期限に従う。
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
