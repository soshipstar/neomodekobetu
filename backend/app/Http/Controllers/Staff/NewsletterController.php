<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use App\Models\ClassroomPhoto;
use App\Models\DailyRecord;
use App\Models\Event;
use App\Models\Newsletter;
use App\Models\NewsletterSetting;
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
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
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

    private const DATE_RULES = [
        'report_start_date'   => 'nullable|date',
        'report_end_date'     => 'nullable|date',
        'schedule_start_date' => 'nullable|date',
        'schedule_end_date'   => 'nullable|date',
    ];

    /**
     * お便りを新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
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
        ], self::DATE_RULES));

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
        $validated = $request->validate(array_merge([
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
        ], self::DATE_RULES));

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

    // =========================================================================
    // AI 生成
    // =========================================================================

    /**
     * AI でお便り内容を生成（単一セクション）
     */
    public function generateAi(Request $request, Newsletter $newsletter): JsonResponse
    {
        $request->validate([
            'section' => 'required|string|in:greeting,event_details,weekly_reports,event_results,others,weekly_intro,elementary_report,junior_report,event_calendar,requests',
            'context' => 'nullable|string',
        ]);

        $section = $request->section;
        $context = $request->context ?? '';

        $settings = $this->getSettings($newsletter->classroom_id);
        $content = $this->generateSection($section, $newsletter, $context, $settings);

        return response()->json([
            'success' => true,
            'data'    => [
                'section' => $section,
                'content' => $content,
            ],
        ]);
    }

    /**
     * 全セクション一括AI生成（旧アプリの「AIで通信を生成」相当）
     */
    public function generateAll(Request $request, Newsletter $newsletter): JsonResponse
    {
        $context = $request->input('context', '');
        $settings = $this->getSettings($newsletter->classroom_id);

        $sections = [
            'greeting', 'event_calendar', 'event_details',
            'weekly_reports', 'weekly_intro', 'event_results',
            'elementary_report', 'junior_report', 'requests', 'others',
        ];

        $result = [];
        $usedPhotoIds = [];
        foreach ($sections as $section) {
            $result[$section] = $this->generateSection($section, $newsletter, $context, $settings, $usedPhotoIds);
        }

        // DB にも保存
        $newsletter->update($result);

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => '全セクションのAI生成が完了しました。',
        ]);
    }

    // =========================================================================
    // 生成ロジック（旧アプリ準拠）
    // =========================================================================

    /**
     * 施設通信設定を取得
     */
    private function getSettings(?int $classroomId): array
    {
        if (!$classroomId) {
            return [];
        }

        $setting = NewsletterSetting::where('classroom_id', $classroomId)->first();
        if (!$setting) {
            return [];
        }

        return array_merge(
            $setting->display_settings ?? [],
            $setting->ai_instructions ?? [],
            [
                'calendar_format'  => $setting->calendar_format ?? 'list',
                'default_requests' => $setting->default_requests ?? '',
                'default_others'   => $setting->default_others ?? '',
            ],
        );
    }

    /**
     * 1 セクションを生成
     */
    private function generateSection(string $section, Newsletter $newsletter, string $context, array $settings, array &$usedPhotoIds = []): string
    {
        $classroomId = $newsletter->classroom_id;
        $newsletter->loadMissing('classroom');
        $classroomName = $newsletter->classroom?->classroom_name ?? '教室';
        $year = $newsletter->year;
        $month = $newsletter->month;

        // 日付範囲の算出（旧アプリと同様に report / schedule 期間を使い分け）
        $reportStart = $newsletter->report_start_date
            ?? Carbon::create($year, $month, 1)->startOfMonth();
        $reportEnd = $newsletter->report_end_date
            ?? Carbon::create($year, $month, 1)->endOfMonth();
        $scheduleStart = $newsletter->schedule_start_date
            ?? Carbon::create($year, $month, 1)->startOfMonth();
        $scheduleEnd = $newsletter->schedule_end_date
            ?? Carbon::create($year, $month, 1)->endOfMonth();

        // 支援案データを取得
        $supportPlans = $classroomId
            ? ActivitySupportPlan::where('classroom_id', $classroomId)
                ->orderBy('day_of_week')
                ->orderBy('activity_name')
                ->get()
            : collect();

        $normalPlans = $supportPlans->filter(fn ($p) => ($p->plan_type ?? 'normal') === 'normal');
        $eventPlans = $supportPlans->filter(fn ($p) => ($p->plan_type ?? 'normal') === 'event');

        // セクション別のカスタム指示
        $instructionKey = match ($section) {
            'greeting'          => 'greeting_instructions',
            'event_details'     => 'event_details_instructions',
            'weekly_reports'    => 'weekly_reports_instructions',
            'weekly_intro'      => 'weekly_intro_instructions',
            'event_results'     => 'event_results_instructions',
            'elementary_report' => 'elementary_report_instructions',
            'junior_report'     => 'junior_report_instructions',
            default             => null,
        };
        $customInstructions = ($instructionKey && !empty($settings[$instructionKey]))
            ? "\n【カスタム指示】{$settings[$instructionKey]}"
            : '';
        $contextSuffix = $context ? "\n【スタッフからの補足情報】{$context}" : '';

        switch ($section) {
            // -----------------------------------------------------------------
            // あいさつ文（旧アプリ準拠: 季節感のある挨拶、150〜200文字）
            // -----------------------------------------------------------------
            case 'greeting':
                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
{$year}年{$month}月号のあいさつ文を作成してください。

【要件】
- {$month}月の季節感を盛り込む
- 保護者への感謝と子どもたちの成長への期待を伝える
- 150〜200文字程度
- 「です・ます」調で温かく丁寧な表現
- 施設名「{$classroomName}」を冒頭で使用{$customInstructions}{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // イベントカレンダー（AI不要、カレンダー表＋イベント一覧）
            // -----------------------------------------------------------------
            case 'event_calendar':
                $events = $classroomId
                    ? Event::where('classroom_id', $classroomId)
                        ->whereBetween('event_date', [$scheduleStart, $scheduleEnd])
                        ->orderBy('event_date')
                        ->get(['event_date', 'event_name'])
                    : collect();

                $text = $this->buildCalendarTable($year, $month, $events);
                break;

            // -----------------------------------------------------------------
            // 行事の詳細（予定期間のイベント詳細を生成）
            // -----------------------------------------------------------------
            case 'event_details':
                $events = $classroomId
                    ? Event::where('classroom_id', $classroomId)
                        ->whereBetween('event_date', [$scheduleStart, $scheduleEnd])
                        ->orderBy('event_date')
                        ->get()
                    : collect();

                if ($events->isEmpty()) {
                    $text = "今月の行事予定はありません。"; break;
                }

                $eventList = '';
                foreach ($events as $e) {
                    $eventList .= "・{$e->event_date->format('n/j')} {$e->event_name}";
                    if ($e->event_description) $eventList .= "（{$e->event_description}）";
                    $eventList .= "\n";
                }

                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のイベント予定について、保護者に向けた魅力的な紹介文を作成してください。

【予定イベント】
{$eventList}

【要件】
- 各イベントについて150〜200文字程度で説明
- 参加者が楽しみに思える内容
- 持ち物や注意事項があれば含める
- 「です・ます」調で温かい表現{$customInstructions}{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // 活動の様子（報告期間の連絡帳＋通常支援案ベース）
            // -----------------------------------------------------------------
            case 'weekly_reports':
                $dailyRecords = $classroomId
                    ? DailyRecord::where('classroom_id', $classroomId)
                        ->whereBetween('record_date', [$reportStart, $reportEnd])
                        ->orderBy('record_date')
                        ->get(['record_date', 'activity_name', 'common_activity'])
                    : collect();

                $activitySummary = '';
                foreach ($dailyRecords as $r) {
                    $detail = collect([$r->activity_name, $r->common_activity])->filter()->implode(' - ');
                    if ($detail) {
                        $activitySummary .= "- {$r->record_date->format('n/j')}: {$detail}\n";
                    }
                }

                $planInfo = '';
                foreach ($normalPlans as $p) {
                    $planInfo .= "・{$p->activity_name}";
                    if ($p->activity_purpose) $planInfo .= "（{$p->activity_purpose}）";
                    $planInfo .= "\n";
                }

                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のデータを参考に、{$year}年{$month}月の「活動の様子」をまとめてください。

【報告期間の活動記録】
{$activitySummary}

【関連する支援案（通常活動）】
{$planInfo}

【要件】
- 活動内容と子どもたちの様子を具体的に伝える
- 保護者が読んで嬉しくなるような温かい文章
- 500〜800文字程度
- 「です・ます」調で丁寧な表現
- 支援案の目的・ねらいを踏まえた記述{$customInstructions}{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // 曜日別活動紹介（支援案を曜日別にグループ化）
            // -----------------------------------------------------------------
            case 'weekly_intro':
                $dayMapping = ['monday' => '月', 'tuesday' => '火', 'wednesday' => '水', 'thursday' => '木', 'friday' => '金', 'saturday' => '土', 'sunday' => '日'];
                $plansByDay = [];
                foreach ($normalPlans as $plan) {
                    if (!$plan->day_of_week) continue;
                    $days = explode(',', $plan->day_of_week);
                    foreach ($days as $day) {
                        $dayName = $dayMapping[trim($day)] ?? trim($day);
                        $plansByDay[$dayName][] = $plan;
                    }
                }

                $plansList = '';
                foreach (['月', '火', '水', '木', '金', '土'] as $day) {
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
                    $text = "曜日別活動紹介の支援案データがありません。"; break;
                }

                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下の曜日別の支援案（活動計画）を、「まだその曜日に参加していない生徒と保護者」に向けて、参加したくなるような魅力的な紹介文を作成してください。

{$plansList}

【要件】
- 各曜日ごとに「■ ○曜日」の見出しをつける
- その曜日に参加することで得られる体験・成長を具体的に伝える
- 「ぜひ○曜日も来てみてください」と思わせる内容
- 各曜日300字程度
- 「です・ます」調で丁寧かつ温かい表現
- 専門用語は避け、分かりやすい言葉で{$customInstructions}{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // 行事の結果報告（報告期間の過去イベント＋イベント支援案）
            // -----------------------------------------------------------------
            case 'event_results':
                $pastEvents = $classroomId
                    ? Event::where('classroom_id', $classroomId)
                        ->whereBetween('event_date', [$reportStart, $reportEnd])
                        ->orderBy('event_date')
                        ->get()
                    : collect();

                if ($pastEvents->isEmpty() && $eventPlans->isEmpty()) {
                    $text = "報告期間中の行事はありません。"; break;
                }

                $eventList = '';
                foreach ($pastEvents as $e) {
                    $eventList .= "・{$e->event_date->format('n/j')} {$e->event_name}";
                    if ($e->staff_comment) $eventList .= "（{$e->staff_comment}）";
                    $eventList .= "\n";
                }

                $eventPlanInfo = '';
                foreach ($eventPlans as $p) {
                    $eventPlanInfo .= "・{$p->activity_name}";
                    if ($p->activity_purpose) $eventPlanInfo .= "（{$p->activity_purpose}）";
                    $eventPlanInfo .= "\n";
                }

                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のデータを参考に、行事の結果報告を作成してください。

【報告期間の行事】
{$eventList}

【イベント関連の支援案】
{$eventPlanInfo}

【要件】
- 各行事について200〜300文字程度で報告
- 子どもたちの反応や成長を具体的に
- 保護者への感謝を忘れずに
- 「です・ます」調で温かい表現{$customInstructions}{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // 小学生・中学生の活動報告
            // -----------------------------------------------------------------
            case 'elementary_report':
            case 'junior_report':
                $gradeLabel = $section === 'elementary_report' ? '小学生' : '中学生・高校生';

                $dailyRecords = $classroomId
                    ? DailyRecord::where('classroom_id', $classroomId)
                        ->whereBetween('record_date', [$reportStart, $reportEnd])
                        ->orderBy('record_date')
                        ->get(['record_date', 'activity_name', 'common_activity'])
                    : collect();

                $activitySummary = '';
                foreach ($dailyRecords as $r) {
                    if ($r->common_activity) {
                        $activitySummary .= "【{$r->record_date->format('Y-m-d')} {$r->activity_name}】\n{$r->common_activity}\n\n";
                    }
                }

                $planInfo = '';
                foreach ($supportPlans as $p) {
                    $planInfo .= "・{$p->activity_name}";
                    if ($p->activity_purpose) $planInfo .= "（{$p->activity_purpose}）";
                    $planInfo .= "\n";
                }

                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のデータを参考に、{$gradeLabel}向けの活動報告を作成してください。

【報告期間】
{$reportStart->format('Y-m-d')} ～ {$reportEnd->format('Y-m-d')}

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
- 活動内容を箇条書きではなく、流れのある文章で{$customInstructions}{$contextSuffix}

文章のみを出力してください（見出しは不要です）。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // 施設からのお願い（設定のデフォルト値を優先）
            // -----------------------------------------------------------------
            case 'requests':
                $default = $settings['default_requests'] ?? '';
                if (!empty($default)) {
                    $text = $default; break;
                }
                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
{$year}年{$month}月号の「施設からのお願い」セクションを作成してください。

【要件】
- 保護者への日常的なお願い事項（持ち物、送迎、連絡事項など）
- 季節に応じた注意事項
- 温かく丁寧な表現で、お願いとして伝える
- 200〜300字程度
- 「です・ます」調{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            // -----------------------------------------------------------------
            // その他（設定のデフォルト値を優先）
            // -----------------------------------------------------------------
            case 'others':
                $default = $settings['default_others'] ?? '';
                if (!empty($default)) {
                    $text = $default; break;
                }
                $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
{$year}年{$month}月号の「その他」セクションを作成してください。

【要件】
- 施設からの追加連絡事項
- 季節に応じた情報
- 温かく丁寧な表現
- 100〜200字程度
- 「です・ます」調{$contextSuffix}

文章のみを出力してください。
PROMPT;
                $text = $this->callAi($prompt); break;

            default:
                $text = '';
                break;
        }

        // 該当セクションに写真を自動引用（カレンダー・お願い事項を除く）
        $noPhotoSections = ['event_calendar', 'requests'];
        if (!in_array($section, $noPhotoSections)) {
            $text .= $this->findMatchingPhotos($section, $newsletter, $usedPhotoIds);
        }

        return $text;
    }

    /**
     * カレンダー表を生成（旧アプリ準拠のテキスト形式）
     */
    private function buildCalendarTable(int $year, int $month, $events): string
    {
        $daysOfWeek = ['日', '月', '火', '水', '木', '金', '土'];

        // イベントを日付でインデックス化
        $eventsByDay = [];
        foreach ($events as $event) {
            $day = (int) $event->event_date->format('j');
            $eventsByDay[$day][] = $event->event_name;
        }

        // 月の情報
        $firstDay = Carbon::create($year, $month, 1);
        $daysInMonth = $firstDay->daysInMonth;
        $startWeekday = $firstDay->dayOfWeek; // 0=Sun

        // 祝日（簡易版）
        $holidays = $this->getJapaneseHolidays($year, $month);

        // テキスト形式のカレンダー表を生成
        $table = "【{$year}年{$month}月のカレンダー】\n\n";
        $table .= "┌────┬────┬────┬────┬────┬────┬────┐\n";
        $table .= "│ 日 │ 月 │ 火 │ 水 │ 木 │ 金 │ 土 │\n";
        $table .= "├────┼────┼────┼────┼────┼────┼────┤\n";

        $currentDay = 1;
        $weekRow = '';

        // 最初の週の空白セル
        for ($i = 0; $i < $startWeekday; $i++) {
            $weekRow .= '│    ';
        }

        while ($currentDay <= $daysInMonth) {
            $weekday = ($startWeekday + $currentDay - 1) % 7;
            $dayStr = str_pad((string) $currentDay, 2, ' ', STR_PAD_LEFT);

            $mark = '';
            if (isset($eventsByDay[$currentDay])) {
                $mark = '●';
            } elseif ($weekday === 0) {
                $mark = '★';
            } elseif ($weekday === 6) {
                $mark = '☆';
            } elseif (isset($holidays[$currentDay])) {
                $mark = '◎';
            }

            $weekRow .= '│' . $dayStr . $mark . ' ';

            if ($weekday === 6) {
                $table .= $weekRow . "│\n";
                if ($currentDay < $daysInMonth) {
                    $table .= "├────┼────┼────┼────┼────┼────┼────┤\n";
                }
                $weekRow = '';
            }

            $currentDay++;
        }

        // 最後の週の残りを埋める
        if ($weekRow !== '') {
            $remaining = 7 - (($startWeekday + $daysInMonth) % 7);
            if ($remaining < 7) {
                for ($i = 0; $i < $remaining; $i++) {
                    $weekRow .= '│    ';
                }
            }
            $table .= $weekRow . "│\n";
        }

        $table .= "└────┴────┴────┴────┴────┴────┴────┘\n\n";
        $table .= "【凡例】●:イベント ★:日曜 ☆:土曜 ◎:祝日\n\n";

        // イベント・祝日一覧
        $table .= "【今月の予定】\n";

        foreach ($holidays as $day => $name) {
            $wd = $daysOfWeek[Carbon::create($year, $month, $day)->dayOfWeek];
            $table .= "{$day}日({$wd}) {$name}【祝日】\n";
        }

        foreach ($events as $event) {
            $d = $event->event_date;
            $table .= "{$d->format('j')}日({$daysOfWeek[$d->dayOfWeek]}) {$event->event_name}\n";
        }

        return $table;
    }

    /**
     * 日本の祝日を取得（簡易版）
     */
    private function getJapaneseHolidays(int $year, int $month): array
    {
        $fixedHolidays = [
            1  => [1 => '元日', 13 => '成人の日'],
            2  => [11 => '建国記念の日', 23 => '天皇誕生日'],
            3  => [20 => '春分の日'],
            4  => [29 => '昭和の日'],
            5  => [3 => '憲法記念日', 4 => 'みどりの日', 5 => 'こどもの日'],
            7  => [21 => '海の日'],
            8  => [11 => '山の日'],
            9  => [15 => '敬老の日', 23 => '秋分の日'],
            10 => [13 => 'スポーツの日'],
            11 => [3 => '文化の日', 23 => '勤労感謝の日'],
        ];

        return $fixedHolidays[$month] ?? [];
    }

    /**
     * OpenAI API を呼び出す
     */
    private function callAi(string $prompt): string
    {
        try {
            $apiKey = config('services.openai.api_key');
            if (!$apiKey) {
                return 'OpenAI APIキーが設定されていません。';
            }
            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧で、参加したくなるような魅力的な文章を書きます。専門用語は避け、分かりやすい表現を心がけます。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 1500,
            ]);

            return $response->choices[0]->message->content ?? '';
        } catch (\Exception $e) {
            \Log::error('Newsletter AI generation failed: ' . $e->getMessage());
            return "AI生成に失敗しました: {$e->getMessage()}";
        }
    }

    /**
     * セクションに該当する写真を検索し、マークダウン画像として返す。
     * 同じ写真が複数セクションに重複しないよう $usedPhotoIds で管理。
     * 1セクションあたり最大3枚。
     */
    private function findMatchingPhotos(string $section, Newsletter $newsletter, array &$usedPhotoIds = []): string
    {
        $classroomId = $newsletter->classroom_id;
        if (!$classroomId) return '';

        $reportStart = $newsletter->report_start_date
            ?? Carbon::create($newsletter->year, $newsletter->month, 1)->startOfMonth();
        $reportEnd = $newsletter->report_end_date
            ?? Carbon::create($newsletter->year, $newsletter->month, 1)->endOfMonth();

        $query = ClassroomPhoto::where('classroom_id', $classroomId)
            ->whereBetween('activity_date', [$reportStart, $reportEnd])
            ->orderBy('activity_date');

        // 既に使用済みの写真を除外
        if (!empty($usedPhotoIds)) {
            $query->whereNotIn('id', $usedPhotoIds);
        }

        // セクション別のフィルタ条件
        switch ($section) {
            case 'elementary_report':
                $query->where('grade_level', 'elementary');
                break;
            case 'junior_report':
                $query->whereIn('grade_level', ['junior_high', 'high_school']);
                break;
            case 'weekly_intro':
                break;
            case 'event_details':
            case 'event_results':
                $query->where(function ($q) {
                    $q->whereHas('activityTag', function ($tq) {
                        $tq->where('tag_name', 'like', '%イベント%');
                    })->orWhereNull('activity_tag_id');
                });
                break;
            default:
                break;
        }

        $photos = $query->limit(3)->get();
        if ($photos->isEmpty()) return '';

        // 使用済みIDに追加
        foreach ($photos as $p) {
            $usedPhotoIds[] = $p->id;
        }

        $lines = [];
        foreach ($photos as $p) {
            $alt = $p->activity_description ?? '活動写真';
            $lines[] = "![{$alt}]({$p->url})";
        }

        return "\n\n" . implode("\n", $lines);
    }

    // =========================================================================
    // 配信・PDF
    // =========================================================================

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
     * お便り Word (.doc) をダウンロード
     */
    public function word(Request $request, Newsletter $newsletter)
    {
        $newsletter->load(['classroom', 'creator:id,full_name']);

        $html = view('word.newsletter', [
            'newsletter' => $newsletter,
            'classroom'  => $newsletter->classroom,
        ])->render();

        $filename = $newsletter->year . '年' . $newsletter->month . '月_' . ($newsletter->classroom?->classroom_name ?? '施設') . '通信.html';

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * お便り PDF プレビューデータを返す（POST）
     */
    public function pdfPreview(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
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
        ], self::DATE_RULES));

        $user = $request->user();

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
