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
        $query = AuditLog::with('user:id,full_name,user_type');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->table_name);
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
