<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanComment;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyPlanController extends Controller
{
    /**
     * 週間計画一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = WeeklyPlan::with(['creator:id,full_name', 'comments.user:id,full_name']);

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('week_start_date')) {
            $query->where('week_start_date', $request->week_start_date);
        }

        if ($request->filled('year')) {
            $query->whereYear('week_start_date', $request->year);
        }

        $plans = $query->orderByDesc('week_start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 特定生徒の週間計画詳細を取得
     */
    public function show(Request $request, int $studentId): JsonResponse
    {
        $user = $request->user();

        $query = WeeklyPlan::with(['creator:id,full_name', 'comments.user:id,full_name', 'submissions'])
            ->where('student_id', $studentId);

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('week_start_date')) {
            $query->where('week_start_date', $request->week_start_date);
        }

        $plan = $query->orderByDesc('week_start_date')->first();

        if (! $plan) {
            return response()->json([
                'success' => true,
                'data'    => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $plan,
        ]);
    }

    /**
     * 週間計画を新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'      => 'required|exists:students,id',
            'week_start_date' => 'required|date',
            'weekly_goal'     => 'nullable|string',
            'shared_goal'     => 'nullable|string',
            'must_do'         => 'nullable|string',
            'should_do'       => 'nullable|string',
            'want_to_do'      => 'nullable|string',
            'plan_data'       => 'nullable|array',
            'plan_content'    => 'nullable|array',
        ]);

        $plan = WeeklyPlan::create(array_merge($validated, [
            'classroom_id' => $request->user()->classroom_id,
            'created_by'   => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $plan,
            'message' => '週間計画を作成しました。',
        ], 201);
    }

    /**
     * 週間計画を更新
     */
    public function update(Request $request, WeeklyPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'plan_content'              => 'nullable|array',
            'weekly_goal'               => 'nullable|string',
            'shared_goal'               => 'nullable|string',
            'must_do'                   => 'nullable|string',
            'should_do'                 => 'nullable|string',
            'want_to_do'               => 'nullable|string',
            'plan_data'                 => 'nullable|array',
            'weekly_goal_achievement'   => 'nullable|integer|min:0|max:5',
            'weekly_goal_comment'       => 'nullable|string',
            'shared_goal_achievement'   => 'nullable|integer|min:0|max:5',
            'shared_goal_comment'       => 'nullable|string',
            'must_do_achievement'       => 'nullable|integer|min:0|max:5',
            'must_do_comment'           => 'nullable|string',
            'should_do_achievement'     => 'nullable|integer|min:0|max:5',
            'should_do_comment'         => 'nullable|string',
            'want_to_do_achievement'    => 'nullable|integer|min:0|max:5',
            'want_to_do_comment'        => 'nullable|string',
            'daily_achievement'         => 'nullable|array',
            'overall_comment'           => 'nullable|string',
            'evaluated_at'              => 'nullable|date',
            'comment'                   => 'nullable|string', // コメント追加用
        ]);

        // コメントは別途保存
        if (! empty($validated['comment'])) {
            WeeklyPlanComment::create([
                'plan_id'  => $plan->id,
                'user_id'  => $request->user()->id,
                'comment'  => $validated['comment'],
            ]);
        }
        unset($validated['comment']);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh(['comments.user:id,full_name']),
            'message' => '週間計画を更新しました。',
        ]);
    }

    /**
     * 週間計画をPDF出力
     */
    public function pdf(Request $request, WeeklyPlan $plan)
    {
        $user = $request->user();

        if ($user->classroom_id && $plan->classroom_id !== $user->classroom_id) {
            abort(403, 'アクセス権限がありません。');
        }

        $plan->load(['creator:id,full_name', 'submissions']);

        $content = $plan->plan_content ?? [];

        $weekStart = $plan->week_start_date;
        $weekEnd = $weekStart->copy()->addDays(6);
        $submitDate = $weekStart->copy()->addDays(7);

        $weekStartFormatted = $weekStart->format('n月j日');
        $weekEndFormatted = $weekEnd->format('n月j日');
        $submitFormatted = $submitDate->format('n月j日');

        $filename = "weekly_plan_{$plan->id}_{$weekStart->format('Ymd')}.pdf";

        return PuppeteerPdfService::download('pdf.weekly-plan', [
            'plan' => $plan,
            'content' => $content,
            'submissions' => $plan->submissions,
            'weekStartFormatted' => $weekStartFormatted,
            'weekEndFormatted' => $weekEndFormatted,
            'submitFormatted' => $submitFormatted,
        ], $filename);
    }
}
