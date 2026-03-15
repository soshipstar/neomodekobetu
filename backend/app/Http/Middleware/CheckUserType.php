<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ユーザータイプ検証ミドルウェア
 *
 * ルート定義で許可するユーザータイプをカンマ区切りで指定:
 *   ->middleware('user_type:staff,admin')
 *
 * マスター管理者 (is_master=true) は全てのルートにアクセス可能。
 */
class CheckUserType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$types  許可するユーザータイプ (カンマ区切りで1引数 or 複数引数)
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        // カンマ区切りの引数を展開 (例: 'staff,admin' → ['staff', 'admin'])
        $allowedTypes = [];
        foreach ($types as $type) {
            $allowedTypes = array_merge($allowedTypes, explode(',', $type));
        }
        $allowedTypes = array_map('trim', $allowedTypes);

        // マスター管理者は全ルートにアクセス可
        if ($user->user_type === 'admin' && $user->is_master) {
            return $next($request);
        }

        // ユーザータイプが許可リストに含まれているか
        if (!in_array($user->user_type, $allowedTypes, true)) {
            return response()->json([
                'message' => 'このリソースへのアクセス権限がありません。',
                'required_type' => $allowedTypes,
                'current_type' => $user->user_type,
            ], 403);
        }

        return $next($request);
    }
}
