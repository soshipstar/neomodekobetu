<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use App\Models\DailyRecord;
use App\Services\ActivitySupportPlanAiService;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class ActivitySupportPlanController extends Controller
{
    /**
     * 支援案一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = ActivitySupportPlan::with('staff:id,full_name');

        if ($classroomId) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        // 検索フィルタ
        if ($request->filled('tag')) {
            $tag = $request->tag;
            $query->where(function ($q) use ($tag) {
                $q->where('tags', $tag)
                  ->orWhere('tags', 'LIKE', "$tag,%")
                  ->orWhere('tags', 'LIKE', "%,$tag,%")
                  ->orWhere('tags', 'LIKE', "%,$tag");
            });
        }

        if ($request->filled('day_of_week')) {
            $day = $request->day_of_week;
            $query->where(function ($q) use ($day) {
                $q->where('day_of_week', $day)
                  ->orWhere('day_of_week', 'LIKE', "$day,%")
                  ->orWhere('day_of_week', 'LIKE', "%,$day,%")
                  ->orWhere('day_of_week', 'LIKE', "%,$day");
            });
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('activity_name', 'ILIKE', $keyword)
                  ->orWhere('activity_content', 'ILIKE', $keyword)
                  ->orWhere('activity_purpose', 'ILIKE', $keyword);
            });
        }

        $plans = $query
            ->orderByDesc('activity_date')
            ->orderByDesc('created_at')
            ->get();

        $plansData = $plans->map(function ($plan) {
            $data = $plan->toArray();
            $data['staff_name'] = $plan->staff->full_name ?? '';
            $data['usage_count'] = DailyRecord::where('support_plan_id', $plan->id)->count();
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $plansData,
        ]);
    }

    /**
     * 支援案の詳細を取得（1件）
     */
    public function show(Request $request, ActivitySupportPlan $plan): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($plan->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * 支援案を新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'activity_name' => 'required|string|max:255',
            'activity_date' => 'required|date',
            'plan_type' => 'sometimes|in:normal,event,other',
            'target_grade' => 'nullable|string',
            'activity_purpose' => 'nullable|string',
            'activity_content' => 'nullable|string',
            'tags' => 'nullable|string',
            'day_of_week' => 'nullable|string',
            'five_domains_consideration' => 'nullable|string',
            'other_notes' => 'nullable|string',
            'total_duration' => 'sometimes|integer|min:30|max:480',
            'activity_schedule' => 'nullable|array',
        ]);

        $plan = ActivitySupportPlan::create([
            ...$validated,
            'staff_id' => $user->id,
            'classroom_id' => $user->classroom_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => '支援案を作成しました。',
        ], 201);
    }

    /**
     * 支援案を更新
     */
    public function update(Request $request, ActivitySupportPlan $plan): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($plan->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'activity_name' => 'sometimes|string|max:255',
            'activity_date' => 'sometimes|date',
            'plan_type' => 'sometimes|in:normal,event,other',
            'target_grade' => 'nullable|string',
            'activity_purpose' => 'nullable|string',
            'activity_content' => 'nullable|string',
            'tags' => 'nullable|string',
            'day_of_week' => 'nullable|string',
            'five_domains_consideration' => 'nullable|string',
            'other_notes' => 'nullable|string',
            'total_duration' => 'sometimes|integer|min:30|max:480',
            'activity_schedule' => 'nullable|array',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'data' => $plan->fresh(),
            'message' => '支援案を更新しました。',
        ]);
    }

    /**
     * 支援案を削除
     */
    public function destroy(Request $request, ActivitySupportPlan $plan): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($plan->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // 使用中かチェック（daily_recordsで使用されている場合）
        $usageCount = DailyRecord::where('support_plan_id', $plan->id)->count();
        if ($usageCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "この支援案は既に活動で{$usageCount}回使用されているため削除できません。",
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => '支援案を削除しました。',
        ]);
    }

    /**
     * 過去の支援案を取得（引用用）
     */
    public function pastPlans(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = ActivitySupportPlan::select([
            'id', 'activity_date', 'activity_name', 'activity_purpose',
            'activity_content', 'five_domains_consideration', 'other_notes',
            'tags', 'day_of_week', 'plan_type', 'target_grade',
            'total_duration', 'activity_schedule',
        ]);

        if ($classroomId) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        // 期間フィルタ
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('activity_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('period') && $request->period !== 'all') {
            $days = (int) $request->period ?: 30;
            $query->where('activity_date', '>=', now()->subDays($days)->toDateString());
        }

        $plans = $query
            ->orderByDesc('activity_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * 支援案をPDF出力
     */
    public function pdf(Request $request, ActivitySupportPlan $plan)
    {
        $user = $request->user();

        if ($user->classroom_id && !in_array($plan->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'アクセス権限がありません。');
        }

        $plan->load('staff:id,full_name');

        $planTypeLabels = [
            'normal' => '通常',
            'event' => 'イベント',
            'other' => 'その他',
        ];

        $gradeLabels = [
            'preschool' => '小学生未満',
            'elementary' => '小学生',
            'junior_high' => '中学生',
            'high_school' => '高校生',
        ];

        $dayLabels = [
            'monday' => '月', 'tuesday' => '火', 'wednesday' => '水',
            'thursday' => '木', 'friday' => '金', 'saturday' => '土', 'sunday' => '日',
        ];

        $planTypeLabel = $planTypeLabels[$plan->plan_type] ?? $plan->plan_type;

        $targetGradeLabel = '';
        if ($plan->target_grade) {
            $grades = array_map(fn($g) => $gradeLabels[trim($g)] ?? trim($g), explode(',', $plan->target_grade));
            $targetGradeLabel = implode('、', $grades);
        }

        $dayOfWeekLabel = '';
        if ($plan->day_of_week) {
            $dows = array_map(fn($d) => $dayLabels[trim($d)] ?? trim($d), explode(',', $plan->day_of_week));
            $dayOfWeekLabel = implode('、', $dows);
        }

        $filename = "activity_support_plan_{$plan->id}.pdf";

        return PuppeteerPdfService::download('pdf.activity-support-plan', [
            'plan' => $plan,
            'planTypeLabel' => $planTypeLabel,
            'targetGradeLabel' => $targetGradeLabel,
            'dayOfWeekLabel' => $dayOfWeekLabel,
        ], $filename);
    }

    /**
     * AIで五領域への配慮を生成
     */
    public function generateAiFiveDomains(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_name' => 'required|string',
            'activity_purpose' => 'nullable|string',
            'activity_content' => 'nullable|string',
            // 対象学年を受けて文言を学年相応に調整する (複数指定可・カンマ区切り)
            'target_grade' => 'nullable|string',
        ]);

        // プロンプト構築・OpenAI 呼び出しは ActivitySupportPlanAiService に集約
        // (同期/非同期ジョブで同一ロジックを共有)。同期版は安定回線向けに維持。
        try {
            $content = app(ActivitySupportPlanAiService::class)
                ->generateFiveDomains($validated, $request->user()->id);

            return response()->json(['success' => true, 'data' => $content]);
        } catch (\Throwable $e) {
            Log::error('AI five domains generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AIでスケジュールをもとに活動内容を生成
     */
    public function generateAiScheduleContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_name' => 'required|string',
            'activity_purpose' => 'nullable|string',
            'total_duration' => 'required|integer',
            'schedule' => 'required|array',
            'target_grade' => 'nullable|string',
        ]);

        // プロンプト構築・OpenAI 呼び出しは ActivitySupportPlanAiService に集約
        // (同期/非同期ジョブで同一ロジックを共有)。同期版は安定回線向けに維持。
        try {
            $content = app(ActivitySupportPlanAiService::class)
                ->generateScheduleContent($validated, $request->user()->id);

            return response()->json(['success' => true, 'data' => $content]);
        } catch (\Throwable $e) {
            Log::error('AI schedule content generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }
}
