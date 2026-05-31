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

        // Cashier の Billable model を Company に変更（顧客 = 企業単位の課金）
        \Laravel\Cashier\Cashier::useCustomerModel(\App\Models\Company::class);

        // ==================================================================
        // RateLimiter 定義 (悪意あるスクレイピング / 大量データ吸い上げ対策)
        // ==================================================================
        // 'api': 認証ユーザーは user_id 単位で 200/分、未認証は IP 単位で 60/分。
        //   現場のダッシュボード自動更新やリアルタイム描画でも余裕がある水準。
        // 'export': PDF/CSV ダウンロードや一括出力など重い処理。
        //   user_id 単位で 30/時間に絞り、業務 1 日分のエクスポートは十分カバーしつつ
        //   機械的に全件抜き取りされるのを抑止する。
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            if ($request->user()) {
                return \Illuminate\Cache\RateLimiting\Limit::perMinute(200)->by('user:' . $request->user()->id);
            }
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('ip:' . $request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('export', function (\Illuminate\Http\Request $request) {
            if ($request->user()) {
                return \Illuminate\Cache\RateLimiting\Limit::perHour(30)->by('user:' . $request->user()->id);
            }
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('ip:' . $request->ip());
        });
        // 'login': 総当たり攻撃対策。IP + 入力ユーザー名の組で 1 分 10 回まで。
        //   正規ユーザーの打ち間違いには十分な猶予を残しつつ、機械的な
        //   パスワード総当たりを抑止する。username 込みにすることで、攻撃者が
        //   1 アカウントを狙い撃ちする場合も、IP 全体を巻き込む誤ロックを避ける。
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $username = (string) $request->input('username', '');
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)
                ->by('login:' . $request->ip() . '|' . mb_strtolower($username));
        });
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Bearer トークン認証を使用するため、statefulApi() は不要
        // (statefulApi はCSRFトークン検証を強制するため削除)

        // カスタムミドルウェアエイリアス
        $middleware->alias([
            'user_type' => \App\Http\Middleware\CheckUserType::class,
            'classroom_access' => \App\Http\Middleware\CheckClassroomAccess::class,
            'external_api_key' => \App\Http\Middleware\VerifyExternalApiKey::class,
        ]);

        // 全 /api/* に per_page クランプを適用 (上限 100)。
        // 悪意あるクライアントが per_page=10000 で一括吸い上げするのを防ぐ。
        $middleware->api(prepend: [
            \App\Http\Middleware\ClampPerPage::class,
        ]);

        // 全 /api/* リクエストを api_access_logs テーブルに記録 (terminate で
        // 非同期書き込みのためユーザー待ち時間に影響なし)。流出/不正解析の後追い用。
        $middleware->api(append: [
            \App\Http\Middleware\LogApiAccess::class,
        ]);

        // API レート制限。booted() で定義した 'api' リミッタ (user 単位 200/分、
        // 未認証 IP 単位 60/分) を全 /api/* に適用。
        // 重いエクスポート系には別途 'throttle:export' (30/時間/user) を個別付与する。
        $middleware->throttleApi();

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

                // tinker (php artisan tinker) からの例外は DB に残さない — 手動実行のノイズ源
                if (str_starts_with(get_class($e), 'Psy\\')) return;
                if (app()->runningInConsole() && in_array('tinker', $_SERVER['argv'] ?? [], true)) return;

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
