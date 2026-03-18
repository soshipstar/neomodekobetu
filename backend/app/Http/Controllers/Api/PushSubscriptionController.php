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
}
