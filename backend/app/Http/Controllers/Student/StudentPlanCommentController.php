<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentPlanCommentController extends Controller
{
    /**
     * 支援計画にコメントを追加
     */
    public function store(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        if ($plan->student_id !== $student->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        // support_plan_comments テーブルにコメントを保存
        DB::table('support_plan_comments')->insert([
            'plan_id'      => $plan->id,
            'commenter_id' => $student->id,
            'commenter_type' => 'student',
            'comment'      => $request->comment,
            'created_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'コメントを送信しました。',
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
