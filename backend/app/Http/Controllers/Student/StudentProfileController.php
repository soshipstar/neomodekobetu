<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentProfileController extends Controller
{
    /**
     * 生徒プロフィール情報を取得
     */
    public function show(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $student->load('classroom:id,classroom_name');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $student->id,
                'student_name'   => $student->student_name,
                'username'       => $student->username,
                'grade_level'    => $student->grade_level,
                'classroom'      => $student->classroom,
                'scheduled_days' => $student->getScheduledDays(),
            ],
        ]);
    }

    /**
     * パスワードを変更
     */
    public function changePassword(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:4|confirmed',
        ]);

        if (! Hash::check($request->current_password, $student->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => '現在のパスワードが正しくありません。',
            ], 422);
        }

        $student->update([
            'password_hash' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'パスワードを変更しました。',
        ]);
    }

    /**
     * リクエストから生徒情報を取得
     */
    private function getStudent(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Student) {
            return $user;
        }

        return Student::where('username', $user->username)->first();
    }
}
