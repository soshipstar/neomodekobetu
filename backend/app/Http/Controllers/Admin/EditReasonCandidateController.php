<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiEditReasonCandidate;
use App\Services\EditReasonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI学習基盤 §11: 修正理由の新カテゴリ候補の確認・昇格(管理者)。動的タクソノミー。
 *
 * 分類: api
 */
class EditReasonCandidateController extends Controller
{
    public function __construct(private EditReasonService $service) {}

    /** GET /api/admin/edit-reason-candidates : 自社+全社共通の保留候補(頻度順) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        $companyId = $user->company_id;

        $q = AiEditReasonCandidate::where('status', 'pending');
        if (! $user->isMasterAdmin()) {
            $q->where(fn ($w) => $w->whereNull('company_id')->orWhere('company_id', $companyId));
        }
        $cands = $q->orderByDesc('frequency')->orderByDesc('distinct_users')->limit(100)
            ->get(['id', 'company_id', 'normalized_text', 'frequency', 'distinct_users', 'member_texts', 'status']);

        return response()->json(['success' => true, 'data' => $cands]);
    }

    /** POST /api/admin/edit-reason-candidates/{candidate}/promote {code, label_ja} */
    public function promote(Request $request, AiEditReasonCandidate $candidate): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        // 法人境界: 自社/全社共通の候補のみ昇格可
        if (! $user->isMasterAdmin() && $candidate->company_id !== null && $candidate->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'この候補は操作できません。'], 403);
        }
        if ($candidate->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'この候補は処理済みです。'], 422);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64|regex:/^[a-z0-9_]+$/',
            'label_ja' => 'required|string|max:100',
        ]);

        $cat = $this->service->promote($candidate, $validated['code'], $validated['label_ja'], $user->id);

        return response()->json(['success' => true, 'data' => $cat, 'message' => '修正理由カテゴリに昇格しました。']);
    }

    /** POST /api/admin/edit-reason-candidates/{candidate}/reject */
    public function reject(Request $request, AiEditReasonCandidate $candidate): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        if (! $user->isMasterAdmin() && $candidate->company_id !== null && $candidate->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'この候補は操作できません。'], 403);
        }
        $candidate->update(['status' => 'rejected', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);

        return response()->json(['success' => true, 'message' => '却下しました。']);
    }
}
