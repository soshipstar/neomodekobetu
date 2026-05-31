<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * クエリパラメータ per_page を上限 (既定 100) でクランプする。
 *
 * 目的:
 *   一覧 API で per_page=500 や per_page=10000 のような大量取得を
 *   悪意あるクライアントにされても、サーバ側で強制的に 100 まで切り下げ、
 *   1 リクエストで全件吸い上げできないようにする。
 *
 * 副作用:
 *   request->input('per_page') を上書きする。実装側 (Controller) では
 *   通常通り $request->input('per_page', 50) で受け取るだけで OK。
 */
class ClampPerPage
{
    public const MAX_PER_PAGE = 100;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('per_page')) {
            $value = (int) $request->input('per_page');
            if ($value > self::MAX_PER_PAGE) {
                $request->merge(['per_page' => self::MAX_PER_PAGE]);
                $request->query->set('per_page', self::MAX_PER_PAGE);
            }
            if ($value < 1) {
                $request->merge(['per_page' => 1]);
                $request->query->set('per_page', 1);
            }
        }
        return $next($request);
    }
}
