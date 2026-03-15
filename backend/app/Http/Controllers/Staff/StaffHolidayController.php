<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffHolidayController extends Controller
{
    /**
     * 休日一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Holiday::query();

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        if ($request->filled('year')) {
            $query->whereYear('holiday_date', $request->year);
        }

        $holidays = $query->orderBy('holiday_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $holidays,
        ]);
    }

    /**
     * 休日を登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'holiday_date' => 'required|date',
            'holiday_name' => 'required|string|max:100',
            'holiday_type' => 'nullable|string|max:50',
        ]);

        $holiday = Holiday::create([
            'classroom_id' => $user->classroom_id,
            'holiday_date' => $validated['holiday_date'],
            'holiday_name' => $validated['holiday_name'],
            'holiday_type' => $validated['holiday_type'] ?? null,
            'created_by'   => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $holiday,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * 休日を削除
     */
    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $holiday->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}
