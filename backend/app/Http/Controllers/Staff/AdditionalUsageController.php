<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalUsage;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdditionalUsageController extends Controller
{
    /**
     * 追加利用一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = AdditionalUsage::with('student:id,student_name,classroom_id');

        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('usage_date', $request->month)
                  ->whereYear('usage_date', $request->year);
        }

        $usages = $query->orderBy('usage_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $usages,
        ]);
    }

    /**
     * 追加利用を登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'usage_date' => 'required|date',
            'notes'      => 'nullable|string|max:500',
        ]);

        // 教室アクセス権チェック
        if ($user->classroom_id) {
            $student = Student::where('id', $validated['student_id'])
                ->where('classroom_id', $user->classroom_id)
                ->first();

            if (! $student) {
                return response()->json(['success' => false, 'message' => '生徒が見つかりません。'], 404);
            }
        }

        // 重複チェック
        $existing = AdditionalUsage::where('student_id', $validated['student_id'])
            ->where('usage_date', $validated['usage_date'])
            ->first();

        if ($existing) {
            $existing->update(['notes' => $validated['notes'] ?? null]);

            return response()->json([
                'success' => true,
                'data'    => $existing->fresh(),
                'message' => '更新しました。',
            ]);
        }

        $usage = AdditionalUsage::create([
            'student_id' => $validated['student_id'],
            'usage_date' => $validated['usage_date'],
            'notes'      => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $usage,
            'message' => '登録しました。',
        ], 201);
    }

    /**
     * 追加利用を削除
     */
    public function destroy(Request $request, AdditionalUsage $usage): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id) {
            $student = $usage->student;
            if ($student && $student->classroom_id !== $user->classroom_id) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $usage->delete();

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }
}
