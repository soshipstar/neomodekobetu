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

        if ($user->classroom_id && !in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $student->load('classroom:id,classroom_name,address,phone');

        // 印刷資料にはログインID + 初期パスワードを載せる必要があるため
        // password_plain を一時的に可視化して返す ($hidden 解除)。
        $student->makeVisible('password_plain');

        return response()->json([
            'success' => true,
            'data'    => [
                'student_name'    => $student->student_name,
                'username'        => $student->username,
                'password_plain'  => $student->password_plain,
                'classroom_name'  => $student->classroom?->classroom_name,
                'classroom_address' => $student->classroom?->address,
                'classroom_phone' => $student->classroom?->phone,
                'grade_level'     => $student->grade_level,
            ],
        ]);
    }
}
