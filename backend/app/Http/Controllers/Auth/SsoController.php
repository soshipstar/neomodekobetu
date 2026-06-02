<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 外部システム（kiduriacount = 国保連請求システム）への SSO を提供する。
 *
 * kiduri2026 を認証元（IdP）とするチケット方式（kiduriacount/DESIGN.md §4.2）:
 *   - ticket: ログイン済みユーザーが請求システムへ遷移する際に60秒有効の使い捨て
 *             チケットを発行する（auth:sanctum）。
 *   - verify: kiduriacount からサーバ間で呼ばれ、共有シークレットでチケットを検証・
 *             消費し、ユーザー情報を返す（Public + 共有シークレット）。
 *
 * 本コントローラは追加のみ。既存の認証フローには影響しない。
 */
class SsoController extends Controller
{
    private const TICKET_PREFIX = 'sso_ticket:';

    private const TICKET_TTL_SECONDS = 60;

    /** 請求システムを利用できるユーザー種別（職員・管理者のみ） */
    private const ALLOWED_USER_TYPES = ['admin', 'staff'];

    /**
     * 使い捨てチケットを発行する（auth:sanctum）。
     */
    public function ticket(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->user_type, self::ALLOWED_USER_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'このユーザーは請求システムを利用できません。',
            ], 403);
        }

        $ticket = Str::random(64);
        Cache::put(self::TICKET_PREFIX.$ticket, $user->id, self::TICKET_TTL_SECONDS);

        return response()->json([
            'success' => true,
            'data' => ['ticket' => $ticket],
        ]);
    }

    /**
     * チケットを検証・消費し、ユーザー情報を返す（サーバ間。共有シークレット必須）。
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'ticket' => 'required|string',
            'secret' => 'required|string',
        ]);

        $expected = (string) config('services.kiduriacount.sso_secret');
        if ($expected === '' || ! hash_equals($expected, (string) $request->input('secret'))) {
            return response()->json(['success' => false, 'message' => '認証に失敗しました。'], 401);
        }

        $cacheKey = self::TICKET_PREFIX.$request->input('ticket');
        $userId = Cache::pull($cacheKey); // ワンタイム（取得と同時に失効）

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'チケットが無効か期限切れです。'], 401);
        }

        $user = User::find($userId);
        if (! $user || ! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'ユーザーが無効です。'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'is_master' => (bool) $user->is_master,
                    'is_company_admin' => (bool) $user->is_company_admin,
                    'company_id' => $user->company_id,
                    'classroom_id' => $user->classroom_id,
                    'accessible_classroom_ids' => $user->switchableClassroomIds(),
                ],
            ],
        ]);
    }
}
