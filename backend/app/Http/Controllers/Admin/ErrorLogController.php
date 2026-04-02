<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->is_master) {
            return response()->json(['success' => false, 'message' => 'マスター管理者権限が必要です。'], 403);
        }
        return null;
    }

    /**
     * エラーログ一覧を取得（フィルタ・ページネーション付き）
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        $query = ErrorLog::with('user:id,full_name,user_type')
            ->orderByDesc('created_at');

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('exception_class', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * エラーログ詳細を取得
     */
    public function show(Request $request, ErrorLog $errorLog): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        $errorLog->load('user:id,full_name,user_type');

        return response()->json([
            'success' => true,
            'data'    => $errorLog,
        ]);
    }

    /**
     * エラーログを一括削除（古いログのクリーンアップ）
     */
    public function cleanup(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        $days = $request->integer('days', 30);
        $cutoff = now()->subDays($days);

        $deleted = ErrorLog::where('created_at', '<', $cutoff)->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted}件のログを削除しました。",
        ]);
    }

    /**
     * エラー統計サマリー
     */
    public function summary(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        $today = now()->startOfDay();
        $week = now()->subDays(7);

        return response()->json([
            'success' => true,
            'data'    => [
                'today'      => ErrorLog::where('created_at', '>=', $today)->count(),
                'this_week'  => ErrorLog::where('created_at', '>=', $week)->count(),
                'total'      => ErrorLog::count(),
                'by_level'   => [
                    'error'    => ErrorLog::where('level', 'error')->where('created_at', '>=', $week)->count(),
                    'warning'  => ErrorLog::where('level', 'warning')->where('created_at', '>=', $week)->count(),
                    'critical' => ErrorLog::where('level', 'critical')->where('created_at', '>=', $week)->count(),
                ],
            ],
        ]);
    }
}
