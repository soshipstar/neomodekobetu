<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentLoginPrintController extends Controller
{
    /**
     * 生徒のログイン情報を印刷用に取得
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($student->classroom_id, $user->accessibleClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $student->load('classroom:id,classroom_name');

        return response()->json([
            'success' => true,
            'data'    => [
                'student_name'   => $student->student_name,
                'username'       => $student->username,
                'classroom_name' => $student->classroom?->classroom_name,
                'grade_level'    => $student->grade_level,
            ],
        ]);
    }
}
