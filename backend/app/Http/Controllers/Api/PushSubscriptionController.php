<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
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
     * 自分の登録済 subscription にテスト通知を送る。
     *
     * 目的:
     *  - プロフィール画面の「テスト通知を送る」ボタンから利用
     *  - VAPID 鍵未設定や subscription なしも明示的にレスポンスで返し、診断容易にする
     *  - 配信成功数とサーバ側ログ (warning) で push の HTTP 失敗を可視化する
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '認証が必要です。'], 401);
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->count();
        if ($subscriptions === 0) {
            return response()->json([
                'success' => false,
                'message' => 'この端末で通知を有効にしてください (購読がまだありません)。',
                'subscriptions' => 0,
            ], 422);
        }

        if (empty(config('services.webpush.public_key')) || empty(config('services.webpush.private_key'))) {
            return response()->json([
                'success' => false,
                'message' => 'サーバ側で VAPID 鍵が未設定です。管理者に連絡してください。',
                'subscriptions' => $subscriptions,
            ], 500);
        }

        $service = app(\App\Services\WebPushService::class);
        $sent = $service->sendToUser(
            $user->id,
            'テスト通知',
            sprintf('%s さん、通知は正常に届いています。(%s)', $user->full_name, now()->format('Y/m/d H:i:s')),
            '/staff/profile',
        );

        return response()->json([
            'success'       => $sent > 0,
            'message'       => $sent > 0
                ? "{$sent} 件の端末にテスト通知を送りました。"
                : "送信処理は実行しましたが、配信に失敗しました (期限切れ subscription の可能性)。",
            'sent'          => $sent,
            'subscriptions' => $subscriptions,
        ], $sent > 0 ? 200 : 502);
    }
}
