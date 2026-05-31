<?php

namespace App\Http\Middleware;

use App\Models\ApiAccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全 /api/* のリクエストを api_access_logs テーブルに記録する。
 *
 * 流出/コピー対策の後追いログ。レコードは毎日 03:30 の scheduler で
 * 90 日より古いものを削除する。
 *
 * 性能配慮:
 *  - ログ書き込みは「レスポンス送出後」(terminate) で行い、ユーザー待ち時間
 *    に影響させない。
 *  - 書き込み失敗は無視 (try-catch で握り潰す)。本番でログテーブルが
 *    壊れても、業務は止めない。
 *  - method+path が広告 SDK のような大量ノイズ系のときは記録対象外
 *    (現状除外パターンは無いが、後で fitter を入れられる構造)。
 */
class LogApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);
        // terminate() で記録できるよう、ハンドラ側で属性を保存
        $request->attributes->set('_api_log_started_at', $startedAt);
        $request->attributes->set('_api_log_status', $response->getStatusCode());
        return $response;
    }

    /**
     * Laravel が「レスポンス送出後」に terminate() を呼ぶ。
     * ここで DB に書き込めばユーザー待ち時間に影響しない。
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $startedAt = (float) $request->attributes->get('_api_log_started_at', microtime(true));
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $user = $request->user();
            ApiAccessLog::create([
                'user_id'       => $user?->id,
                'user_type'     => $user?->user_type,
                'method'        => substr($request->method(), 0, 10),
                'path'          => substr($request->path(), 0, 500),
                'status_code'   => $response->getStatusCode(),
                'duration_ms'   => $durationMs,
                'ip_address'    => substr($request->ip() ?? '', 0, 45),
                'user_agent'    => substr($request->userAgent() ?? '', 0, 500),
                'response_bytes'=> strlen((string) $response->getContent()),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // DB 書き込み失敗は業務を止めない (無限ループ防止のため Log すら任意)
            Log::warning('LogApiAccess failed: ' . $e->getMessage());
        }
    }
}
