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
        then: function () {
            // Broadcasting auth route with Sanctum token authentication
            Illuminate\Support\Facades\Route::prefix('api')
                ->middleware(['api', 'auth:sanctum'])
                ->group(function () {
                    Illuminate\Support\Facades\Route::post('/broadcasting/auth', function (Illuminate\Http\Request $request) {
                        return Illuminate\Support\Facades\Broadcast::auth($request);
                    });
                });
        },
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
        // エラーをDBに記録
        $exceptions->report(function (\Throwable $e) {
            try {
                if ($e instanceof \Illuminate\Auth\AuthenticationException) return;
                if ($e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) return;
                if ($e instanceof \Illuminate\Validation\ValidationException) return;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) return;

                $request = request();
                \App\Models\ErrorLog::create([
                    'level'           => 'error',
                    'message'         => mb_substr($e->getMessage(), 0, 2000),
                    'exception_class' => get_class($e),
                    'file'            => $e->getFile(),
                    'line'            => $e->getLine(),
                    'trace'           => array_slice(array_map(fn ($t) => ($t['file'] ?? '') . ':' . ($t['line'] ?? ''), $e->getTrace()), 0, 10),
                    'url'             => $request?->fullUrl(),
                    'method'          => $request?->method(),
                    'user_id'         => $request?->user()?->id,
                    'ip_address'      => $request?->ip(),
                    'user_agent'      => mb_substr($request?->userAgent() ?? '', 0, 500),
                    'request_data'    => array_slice($request?->all() ?? [], 0, 20),
                ]);
            } catch (\Throwable $logError) {
                // DB記録失敗は無視（無限ループ防止）
            }
        });

        // JSON APIレスポンスのエラーフォーマット
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // auth:sanctum 未認証時に Route [login] not defined を防ぐ
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        // AuthenticationException を 401 JSON で返す
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })
    ->create();
