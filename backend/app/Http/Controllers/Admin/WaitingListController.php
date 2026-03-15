<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitingListController extends Controller
{
    /**
     * 待機リスト（status=waiting の生徒一覧）を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::where('status', 'waiting')
            ->with([
                'classroom:id,classroom_name',
                'guardian:id,full_name,email',
            ]);

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $students = $query->orderBy('desired_start_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 待機生徒の情報を更新（ステータス変更含む）
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'status'               => 'sometimes|string|in:waiting,active,inactive',
            'desired_start_date'   => 'nullable|date',
            'desired_weekly_count' => 'nullable|integer|min:1|max:7',
            'waiting_notes'        => 'nullable|string|max:1000',
            'classroom_id'         => 'sometimes|exists:classrooms,id',
        ]);

        $student->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $student->fresh()->load('classroom:id,classroom_name'),
            'message' => '更新しました。',
        ]);
    }
}
