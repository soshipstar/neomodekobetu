<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterAdminAuditLog;
use App\Policies\BillingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * マスター管理者の操作履歴 (master_admin_audit_logs) 閲覧API。
 *
 * 監査ログは append-only で、編集・削除はできない。
 * フィルタ: ?action= / ?master_user_id= / ?company_id= / ?from=YYYY-MM-DD / ?to=YYYY-MM-DD
 */
class MasterAuditLogController extends Controller
{
    public function __construct(private readonly BillingPolicy $policy) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->policy->manageAsMaster($user)) {
            return response()->json(['success' => false, 'message' => 'マスター管理者権限が必要です。'], 403);
        }

        $validated = $request->validate([
            'action' => 'nullable|string|max:100',
            'master_user_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $query = MasterAdminAuditLog::query()
            ->with([
                'masterUser:id,full_name,username',
                'company:id,name,code',
            ])
            ->orderByDesc('id');

        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (!empty($validated['master_user_id'])) {
            $query->where('master_user_id', $validated['master_user_id']);
        }
        if (!empty($validated['company_id'])) {
            $query->where('company_id', $validated['company_id']);
        }
        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            // toは1日終わりまで含める
            $query->where('created_at', '<=', $validated['to'].' 23:59:59');
        }

        $logs = $query->limit((int) ($validated['limit'] ?? 200))->get();

        // 利用可能な action 一覧（フィルタUIの選択肢用）
        $actions = MasterAdminAuditLog::query()
            ->selectRaw('DISTINCT action')
            ->orderBy('action')
            ->pluck('action');

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'available_actions' => $actions,
            ],
        ]);
    }

    public function show(Request $request, MasterAdminAuditLog $log): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->policy->manageAsMaster($user)) {
            return response()->json(['success' => false, 'message' => 'マスター管理者権限が必要です。'], 403);
        }
        $log->load(['masterUser:id,full_name,username', 'company:id,name,code']);
        return response()->json(['success' => true, 'data' => $log]);
    }
}
