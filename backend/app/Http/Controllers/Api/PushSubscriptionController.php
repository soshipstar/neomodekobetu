<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * GET /api/push/vapid-key
     * Return the VAPID public key for the frontend to use when subscribing.
     */
    public function vapidPublicKey(): JsonResponse
    {
        $publicKey = config('services.webpush.public_key');

        if (empty($publicKey)) {
            return response()->json([
                'error' => 'VAPID public key is not configured',
            ], 500);
        }

        return response()->json([
            'publicKey' => $publicKey,
        ]);
    }

    /**
     * POST /api/push/subscribe
     * Save a push subscription for the authenticated user.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = $request->user();

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $validated['keys']['p256dh'],
                'auth' => $validated['keys']['auth'],
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/push/unsubscribe
     * Remove a push subscription by endpoint.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();

        $deleted = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json([
            'success' => $deleted > 0,
        ]);
    }

    /**
     * POST /api/push/test
     * 認証中ユーザの全ての Push 購読端末にテスト通知を送る。
     *
     * 「通知が届かない」障害の切り分けと、保護者・スタッフ自身が
     * 「ちゃんと届くか」をその場で確認するために用意する。
     *
     * 返却:
     *   success: true/false
     *   sent: 実際に push gateway が受理した端末数
     *   total: ユーザーが登録している購読総数
     *   message: 結果メッセージ
     */
    public function test(Request $request, WebPushService $webPush): JsonResponse
    {
        $user = $request->user();

        $total = PushSubscription::where('user_id', $user->id)->count();

        if ($total === 0) {
            return response()->json([
                'success' => false,
                'sent' => 0,
                'total' => 0,
                'message' => 'この端末では通知が有効になっていません。先に「通知を有効にする」を押してください。',
            ]);
        }

        $sent = $webPush->sendToUser(
            $user->id,
            'KIDURI 通知テスト',
            '通知が正しく届いています。実際の連絡帳・チャットでもこのように届きます。',
            '/'
        );

        return response()->json([
            'success' => $sent > 0,
            'sent' => $sent,
            'total' => $total,
            'message' => $sent > 0
                ? "{$sent} / {$total} 端末に送信しました。通知センターを確認してください。"
                : '送信に失敗しました。少し待ってから再度お試しください。',
        ]);
    }
}
