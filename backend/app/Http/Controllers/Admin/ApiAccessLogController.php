<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiAccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API アクセス監査ログ (api_access_logs) の閲覧 (マスター管理者専用)。
 * 不正解析/コピーの後追い調査用。
 *
 * 注: 既存の Admin\AuditLogController は別物 (audit_logs = 操作履歴) のため、
 *     名前衝突を避けてこちらは ApiAccessLogController とする。
 */
class ApiAccessLogController extends Controller
{
    private function denyIfNotMaster(Request $request): ?JsonResponse
    {
        if (! $request->user()?->is_master) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        return null;
    }

    /**
     * ログ一覧 (フィルタ + ページネーション)。
     * フィルタ: user_id / status_code / method / path(部分一致) / from / to / min_status
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;

        $validated = $request->validate([
            'user_id'     => 'nullable|integer',
            'status_code' => 'nullable|integer',
            'method'      => 'nullable|string|max:10',
            'path'        => 'nullable|string|max:200',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date',
            'min_status'  => 'nullable|integer',
            'per_page'    => 'nullable|integer',
        ]);

        $q = ApiAccessLog::query()->with('user:id,full_name,user_type')->orderByDesc('created_at');

        if (!empty($validated['user_id']))     $q->where('user_id', $validated['user_id']);
        if (!empty($validated['status_code'])) $q->where('status_code', $validated['status_code']);
        if (!empty($validated['min_status']))  $q->where('status_code', '>=', $validated['min_status']);
        if (!empty($validated['method']))      $q->where('method', strtoupper($validated['method']));
        if (!empty($validated['path']))        $q->where('path', 'like', '%' . $validated['path'] . '%');
        if (!empty($validated['from']))        $q->where('created_at', '>=', $validated['from']);
        if (!empty($validated['to']))          $q->where('created_at', '<=', $validated['to']);

        $logs = $q->paginate($request->integer('per_page', 50));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * 直近 24 時間のサマリ統計。
     */
    public function stats(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotMaster($request)) return $deny;

        $since = now()->subDay();

        $total = ApiAccessLog::where('created_at', '>=', $since)->count();

        $topUsers = ApiAccessLog::select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->with('user:id,full_name,user_type')
            ->get()
            ->map(fn ($r) => [
                'user_id'   => $r->user_id,
                'full_name' => $r->user?->full_name,
                'user_type' => $r->user?->user_type,
                'count'     => (int) $r->cnt,
            ]);

        $statusBreakdown = ApiAccessLog::select('status_code', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $since)
            ->groupBy('status_code')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['status_code' => (int) $r->status_code, 'count' => (int) $r->cnt]);

        $topExporters = ApiAccessLog::select('user_id', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->where(function ($q) {
                $q->where('path', 'like', '%pdf%')
                  ->orWhere('path', 'like', '%csv%')
                  ->orWhere('path', 'like', '%export%');
            })
            ->groupBy('user_id')
            ->orderByDesc('cnt')
            ->limit(5)
            ->with('user:id,full_name,user_type')
            ->get()
            ->map(fn ($r) => [
                'user_id'   => $r->user_id,
                'full_name' => $r->user?->full_name,
                'count'     => (int) $r->cnt,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'window_hours'     => 24,
                'total_requests'   => $total,
                'top_users'        => $topUsers,
                'status_breakdown' => $statusBreakdown,
                'top_exporters'    => $topExporters,
            ],
        ]);
    }
}
