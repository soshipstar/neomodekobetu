<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanSubmission;
use App\Traits\ResolvesStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentWeeklyPlanController extends Controller
{
    use ResolvesStudent;
    /**
     * 生徒が閲覧可能な週間計画を取得
     * week_start パラメータ指定時はその週の自分の計画を返す
     * 指定なしの場合はページネーション付き一覧を返す
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        // 特定の週指定がある場合、その週の計画を返す
        if ($request->filled('week_start')) {
            $plan = WeeklyPlan::where('student_id', $student->id)
                ->where('week_start_date', $request->input('week_start'))
                ->with(['submissions', 'comments.user'])
                ->first();

            return response()->json([
                'success' => true,
                'data'    => $plan,
            ]);
        }

        // 一覧（自分の計画のみ）
        $plans = WeeklyPlan::where('student_id', $student->id)
            ->with(['submissions'])
            ->orderByDesc('week_start_date')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 週間計画を新規作成（生徒）
     */
    public function store(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'weekly_goal'     => 'nullable|string',
            'shared_goal'     => 'nullable|string',
            'must_do'         => 'nullable|string',
            'should_do'       => 'nullable|string',
            'want_to_do'      => 'nullable|string',
            'plan_data'       => 'nullable|array',
        ]);

        // 同じ週の計画が既に存在する場合はエラー
        $existing = WeeklyPlan::where('student_id', $student->id)
            ->where('week_start_date', $validated['week_start_date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'この週の計画は既に存在します。',
            ], 422);
        }

        $plan = WeeklyPlan::create(array_merge($validated, [
            'student_id'     => $student->id,
            'classroom_id'   => $student->classroom_id,
            'created_by'     => $request->user()->id,
            'created_by_type' => 'student',
        ]));

        return response()->json([
            'success' => true,
            'data'    => $plan->load(['submissions', 'comments.user']),
            'message' => '週間計画を作成しました。',
        ], 201);
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

        if ($plan->student_id !== $student->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'weekly_goal'  => 'nullable|string',
            'shared_goal'  => 'nullable|string',
            'must_do'      => 'nullable|string',
            'should_do'    => 'nullable|string',
            'want_to_do'   => 'nullable|string',
            'plan_data'    => 'nullable|array',
            'submissions'  => 'nullable|array',
            'submissions.*.submission_item' => 'required|string|max:500',
            'submissions.*.is_completed'    => 'boolean',
        ]);

        DB::transaction(function () use ($plan, $student, $validated) {
            // Update plan content
            $planFields = ['weekly_goal', 'shared_goal', 'must_do', 'should_do', 'want_to_do', 'plan_data'];
            $updateData = [];
            foreach ($planFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field];
                }
            }
            if (!empty($updateData)) {
                $plan->update($updateData);
            }

            // Update submissions
            if (!empty($validated['submissions'])) {
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
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh(),
            'message' => '保存しました。',
        ]);
    }

    // getStudent() は ResolvesStudent トレイトで提供
}
