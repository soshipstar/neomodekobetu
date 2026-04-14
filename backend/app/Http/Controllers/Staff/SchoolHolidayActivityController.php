<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SchoolHolidayActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolHolidayActivityController extends Controller
{
    /**
     * 学校休業日の活動一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = SchoolHolidayActivity::with('classroom:id,classroom_name');

        if ($classroomId) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        if ($request->filled('month')) {
            $query->whereMonth('activity_date', $request->month);
        }
        if ($request->filled('year')) {
            $query->whereYear('activity_date', $request->year);
        }

        $activities = $query->orderBy('activity_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $activities,
        ]);
    }

    /**
     * 月単位で学校休業日活動を一括保存（旧システム互換）
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $validated = $request->validate([
            'year'            => 'required|integer',
            'month'           => 'required|integer|min:1|max:12',
            'activity_dates'  => 'present|array',
            'activity_dates.*' => 'date',
        ]);

        $y = $validated['year'];
        $m = $validated['month'];
        $startDate = sprintf('%04d-%02d-01', $y, $m);
        $endDate = date('Y-m-t', strtotime($startDate));

        DB::transaction(function () use ($classroomId, $startDate, $endDate, $validated, $user) {
            // この月の既存レコードを削除
            SchoolHolidayActivity::where('classroom_id', $classroomId)
                ->whereBetween('activity_date', [$startDate, $endDate])
                ->delete();

            // 新しいレコードを挿入
            foreach ($validated['activity_dates'] as $date) {
                SchoolHolidayActivity::create([
                    'activity_date' => $date,
                    'classroom_id'  => $classroomId,
                    'created_by'    => $user->id,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => '保存しました。',
        ]);
    }

    /**
     * 学校休業日の活動を登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'activity_date' => 'required|date',
            'note'          => 'nullable|string|max:500',
        ]);

        $activity = SchoolHolidayActivity::create([
            'activity_date' => $validated['activity_date'],
            'classroom_id'  => $user->classroom_id,
            'note'          => $validated['note'] ?? null,
            'created_by'    => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $activity,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * 学校休業日の活動を更新
     */
    public function update(Request $request, SchoolHolidayActivity $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'activity_date' => 'sometimes|date',
            'note'          => 'nullable|string|max:500',
        ]);

        $activity->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $activity->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * 学校休業日の活動を削除
     */
    public function destroy(Request $request, SchoolHolidayActivity $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $activity->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * 学校休業日活動に生徒を割り当て
     */
    public function assign(Request $request, SchoolHolidayActivity $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'student_ids'   => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $activity->update([
            'assigned_student_ids' => $validated['student_ids'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $activity->fresh(),
            'message' => '生徒を割り当てました。',
        ]);
    }
}
