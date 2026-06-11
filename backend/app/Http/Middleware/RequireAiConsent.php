<?php

namespace App\Http\Middleware;

use App\Models\UserConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI 生成系エンドポイントの前段で、認証ユーザーが AI 利用方針を含む
 * 必要な同意を取得済みかチェックする。
 *
 * 未取得の場合は 403 を返し、フロントエンドはレスポンスを見て
 * 同意取得モーダル (ConsentRequiredGate) へ誘導する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R3b (2026-05-17)
 *
 * 適用対象: AI 生成を伴うルート (generate-ai / generate-integrated /
 *           generate-revision-notes / generate-basis / generate-ai-五領域 等)
 */
class RequireAiConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // AUTH-06 修正: 未認証ユーザーは防御的に 401 を返す。
        // 通常は先行する auth:sanctum が弾くが、ミドルウェア順序の設定ミスや
        // トークン期限切れのエッジケースで未認証リクエストがここに到達した場合に
        // 同意チェックをスキップして通過させないため、明示的に拒否する。
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => '認証が必要です。',
            ], 401);
        }

        // staff / admin は AI 利用方針への同意が必要 (規約 + プライバシー含む)
        // それ以外のロール (tablet / agent 等) はそもそも本ルートに到達しない想定だが、
        // 念のため同様にチェック。
        $required = UserConsent::REQUIRED_FOR_STAFF_AI;
        $missing = [];
        foreach ($required as $type) {
            if (! UserConsent::hasActiveConsent($user->id, $type)) {
                $missing[] = $type;
            }
        }

        if (! empty($missing)) {
            return response()->json([
                'success'        => false,
                'error_code'     => 'ai_consent_required',
                'message'        => 'AI 機能のご利用には、規約・プライバシーポリシー・AI 利用方針への同意が必要です。',
                'missing_consents' => $missing,
            ], 403);
        }

        return $next($request);
    }
}
