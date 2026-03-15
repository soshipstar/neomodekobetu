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

        if ($request->filled('month')) {
            $query->whereMonth('holiday_date', $request->month);
        }

        $holidays = $query->orderBy('holiday_date')->get();

        $mapped = $holidays->map(function ($holiday) {
            $data = $holiday->toArray();
            $data['date'] = $holiday->holiday_date;
            $data['name'] = $holiday->holiday_name;
            $data['is_recurring'] = $holiday->holiday_type === 'regular';
            return $data;
        });

        return response()->json([
            'success' => true,
            'data'    => $mapped,
        ]);
    }

    /**
     * 休日を登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'holiday_date' => 'required_without:date|date',
            'date'         => 'required_without:holiday_date|date',
            'holiday_name' => 'required_without:name|string|max:100',
            'name'         => 'required_without:holiday_name|string|max:100',
            'holiday_type' => 'nullable|string|max:50',
            'is_recurring' => 'nullable|boolean',
        ]);

        $holidayDate = $validated['holiday_date'] ?? $validated['date'];
        $holidayName = $validated['holiday_name'] ?? $validated['name'];
        $holidayType = $validated['holiday_type'] ?? (isset($validated['is_recurring']) ? ($validated['is_recurring'] ? 'regular' : null) : null);

        $holiday = Holiday::create([
            'classroom_id' => $user->classroom_id,
            'holiday_date' => $holidayDate,
            'holiday_name' => $holidayName,
            'holiday_type' => $holidayType,
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
