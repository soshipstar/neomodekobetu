<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianWeeklyPlanController extends Controller
{
    /**
     * 保護者が閲覧可能な週間計画一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        // 生徒の教室に紐づく週間計画を取得
        $classroomIds = $user->students()->pluck('classroom_id')->unique();

        $query = WeeklyPlan::whereIn('classroom_id', $classroomIds)
            ->where('status', '!=', 'draft')
            ->with('classroom:id,classroom_name');

        if ($request->filled('week_start_date')) {
            $query->where('week_start_date', $request->week_start_date);
        }

        $plans = $query->orderByDesc('week_start_date')->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 週間計画の詳細を取得
     */
    public function show(Request $request, WeeklyPlan $plan): JsonResponse
    {
        $user = $request->user();
        $classroomIds = $user->students()->pluck('classroom_id')->unique()->toArray();

        if (! in_array($plan->classroom_id, $classroomIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $plan->load(['classroom:id,classroom_name', 'comments.user:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $plan,
        ]);
    }

    /**
     * 週間計画にコメントを追加
     */
    public function addComment(Request $request, WeeklyPlan $plan): JsonResponse
    {
        $user = $request->user();
        $classroomIds = $user->students()->pluck('classroom_id')->unique()->toArray();

        if (! in_array($plan->classroom_id, $classroomIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $comment = WeeklyPlanComment::create([
            'plan_id'        => $plan->id,
            'user_id'        => $user->id,
            'commenter_type' => 'guardian',
            'comment'        => $validated['comment'],
        ]);

        $comment->load('user:id,full_name');

        return response()->json([
            'success' => true,
            'data'    => $comment,
            'message' => 'コメントを投稿しました。',
        ], 201);
    }
}
