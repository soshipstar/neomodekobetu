<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * 通知カテゴリのキー一覧。フロントに公開するマスタ。
     */
    public const CATEGORIES = [
        'chat' => 'チャット',
        'announcement' => 'お知らせ',
        'meeting' => '面談予約',
        'kakehashi' => 'かけはし依頼',
        'monitoring' => 'モニタリング',
        'support_plan' => '個別支援計画',
        'submission' => '提出物依頼',
        'absence' => '欠席連絡',
    ];

    /**
     * 現在の通知設定を取得する。未設定キーは有効扱い (true)。
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->notification_preferences ?? [];

        $merged = [];
        foreach (self::CATEGORIES as $key => $label) {
            $merged[$key] = [
                'label' => $label,
                'enabled' => array_key_exists($key, $prefs) ? (bool) $prefs[$key] : true,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $merged,
        ]);
    }

    /**
     * 通知設定を更新する。
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [];
        foreach (array_keys(self::CATEGORIES) as $key) {
            $rules["preferences.{$key}"] = 'sometimes|boolean';
        }
        $validated = $request->validate($rules);

        $incoming = $validated['preferences'] ?? [];
        $prefs = $user->notification_preferences ?? [];
        foreach ($incoming as $key => $value) {
            $prefs[$key] = (bool) $value;
        }
        $user->notification_preferences = $prefs;
        $user->save();

        $merged = [];
        foreach (self::CATEGORIES as $key => $label) {
            $merged[$key] = [
                'label' => $label,
                'enabled' => array_key_exists($key, $prefs) ? (bool) $prefs[$key] : true,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $merged,
            'message' => '通知設定を更新しました。',
        ]);
    }
}
