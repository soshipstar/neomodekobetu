<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Laravel 12.x の簡素化されたブートストラップ。
| プロバイダーレスブート方式で、ミドルウェアとルーティングをここで設定。
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->booted(function (Application $app) {
        // Model Observers の登録
        \App\Models\ChatMessage::observe(\App\Observers\ChatMessageObserver::class);
        \App\Models\IndividualSupportPlan::observe(\App\Observers\SupportPlanObserver::class);
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Bearer トークン認証を使用するため、statefulApi() は不要
        // (statefulApi はCSRFトークン検証を強制するため削除)

        // カスタムミドルウェアエイリアス
        $middleware->alias([
            'user_type' => \App\Http\Middleware\CheckUserType::class,
            'classroom_access' => \App\Http\Middleware\CheckClassroomAccess::class,
        ]);

        // API レート制限
        $middleware->throttleApi('60,1');

        // 信頼されたプロキシ (Docker/Nginx経由)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // JSON APIレスポンスのエラーフォーマット
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })
    ->create();
