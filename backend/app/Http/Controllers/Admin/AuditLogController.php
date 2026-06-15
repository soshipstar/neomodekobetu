<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * 監査ログ一覧をフィルタ付きで取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AuditLog::with('user:id,full_name,user_type');

        // テナント分離(rank6): マスター以外は自施設の監査ログのみ閲覧できる。
        // 施設が特定できない非マスターには何も返さない(fail-closed)。
        if (! $user->isMasterAdmin()) {
            $companyId = $user->company_id;
            if ($companyId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('company_id', $companyId);
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // 対象テーブル名でフィルタ。実カラムは target_table。
        // 後方互換のため request キーは target_table / table_name の両方を受け付ける。
        if ($request->filled('target_table')) {
            $query->where('target_table', $request->input('target_table'));
        } elseif ($request->filled('table_name')) {
            $query->where('target_table', $request->input('table_name'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }
}
