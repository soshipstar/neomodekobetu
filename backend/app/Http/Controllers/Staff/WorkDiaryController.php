<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\WorkDiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkDiaryController extends Controller
{
    /**
     * 業務日誌一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = WorkDiary::with(['creator:id,full_name', 'updater:id,full_name']);

        // 主教室 + classroom_user ピボットで所属する全教室を対象にする
        if ($user->classroom_id) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        if ($request->filled('date')) {
            $query->where('diary_date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->where('diary_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('diary_date', '<=', $request->date_to);
        }

        if ($request->filled('month')) {
            $query->whereRaw("to_char(diary_date, 'YYYY-MM') = ?", [$request->month]);
        }

        $diaries = $query->orderByDesc('diary_date')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data'    => $diaries,
        ]);
    }

    /**
     * 業務日誌を新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'diary_date'               => 'required|date',
            'previous_day_review'      => 'nullable|string',
            'daily_communication'      => 'nullable|string',
            'daily_roles'              => 'nullable|string',
            'prev_day_children_status' => 'nullable|string',
            'children_special_notes'   => 'nullable|string',
            'other_notes'              => 'nullable|string',
        ]);

        // 同じ日付の日誌があるかチェック
        $existing = WorkDiary::where('classroom_id', $request->user()->classroom_id)
            ->where('diary_date', $validated['diary_date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'この日付の業務日誌は既に存在します。',
                'data'    => $existing,
            ], 422);
        }

        $diary = WorkDiary::create(array_merge($validated, [
            'classroom_id' => $request->user()->classroom_id,
            'created_by'   => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $diary,
            'message' => '業務日誌を作成しました。',
        ], 201);
    }

    /**
     * 業務日誌詳細を取得
     */
    public function show(Request $request, WorkDiary $diary): JsonResponse
    {
        $diary->load(['creator:id,full_name', 'updater:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $diary,
        ]);
    }

    /**
     * 業務日誌を更新
     */
    public function update(Request $request, WorkDiary $diary): JsonResponse
    {
        $validated = $request->validate([
            'previous_day_review'      => 'nullable|string',
            'daily_communication'      => 'nullable|string',
            'daily_roles'              => 'nullable|string',
            'prev_day_children_status' => 'nullable|string',
            'children_special_notes'   => 'nullable|string',
            'other_notes'              => 'nullable|string',
        ]);

        $diary->update(array_merge($validated, [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $diary->fresh(),
            'message' => '業務日誌を更新しました。',
        ]);
    }
}
