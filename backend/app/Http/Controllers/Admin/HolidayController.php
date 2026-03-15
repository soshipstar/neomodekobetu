<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * 休日一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query();

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
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
        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'holiday_date' => 'required|date',
            'holiday_name' => 'required|string|max:100',
        ]);

        $holiday = Holiday::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $holiday,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * 休日を削除
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}
