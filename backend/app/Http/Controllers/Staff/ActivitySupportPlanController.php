<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use App\Models\AiGenerationLog;
use App\Models\DailyRecord;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

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
            $query->where('classroom_id', $classroomId);
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
            $data['usage_count'] = 0;
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

        if ($user->classroom_id && $plan->classroom_id !== $user->classroom_id) {
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

        if ($user->classroom_id && $plan->classroom_id !== $user->classroom_id) {
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

        if ($user->classroom_id && $plan->classroom_id !== $user->classroom_id) {
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
            $query->where('classroom_id', $classroomId);
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

        if ($user->classroom_id && $plan->classroom_id !== $user->classroom_id) {
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
            'mon' => '月', 'tue' => '火', 'wed' => '水',
            'thu' => '木', 'fri' => '金', 'sat' => '土', 'sun' => '日',
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
        $request->validate([
            'activity_name' => 'required|string',
            'activity_purpose' => 'nullable|string',
            'activity_content' => 'nullable|string',
        ]);

        $activityName = $request->activity_name;
        $activityPurpose = $request->activity_purpose ?? '';
        $activityContent = $request->activity_content ?? '';

        $prompt = "あなたは放課後等デイサービスの支援員です。以下の活動について、五領域への配慮を生成してください。\n\n";
        $prompt .= "【活動名】{$activityName}\n";
        if ($activityPurpose) {
            $prompt .= "【活動の目的】{$activityPurpose}\n";
        }
        if ($activityContent) {
            $prompt .= "【活動の内容】{$activityContent}\n";
        }
        $prompt .= "\n以下のJSON形式で出力してください:\n";
        $prompt .= "{\n";
        $prompt .= '  "five_domains_consideration": "【健康・生活】\n基本的な生活習慣や健康管理に関する配慮\n\n【運動・感覚】\n身体の動きや感覚の活用に関する配慮\n\n【認知・行動】\n物事の理解や問題解決、行動コントロールに関する配慮\n\n【言語・コミュニケーション】\n言葉の理解や表現に関する配慮\n\n【人間関係・社会性】\n他者との関わりやルールの理解に関する配慮",' . "\n";
        $prompt .= '  "other_notes": "活動実施上の配慮事項・注意点"' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "各領域は具体的かつ実践的な内容で記載してください。放課後等デイサービスの利用児童の特性を踏まえた配慮を含めてください。";

        try {
            $startTime = microtime(true);

            $response = OpenAI::chat()->create([
                'model' => config('services.openai.model', 'gpt-5'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '放課後等デイサービスの支援案を作成する専門家AIアシスタントです。JSON形式で回答してください。',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $content = json_decode($response->choices[0]->message->content, true) ?? [];

            $this->logGeneration('activity_support_plan_five_domains', $response, $prompt, $content, $durationMs);

            return response()->json([
                'success' => true,
                'data' => $content,
            ]);
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
        $request->validate([
            'activity_name' => 'required|string',
            'activity_purpose' => 'nullable|string',
            'total_duration' => 'required|integer',
            'schedule' => 'required|array',
            'target_grade' => 'nullable|string',
        ]);

        $activityName = $request->activity_name;
        $activityPurpose = $request->activity_purpose ?? '';
        $totalDuration = $request->total_duration;
        $schedule = $request->schedule;
        $targetGrade = $request->target_grade ?? '';

        // 対象年齢層の日本語変換
        $gradeLabels = [
            'preschool' => '小学生未満',
            'elementary' => '小学生',
            'junior_high' => '中学生',
            'high_school' => '高校生',
        ];

        $targetGradeText = '';
        if ($targetGrade) {
            $grades = array_map(fn($g) => $gradeLabels[trim($g)] ?? trim($g), explode(',', $targetGrade));
            $targetGradeText = implode('、', $grades);
        }

        // スケジュール情報を文字列に変換
        $scheduleText = '';
        foreach ($schedule as $i => $item) {
            $num = $i + 1;
            $type = ($item['type'] ?? '') === 'routine' ? '毎日の支援' : '主活動';
            $name = $item['name'] ?? '';
            $duration = $item['duration'] ?? 15;
            $content = $item['content'] ?? '';
            $scheduleText .= "{$num}. [{$type}] {$name}（{$duration}分）";
            if ($content) {
                $scheduleText .= "\n   内容: {$content}";
            }
            $scheduleText .= "\n";
        }

        $prompt = "あなたは放課後等デイサービスの支援員です。以下の活動スケジュールに基づいて、詳細な活動内容を生成してください。\n\n";
        $prompt .= "【活動名】{$activityName}\n";
        if ($activityPurpose) {
            $prompt .= "【活動の目的】{$activityPurpose}\n";
        }
        $prompt .= "【総活動時間】{$totalDuration}分\n";
        if ($targetGradeText) {
            $prompt .= "【対象年齢層】{$targetGradeText}\n";
        }
        $prompt .= "\n【活動スケジュール】\n{$scheduleText}\n";
        $prompt .= "\n以下のJSON形式で出力してください:\n";
        $prompt .= "{\n";
        $prompt .= '  "activity_content": "■ 詳細な活動の流れ\n\n【活動1: ○○】（○○分）\n・導入：...\n・展開：...\n・スタッフの役割と配置：...\n\n（各活動ごとに記載）\n\n■ 準備物\n・...\n・...",' . "\n";
        $prompt .= '  "other_notes": "安全上の注意点、個別対応の配慮事項、観察ポイント"' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "注意:\n";
        $prompt .= "- 毎日の支援（ルーティーン）は簡潔に、主活動は詳しく記載してください\n";
        $prompt .= "- 発達段階に応じた声かけの例を含めてください\n";
        $prompt .= "- 活動の切り替え時の配慮も含めてください\n";
        $prompt .= "- 時間配分を各活動に明記してください";

        try {
            $startTime = microtime(true);

            $response = OpenAI::chat()->create([
                'model' => config('services.openai.model', 'gpt-5'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '放課後等デイサービスの活動計画を作成する専門家AIアシスタントです。JSON形式で回答してください。',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $content = json_decode($response->choices[0]->message->content, true) ?? [];

            $this->logGeneration('activity_support_plan_schedule', $response, $prompt, $content, $durationMs);

            return response()->json([
                'success' => true,
                'data' => $content,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI schedule content generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => '生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function logGeneration(string $type, object $response, string $prompt, array $output, int $durationMs): void
    {
        try {
            AiGenerationLog::create([
                'user_id' => Auth::id(),
                'generation_type' => $type,
                'model' => $response->model ?? config('services.openai.model', 'gpt-5'),
                'prompt_tokens' => $response->usage->promptTokens ?? 0,
                'completion_tokens' => $response->usage->completionTokens ?? 0,
                'input_data' => ['prompt' => mb_substr($prompt, 0, 5000)],
                'output_data' => $output,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log AI generation', ['error' => $e->getMessage()]);
        }
    }
}
