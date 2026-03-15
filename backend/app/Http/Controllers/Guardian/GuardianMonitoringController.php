<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\MonitoringRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianMonitoringController extends Controller
{
    /**
     * 保護者が閲覧可能なモニタリング記録一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        $records = MonitoringRecord::whereIn('student_id', $studentIds)
            ->where('is_official', true)
            ->with(['student:id,student_name', 'details'])
            ->orderByDesc('monitoring_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * モニタリング記録の詳細を取得
     */
    public function show(Request $request, MonitoringRecord $record): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();

        if (! in_array($record->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $record->load(['student:id,student_name', 'details', 'plan']);

        return response()->json([
            'success' => true,
            'data'    => $record,
        ]);
    }

    /**
     * モニタリング記録を保護者確認
     */
    public function confirm(Request $request, MonitoringRecord $record): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();

        if (! in_array($record->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        if ($record->guardian_confirmed) {
            return response()->json(['success' => false, 'message' => '既に確認済みです。'], 422);
        }

        $record->update([
            'guardian_confirmed'    => true,
            'guardian_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $record->fresh(),
            'message' => '確認しました。',
        ]);
    }
}
