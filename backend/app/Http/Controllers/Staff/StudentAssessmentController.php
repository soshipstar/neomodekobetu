<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAssessmentController extends Controller
{
    /**
     * 生徒のアセスメント一覧を取得
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id && !in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }

        $assessments = StudentAssessment::where('student_id', $student->id)
            ->orderBy('domain')
            ->orderBy('item_key')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assessments,
        ]);
    }

    /**
     * アセスメント項目を保存（新規or更新）
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id && !in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }

        $validated = $request->validate([
            'domain' => 'required|string|max:50',
            'item_key' => 'required|string|max:50',
            'current_status' => 'nullable|string',
            'support_needs' => 'nullable|string',
            'level' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ]);

        $assessment = StudentAssessment::updateOrCreate(
            [
                'student_id' => $student->id,
                'domain' => $validated['domain'],
                'item_key' => $validated['item_key'],
            ],
            [
                'current_status' => $validated['current_status'] ?? null,
                'support_needs' => $validated['support_needs'] ?? null,
                'level' => $validated['level'] ?? 3,
                'notes' => $validated['notes'] ?? null,
                'assessed_by' => $user->id,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $assessment,
            'message' => 'アセスメントを保存しました。',
        ]);
    }
}
