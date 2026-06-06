<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

/**
 * 規約・プライバシーポリシー・AI 利用方針への同意の取得・撤回・状態確認。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 (R3a) 対応。
 * (2026-05-17 — Phase A 着手)
 */
class ConsentController extends Controller
{
    /**
     * 現在のユーザーの同意状態を返す。
     *
     * 返却:
     * {
     *   user_type: 'staff',
     *   required_types: ['privacy_policy', 'terms', 'ai_usage'],
     *   current_versions: { privacy_policy: 'v1.0', ... },
     *   granted: { privacy_policy: { granted: true, version: 'v1.0', granted_at: '...' }, ... },
     *   needs_consent: ['ai_usage']  // まだ現行 version への同意が無いもの
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $user->user_type;

        $required = $this->requiredTypesForUserType($userType);
        $current = UserConsent::CURRENT_VERSIONS;

        $granted = [];
        $needsConsent = [];

        foreach ($required as $type) {
            $version = $current[$type] ?? null;
            $row = UserConsent::where('user_id', $user->id)
                ->where('consent_type', $type)
                ->where('version', $version)
                ->where('granted', true)
                ->whereNull('revoked_at')
                ->orderByDesc('granted_at')
                ->first();

            if ($row) {
                $granted[$type] = [
                    'granted'    => true,
                    'version'    => $row->version,
                    'granted_at' => $row->granted_at,
                ];
            } else {
                $needsConsent[] = $type;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user_type'        => $userType,
                'required_types'   => $required,
                'current_versions' => array_intersect_key($current, array_flip($required)),
                'granted'          => $granted,
                'needs_consent'    => $needsConsent,
            ],
        ]);
    }

    /**
     * 同意を 1 件付与する。
     *
     * リクエスト: { consent_type, version, student_id? }
     * version は現行バージョンと一致しなければならない (旧版への同意は無効)。
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consent_type' => ['required', 'string', Rule::in(array_keys(UserConsent::CURRENT_VERSIONS))],
            'version'      => ['required', 'string', 'max:20'],
            'student_id'   => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $expected = UserConsent::CURRENT_VERSIONS[$validated['consent_type']] ?? null;
        if ($expected === null || $expected !== $validated['version']) {
            return response()->json([
                'success' => false,
                'message' => '同意対象の規約バージョンが現行と一致しません。最新の規約をご確認のうえ、再度同意してください。',
            ], 422);
        }

        $user = $request->user();

        // child_ai_consent は guardian のみ + student_id 必須
        if ($validated['consent_type'] === UserConsent::TYPE_CHILD_AI) {
            if ($user->user_type !== 'guardian') {
                return response()->json([
                    'success' => false,
                    'message' => 'この同意は保護者ロールのみが行えます。',
                ], 403);
            }
            if (empty($validated['student_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'お子様の指定が必要です。',
                ], 422);
            }
        }

        $consent = UserConsent::create([
            'user_id'      => $user->id,
            'consent_type' => $validated['consent_type'],
            'version'      => $validated['version'],
            'student_id'   => $validated['student_id'] ?? null,
            'granted'      => true,
            'granted_at'   => now(),
            'ip_address'   => $request->ip(),
            'user_agent'   => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $consent,
            'message' => '同意を記録しました。',
        ], 201);
    }

    /**
     * 同意を撤回する。
     *
     * 過去の AI 生成済みデータは保持される (利用規約・AI 利用方針に明記)。
     * 新規 AI 生成リクエストはこのレコードを参照する Middleware で遮断する。
     */
    public function revoke(Request $request, string $type): JsonResponse
    {
        if (! array_key_exists($type, UserConsent::CURRENT_VERSIONS)) {
            return response()->json([
                'success' => false,
                'message' => '不明な同意種別です。',
            ], 404);
        }

        $studentId = $request->integer('student_id') ?: null;
        $user = $request->user();

        $q = UserConsent::where('user_id', $user->id)
            ->where('consent_type', $type)
            ->where('granted', true)
            ->whereNull('revoked_at');

        if ($studentId !== null) {
            $q->where('student_id', $studentId);
        }

        $count = $q->update([
            'granted'    => false,
            'revoked_at' => now(),
        ]);

        return response()->json([
            'success'        => true,
            'revoked_count'  => $count,
            'message'        => "{$count} 件の同意を撤回しました。",
        ]);
    }

    /**
     * 規約本文を返す (Markdown 形式)。
     * GET /api/legal/{type}/{version?}
     */
    public function legal(Request $request, string $type, ?string $version = null): JsonResponse
    {
        $allowed = ['privacy_policy', 'terms', 'ai_usage'];
        if (! in_array($type, $allowed, true)) {
            return response()->json(['success' => false, 'message' => '不明な文書です。'], 404);
        }

        $version = $version ?: ($type === 'privacy_policy'
            ? UserConsent::CURRENT_VERSIONS[UserConsent::TYPE_PRIVACY_POLICY]
            : ($type === 'terms'
                ? UserConsent::CURRENT_VERSIONS[UserConsent::TYPE_TERMS]
                : UserConsent::CURRENT_VERSIONS[UserConsent::TYPE_AI_USAGE]));

        // resources/legal/{type}_{version}.md を読む
        // 例: privacy_policy_v1.0.md  と  privacy_policy_v1.md  の両形式を試す
        $candidates = [
            resource_path("legal/{$type}_{$version}.md"),
            resource_path("legal/{$type}_" . str_replace('.0', '', $version) . ".md"),
        ];

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'type'    => $type,
                        'version' => $version,
                        'content' => File::get($path),
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => "規約本文が見つかりません ({$type} {$version})。",
        ], 404);
    }

    /**
     * user_type ごとの必須同意セットを返す。
     */
    private function requiredTypesForUserType(string $userType): array
    {
        return match ($userType) {
            'staff', 'admin'       => UserConsent::REQUIRED_FOR_STAFF_AI,
            'guardian', 'student'  => UserConsent::BASE_REQUIRED_FOR_ALL,
            'tablet', 'agent'      => UserConsent::BASE_REQUIRED_FOR_ALL,
            default                => UserConsent::BASE_REQUIRED_FOR_ALL,
        };
    }
}
