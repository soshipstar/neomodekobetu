<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use App\Models\DailyRecord;
use App\Models\Event;
use App\Models\Newsletter;
use App\Services\PuppeteerPdfService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'section' => 'required|string|in:greeting,event_details,weekly_reports,event_results,others,weekly_intro,elementary_report,junior_report,event_calendar,requests',
            'context' => 'nullable|string',
        ]);

        $section = $request->section;
        $context = $request->context ?? '';

        $sectionLabels = [
            'greeting'           => 'あいさつ文',
            'event_details'      => '行事の詳細',
            'weekly_reports'     => '週報',
            'event_results'      => '行事の結果報告',
            'others'             => 'その他のお知らせ',
            'weekly_intro'       => '曜日別活動紹介',
            'elementary_report'  => '小学生の活動報告',
            'junior_report'      => '中学生の活動報告',
            'event_calendar'     => 'イベントカレンダー',
            'requests'           => '施設からのお願い',
        ];

        $sectionLabel = $sectionLabels[$section] ?? $section;

        // Build rich context from database
        $richContext = $this->buildRichContext($newsletter);

        // Build section-specific prompt for legacy-compatible sections
        $sectionPrompt = $this->buildSectionPrompt($section, $newsletter, $sectionLabel, $richContext, $context);

        // event_calendar returns formatted text directly (no AI needed, legacy compat)
        if ($section === 'event_calendar') {
            return response()->json([
                'success' => true,
                'data'    => [
                    'section' => $section,
                    'content' => $sectionPrompt,
                ],
            ]);
        }

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧で、参加したくなるような魅力的な文章を書きます。専門用語は避け、分かりやすい表現を心がけます。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $sectionPrompt,
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 1500,
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
     * セクション別のプロンプトを構築する（旧アプリ準拠）
     */
    private function buildSectionPrompt(string $section, Newsletter $newsletter, string $sectionLabel, string $richContext, string $context): string
    {
        $classroomId = $newsletter->classroom_id;
        $newsletter->loadMissing('classroom');
        $classroomName = $newsletter->classroom?->classroom_name ?? '教室';
        $year = $newsletter->year;
        $month = $newsletter->month;

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $contextSuffix = $context ? "\n【スタッフからの補足情報】{$context}" : '';

        switch ($section) {
            case 'weekly_intro':
                // 曜日別活動紹介 - 支援案を曜日別にグループ化してプロンプト生成
                $plans = ActivitySupportPlan::where('classroom_id', $classroomId)
                    ->whereNotNull('day_of_week')
                    ->orderBy('day_of_week')
                    ->get(['activity_name', 'activity_purpose', 'activity_content', 'five_domains_consideration', 'day_of_week']);

                $dayMapping = ['monday' => '月', 'tuesday' => '火', 'wednesday' => '水', 'thursday' => '木', 'friday' => '金', 'saturday' => '土', 'sunday' => '日'];
                $plansByDay = [];
                foreach ($plans as $plan) {
                    $days = explode(',', $plan->day_of_week);
                    foreach ($days as $day) {
                        $dayName = $dayMapping[trim($day)] ?? trim($day);
                        $plansByDay[$dayName][] = $plan;
                    }
                }

                $plansList = '';
                $dayOrder = ['月', '火', '水', '木', '金', '土'];
                foreach ($dayOrder as $day) {
                    if (empty($plansByDay[$day])) continue;
                    $plansList .= "■ {$day}曜日\n";
                    foreach ($plansByDay[$day] as $p) {
                        $plansList .= "【{$p->activity_name}】\n";
                        if ($p->activity_purpose) $plansList .= "目的: {$p->activity_purpose}\n";
                        if ($p->activity_content) $plansList .= "内容: {$p->activity_content}\n";
                        $plansList .= "\n";
                    }
                }

                if (empty($plansList)) {
                    return "{$year}年{$month}月号の「{$sectionLabel}」を生成してください。支援案データがないため、一般的な曜日別活動紹介を作成してください。{$contextSuffix}";
                }

                return <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下の曜日別の支援案（活動計画）を、「まだその曜日に参加していない生徒と保護者」に向けて、参加したくなるような魅力的な紹介文を作成してください。

{$plansList}

【要件】
- 各曜日ごとに「■ ○曜日」の見出しをつける
- その曜日に参加することで得られる体験・成長を具体的に伝える
- 「ぜひ○曜日も来てみてください」と思わせる内容
- 各曜日300字程度
- 「です・ます」調で丁寧かつ温かい表現
- 専門用語は避け、分かりやすい言葉で
{$contextSuffix}

文章のみを出力してください。
PROMPT;

            case 'elementary_report':
            case 'junior_report':
                // 学年別活動報告 - 連絡帳データと支援案から生成
                $gradeLabel = $section === 'elementary_report' ? '小学生' : '中学生・高校生';

                $dailyRecords = DailyRecord::where('classroom_id', $classroomId)
                    ->whereBetween('record_date', [$periodStart, $periodEnd])
                    ->orderBy('record_date')
                    ->get(['record_date', 'activity_name', 'common_activity']);

                $activitySummary = '';
                foreach ($dailyRecords as $record) {
                    if ($record->common_activity) {
                        $activitySummary .= "【{$record->record_date->format('Y-m-d')} {$record->activity_name}】\n{$record->common_activity}\n\n";
                    }
                }

                $supportPlans = ActivitySupportPlan::where('classroom_id', $classroomId)
                    ->get(['activity_name', 'activity_purpose']);
                $planInfo = '';
                foreach ($supportPlans as $plan) {
                    $planInfo .= "・{$plan->activity_name}";
                    if ($plan->activity_purpose) $planInfo .= "（{$plan->activity_purpose}）";
                    $planInfo .= "\n";
                }

                if (empty($activitySummary)) {
                    return "{$year}年{$month}月号の「{$gradeLabel}の活動報告」を生成してください。活動記録がないため、一般的な内容で作成してください。{$contextSuffix}";
                }

                return <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のデータを参考に、{$gradeLabel}向けの活動報告を作成してください。

【報告期間】
{$periodStart->format('Y-m-d')} ～ {$periodEnd->format('Y-m-d')}

【期間中の活動記録（連絡帳より）】
{$activitySummary}

【関連する支援案】
{$planInfo}

【要件】
- {$gradeLabel}の子どもたちがどのような活動に取り組んだかをまとめる
- 子どもたちの様子、成長、楽しんでいるエピソードを具体的に
- 保護者が読んで嬉しくなるような温かい文章
- 300〜500字程度
- 「です・ます」調で丁寧な表現
- 活動内容を箇条書きではなく、流れのある文章で
{$contextSuffix}

文章のみを出力してください（見出しは不要です）。
PROMPT;

            case 'event_calendar':
                // イベントカレンダー - 予定イベントから生成
                $events = Event::where('classroom_id', $classroomId)
                    ->where('event_date', '>=', $periodStart)
                    ->where('event_date', '<=', $periodEnd)
                    ->orderBy('event_date')
                    ->get(['event_date', 'event_name']);

                if ($events->isEmpty()) {
                    return "{$year}年{$month}月号の「イベントカレンダー」を生成してください。予定イベントがありません。{$contextSuffix}";
                }

                $daysOfWeek = ['日', '月', '火', '水', '木', '金', '土'];
                $calendar = '';
                foreach ($events as $event) {
                    $date = $event->event_date;
                    $day = $date->format('j');
                    $dayOfWeek = $daysOfWeek[$date->dayOfWeek];
                    $calendar .= "{$day}日({$dayOfWeek}) {$event->event_name}\n";
                }

                return $calendar;

            case 'requests':
                // 施設からのお願い - テンプレートまたはAI生成
                return <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
{$year}年{$month}月号の「施設からのお願い」セクションを作成してください。

{$richContext}

【要件】
- 保護者への日常的なお願い事項（持ち物、送迎、連絡事項など）
- 季節に応じた注意事項
- 温かく丁寧な表現で、お願いとして伝える
- 200〜300字程度
- 「です・ます」調
{$contextSuffix}

文章のみを出力してください。
PROMPT;

            default:
                // 既存セクション（greeting, event_details, weekly_reports, event_results, others）
                return "{$year}年{$month}月号のお便り「{$newsletter->title}」の"
                    . "「{$sectionLabel}」セクションの文章を生成してください。\n\n"
                    . ($richContext ? "{$richContext}\n\n" : '')
                    . ($context ? "【スタッフからの補足情報】{$context}\n\n" : '')
                    . "上記の情報を踏まえて適切な文章を生成してください。HTMLタグは使わず、プレーンテキストで出力してください。";
        }
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
     * お便り PDF をダウンロード
     */
    public function pdf(Request $request, Newsletter $newsletter)
    {
        $newsletter->load(['classroom', 'creator:id,full_name']);

        $filename = 'newsletter_' . $newsletter->year . '_' . $newsletter->month . '_' . ($newsletter->title ?? $newsletter->id) . '.pdf';

        return PuppeteerPdfService::download('pdf.newsletter', [
            'newsletter' => $newsletter,
            'classroom'  => $newsletter->classroom,
        ], $filename);
    }

    /**
     * お便り PDF プレビューデータを返す（POST）
     * 保存前のデータからPDFプレビュー用のデータを生成する
     */
    public function pdfPreview(Request $request): JsonResponse
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

        $user = $request->user();

        // 保存せずにプレビュー用のデータを組み立て
        $previewData = array_merge($validated, [
            'id'            => null,
            'classroom_id'  => $user->classroom_id,
            'created_by'    => $user->id,
            'is_published'  => false,
            'preview_mode'  => true,
            'classroom'     => $user->classroom_id
                ? \App\Models\Classroom::find($user->classroom_id)
                : null,
            'creator'       => [
                'id'        => $user->id,
                'full_name' => $user->full_name,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $previewData,
        ]);
    }
}
