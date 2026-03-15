<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * 教室の生徒一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Student::query()->with('guardian:id,full_name,email');

        if ($classroomId) {
            $query->byClassroom($classroomId);
        }

        // 検索（名前・ユーザー名）
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // 学年フィルター
        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        // ステータスフィルター
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // デフォルトはアクティブのみ
            $query->active();
        }

        $perPage = $request->integer('per_page', 30);
        $students = $query->orderBy('student_name')
            ->paginate(min($perPage, 100));

        return response()->json([
            'success' => true,
            'data'    => $students->items(),
            'meta'    => [
                'current_page' => $students->currentPage(),
                'last_page'    => $students->lastPage(),
                'per_page'     => $students->perPage(),
                'total'        => $students->total(),
            ],
        ]);
    }

    /**
     * 生徒詳細を取得（保護者情報付き）
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        // 教室スコープチェック
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            return response()->json([
                'success' => false,
                'message' => 'アクセス権限がありません。',
            ], 403);
        }

        $student->load('guardian:id,full_name,email', 'classroom:id,classroom_name');

        return response()->json([
            'success' => true,
            'data'    => $student,
        ]);
    }
}
