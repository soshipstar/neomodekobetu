<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 教室スコープ検証ミドルウェア
 *
 * すべてのリソースが classroom_id で分離されていることを保証する。
 * マスター管理者 (is_master=true) のみ教室横断アクセスが可能。
 *
 * 使用方法:
 *   Route::middleware('classroom_access') — ルートパラメータまたはリクエストから classroom_id を自動検出
 *   Route::middleware('classroom_access:classroom') — 特定のルートパラメータ名を指定
 */
class CheckClassroomAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $parameterName  ルートパラメータ名 (デフォルト: 自動検出)
     */
    public function handle(Request $request, Closure $next, ?string $parameterName = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        // マスター管理者は教室横断アクセス可
        if ($user->user_type === 'admin' && $user->is_master) {
            return $next($request);
        }

        // 教室IDの取得を試行
        $classroomId = $this->resolveClassroomId($request, $parameterName);

        // 教室IDが解決できない場合はスキップ（コントローラーで制御）
        if ($classroomId === null) {
            return $next($request);
        }

        // ユーザーの所属教室とリソースの教室が一致するか確認
        if ((int) $user->classroom_id !== (int) $classroomId) {
            return response()->json([
                'message' => 'この教室のリソースにアクセスする権限がありません。',
            ], 403);
        }

        return $next($request);
    }

    /**
     * リクエストから classroom_id を解決する
     */
    protected function resolveClassroomId(Request $request, ?string $parameterName): ?int
    {
        // 1. 明示的なパラメータ名が指定されている場合
        if ($parameterName !== null) {
            $model = $request->route($parameterName);
            if ($model && is_object($model) && isset($model->classroom_id)) {
                return (int) $model->classroom_id;
            }
            if ($model && is_numeric($model)) {
                return (int) $model;
            }
        }

        // 2. ルートパラメータから classroom_id を探す
        $routeParams = $request->route()?->parameters() ?? [];
        foreach ($routeParams as $key => $value) {
            // Eloquent モデルバインディングの場合
            if (is_object($value) && isset($value->classroom_id)) {
                return (int) $value->classroom_id;
            }
        }

        // 3. classroom パラメータが直接存在する場合
        if (isset($routeParams['classroom'])) {
            $classroom = $routeParams['classroom'];
            return is_object($classroom) ? (int) $classroom->id : (int) $classroom;
        }

        // 4. リクエストボディ/クエリパラメータから
        if ($request->has('classroom_id')) {
            return (int) $request->input('classroom_id');
        }

        return null;
    }
}
