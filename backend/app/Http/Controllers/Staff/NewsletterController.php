<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use App\Models\DailyRecord;
use App\Models\Event;
use App\Models\Newsletter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class NewsletterController extends Controller
{
    /**
     * お便り一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Newsletter::with('creator:id,full_name');

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        $newsletters = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $newsletters,
        ]);
    }

    /**
     * お便りを新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'              => 'required|integer|min:2020|max:2100',
            'month'             => 'required|integer|min:1|max:12',
            'title'             => 'required|string|max:255',
            'greeting'          => 'nullable|string',
            'event_calendar'    => 'nullable|string',
            'event_details'     => 'nullable|string',
            'weekly_reports'    => 'nullable|string',
            'weekly_intro'      => 'nullable|string',
            'event_results'     => 'nullable|string',
            'requests'          => 'nullable|string',
            'others'            => 'nullable|string',
            'elementary_report' => 'nullable|string',
            'junior_report'     => 'nullable|string',
        ]);

        $newsletter = Newsletter::create(array_merge($validated, [
            'classroom_id' => $request->user()->classroom_id,
            'created_by'   => $request->user()->id,
            'is_published' => false,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
            'message' => 'お便りを作成しました。',
        ], 201);
    }

    /**
     * お便り詳細を取得
     */
    public function show(Newsletter $newsletter): JsonResponse
    {
        $newsletter->load('creator:id,full_name');

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
        ]);
    }

    /**
     * お便りを更新（下書き保存）
     */
    public function update(Request $request, Newsletter $newsletter): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'greeting'          => 'nullable|string',
            'event_calendar'    => 'nullable|string',
            'event_details'     => 'nullable|string',
            'weekly_reports'    => 'nullable|string',
            'weekly_intro'      => 'nullable|string',
            'event_results'     => 'nullable|string',
            'requests'          => 'nullable|string',
            'others'            => 'nullable|string',
            'elementary_report' => 'nullable|string',
            'junior_report'     => 'nullable|string',
        ]);

        $newsletter->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $newsletter->fresh(),
            'message' => '下書きを保存しました。',
        ]);
    }

    /**
     * お便りを削除
     */
    public function destroy(Newsletter $newsletter): JsonResponse
    {
        if ($newsletter->is_published) {
            return response()->json([
                'success' => false,
                'message' => '公開済みのお便りは削除できません。',
            ], 422);
        }

        $newsletter->delete();

        return response()->json([
            'success' => true,
            'message' => 'お便りを削除しました。',
        ]);
    }

    /**
     * AI でお便り内容を生成（活動記録・イベント・支援案の文脈データ付き）
     */
    public function generateAi(Request $request, Newsletter $newsletter): JsonResponse
    {
        $request->validate([
            'section' => 'required|string|in:greeting,event_details,weekly_reports,event_results,others',
            'context' => 'nullable|string',
        ]);

        $section = $request->section;
        $context = $request->context ?? '';

        $sectionLabels = [
            'greeting'       => 'あいさつ文',
            'event_details'  => '行事の詳細',
            'weekly_reports' => '週報',
            'event_results'  => '行事の結果報告',
            'others'         => 'その他のお知らせ',
        ];

        $sectionLabel = $sectionLabels[$section] ?? $section;

        // Build rich context from database
        $richContext = $this->buildRichContext($newsletter);

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => '児童発達支援施設のスタッフとして、保護者向けのお便りの文章を作成します。温かみがあり、丁寧な表現を心がけてください。'
                            . '以下の教室情報や活動データを参考にして、具体的で臨場感のある文章を書いてください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "{$newsletter->year}年{$newsletter->month}月号のお便り「{$newsletter->title}」の"
                            . "「{$sectionLabel}」セクションの文章を生成してください。\n\n"
                            . ($richContext ? "{$richContext}\n\n" : '')
                            . ($context ? "【スタッフからの補足情報】{$context}\n\n" : '')
                            . "上記の情報を踏まえて適切な文章を生成してください。HTMLタグは使わず、プレーンテキストで出力してください。",
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 1000,
            ]);

            $generatedText = $response->choices[0]->message->content;

            return response()->json([
                'success' => true,
                'data'    => [
                    'section' => $section,
                    'content' => $generatedText,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * お便りの年月・教室に基づいてAI生成用の文脈データを構築する
     */
    private function buildRichContext(Newsletter $newsletter): string
    {
        $classroomId = $newsletter->classroom_id;
        if (!$classroomId) {
            return '';
        }

        $parts = [];

        // 期間の開始・終了日を算出
        $periodStart = Carbon::create($newsletter->year, $newsletter->month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // 【教室情報】
        $newsletter->loadMissing('classroom');
        $classroom = $newsletter->classroom;
        if ($classroom) {
            $studentCount = $classroom->students()->count();
            $parts[] = "【教室情報】{$classroom->classroom_name}、在籍{$studentCount}名";
        }

        // 【期間の活動記録】
        $dailyRecords = DailyRecord::where('classroom_id', $classroomId)
            ->whereBetween('record_date', [$periodStart, $periodEnd])
            ->orderBy('record_date')
            ->get(['record_date', 'activity_name', 'common_activity']);

        if ($dailyRecords->isNotEmpty()) {
            $lines = ['【期間の活動記録】'];
            foreach ($dailyRecords as $record) {
                $date = $record->record_date->format('n/j');
                $activity = $record->activity_name ?? '';
                $common = $record->common_activity ?? '';
                $detail = collect([$activity, $common])->filter()->implode(' - ');
                if ($detail) {
                    $lines[] = "- {$date}: {$detail}";
                }
            }
            if (count($lines) > 1) {
                $parts[] = implode("\n", $lines);
            }
        }

        // 【今後のイベント】 (当月以降のイベント)
        $events = Event::where('classroom_id', $classroomId)
            ->where('event_date', '>=', $periodStart)
            ->where('event_date', '<=', $periodEnd->copy()->addMonth())
            ->orderBy('event_date')
            ->get(['event_date', 'event_name']);

        if ($events->isNotEmpty()) {
            $lines = ['【今後のイベント】'];
            foreach ($events as $event) {
                $date = $event->event_date->format('n/j');
                $lines[] = "- {$date}: {$event->event_name}";
            }
            $parts[] = implode("\n", $lines);
        }

        // 【活動支援案のテーマ】
        $supportPlans = ActivitySupportPlan::where('classroom_id', $classroomId)
            ->whereBetween('activity_date', [$periodStart, $periodEnd])
            ->orderBy('activity_date')
            ->get(['activity_name', 'activity_purpose']);

        if ($supportPlans->isNotEmpty()) {
            $themes = $supportPlans->map(function ($plan) {
                $name = $plan->activity_name ?? '';
                $purpose = $plan->activity_purpose ?? '';
                return collect([$name, $purpose])->filter()->implode('（') . ($purpose ? '）' : '');
            })->filter()->implode('、');

            if ($themes) {
                $parts[] = "【活動支援案のテーマ】{$themes}";
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * お便りを発行（公開）
     */
    public function publish(Request $request, Newsletter $newsletter): JsonResponse
    {
        if (empty($newsletter->title) || empty($newsletter->greeting)) {
            return response()->json([
                'success' => false,
                'message' => 'タイトルとあいさつ文は必須です。',
            ], 422);
        }

        $newsletter->update([
            'is_published' => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
            'message' => '通信を発行しました。',
        ]);
    }

    /**
     * お便り PDF データを返す
     */
    public function pdf(Request $request, Newsletter $newsletter): JsonResponse
    {
        $newsletter->load(['classroom', 'creator:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
        ]);
    }
}
