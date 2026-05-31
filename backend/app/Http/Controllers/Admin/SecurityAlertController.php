<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 異常検知 (ApiAnomalyDetectionService) が検出したセキュリティアラートの
 * 閲覧・対処管理 (マスター管理者専用)。
 */
class SecurityAlertController extends Controller
{
    private function denyIfNotMaster(Request $request): ?JsonResponse
    {
        if (! $request->user()?->is_master) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        return null;
    }

    /**
     * アラート一覧 (フィルタ + ページネーション)。
     * フィルタ: rule / is_resolved / user_id
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;

        $q = SecurityAlert::query()
            ->with(['user:id,full_name,user_type', 'resolvedBy:id,full_name'])
            ->orderByDesc('created_at');

        if ($request->filled('rule'))        $q->where('rule', $request->rule);
        if ($request->filled('user_id'))     $q->where('user_id', $request->integer('user_id'));
        if ($request->has('is_resolved'))    $q->where('is_resolved', $request->boolean('is_resolved'));

        $alerts = $q->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $alerts->items(),
            'meta'    => [
                'current_page' => $alerts->currentPage(),
                'last_page'    => $alerts->lastPage(),
                'total'        => $alerts->total(),
            ],
        ]);
    }

    /**
     * 未対処アラート件数 (サイドバー / ダッシュボードのバッジ用)。
     */
    public function unresolvedCount(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;
        return response()->json([
            'success' => true,
            'data'    => ['unresolved' => SecurityAlert::where('is_resolved', false)->count()],
        ]);
    }

    /**
     * 対処済み / 未対処の切り替え。
     */
    public function resolve(Request $request, SecurityAlert $securityAlert): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;

        $validated = $request->validate([
            'is_resolved'   => 'required|boolean',
            'resolved_note' => 'nullable|string|max:2000',
        ]);

        $securityAlert->update([
            'is_resolved'   => $validated['is_resolved'],
            'resolved_note' => $validated['resolved_note'] ?? $securityAlert->resolved_note,
            'resolved_by'   => $validated['is_resolved'] ? $request->user()->id : null,
            'resolved_at'   => $validated['is_resolved'] ? now() : null,
        ]);

        return response()->json(['success' => true, 'data' => $securityAlert->fresh()]);
    }
}
