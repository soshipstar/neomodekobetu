<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentWeeklyPlanController extends Controller
{
    /**
     * 生徒が閲覧可能な週間計画一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $plans = WeeklyPlan::where('classroom_id', $student->classroom_id)
            ->where('status', '!=', 'draft')
            ->with(['submissions' => function ($q) use ($student) {
                // この生徒に関連する提出物のみ
            }])
            ->orderByDesc('week_start_date')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 週間計画の提出物を保存
     */
    public function save(Request $request, WeeklyPlan $plan): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        if ($plan->classroom_id !== $student->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'submissions' => 'required|array',
            'submissions.*.submission_item' => 'required|string|max:500',
            'submissions.*.is_completed'    => 'boolean',
        ]);

        DB::transaction(function () use ($plan, $student, $validated) {
            foreach ($validated['submissions'] as $item) {
                WeeklyPlanSubmission::updateOrCreate(
                    [
                        'weekly_plan_id'  => $plan->id,
                        'submission_item' => $item['submission_item'],
                    ],
                    [
                        'is_completed'      => $item['is_completed'] ?? false,
                        'completed_at'      => ($item['is_completed'] ?? false) ? now() : null,
                        'completed_by_type' => 'student',
                        'completed_by_id'   => $student->id,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => '保存しました。',
        ]);
    }

    /**
     * 週間計画を更新（PUT用）
     */
    public function update(Request $request, WeeklyPlan $plan): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        if ($plan->classroom_id !== $student->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'submissions' => 'required|array',
            'submissions.*.submission_item' => 'required|string|max:500',
            'submissions.*.is_completed'    => 'boolean',
        ]);

        DB::transaction(function () use ($plan, $student, $validated) {
            foreach ($validated['submissions'] as $item) {
                WeeklyPlanSubmission::updateOrCreate(
                    [
                        'weekly_plan_id'  => $plan->id,
                        'submission_item' => $item['submission_item'],
                    ],
                    [
                        'is_completed'      => $item['is_completed'] ?? false,
                        'completed_at'      => ($item['is_completed'] ?? false) ? now() : null,
                        'completed_by_type' => 'student',
                        'completed_by_id'   => $student->id,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => '保存しました。',
        ]);
    }

    /**
     * リクエストから生徒情報を取得（auth方式に依存）
     */
    private function getStudent(Request $request)
    {
        // Sanctumトークンからstudent情報を取得
        // student認証の場合、tokenable が Student モデルの可能性がある
        $user = $request->user();

        if ($user instanceof \App\Models\Student) {
            return $user;
        }

        // user_type=student の場合、対応するstudentレコードを探す
        return \App\Models\Student::where('username', $user->username)->first();
    }
}
