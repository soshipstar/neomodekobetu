<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Traits\ResolvesStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentProfileController extends Controller
{
    use ResolvesStudent;
    /**
     * 生徒プロフィール情報を取得
     */
    public function show(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $student->load(['classroom:id,classroom_name', 'guardian:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $student->id,
                'student_name'   => $student->student_name,
                'username'       => $student->username,
                'birth_date'     => $student->birth_date,
                'grade_level'    => $student->grade_level,
                'classroom_name' => $student->classroom?->classroom_name,
                'guardian_name'  => $student->guardian?->full_name,
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
            'new_password'     => 'required|string|min:6|confirmed',
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

    // getStudent() は ResolvesStudent トレイトで提供
}
