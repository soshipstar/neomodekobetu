<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationLog;
use App\Models\Classroom;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use App\Models\Newsletter;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Services\ServiceTypeRegistry;
use App\Services\StrengthsAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AiGenerationController extends Controller
{
    /**
     * 生徒の所属事業所から service_type を解決する。
     * 解決できない場合は after_school にフォールバック。
     */
    private function resolveServiceType(?int $classroomId): string
    {
        if (!$classroomId) {
            return ServiceTypeRegistry::AFTER_SCHOOL;
        }
        $type = Classroom::query()->where('id', $classroomId)->value('service_type');
        return ServiceTypeRegistry::isValid((string) $type)
            ? (string) $type
            : ServiceTypeRegistry::AFTER_SCHOOL;
    }

    /**
     * 個別支援計画書の内容をAI生成
     */
    public function generateSupportPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'  => 'required|exists:students,id',
            'context'     => 'nullable|string',
        ]);

        $student = Student::with(['interviews', 'dailyRecords.dailyRecord'])->findOrFail($validated['student_id']);

        // 過去の面接記録
        $interviews = $student->interviews()
            ->orderByDesc('interview_date')
            ->limit(5)
            ->get();

        $interviewText = $interviews->map(fn ($i) => "[{$i->interview_date}] {$i->content}")->implode("\n");

        // 過去の連絡帳記録
        $records = StudentRecord::where('student_id', $student->id)
            ->with('dailyRecord:id,record_date,activity_name')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // 新スキーマ（5 領域カラム）を使用。legacy の domain1/domain2 は存在しない。
        $domainLabels = [
            'health_life' => '健康・生活',
            'motor_sensory' => '運動・感覚',
            'cognitive_behavior' => '認知・行動',
            'language_communication' => '言語・コミュニケーション',
            'social_relations' => '人間関係・社会性',
        ];
        $recordsText = $records->map(function ($r) use ($domainLabels) {
            $date = $r->dailyRecord->record_date ?? '';
            $parts = [];
            foreach ($domainLabels as $col => $label) {
                if (!empty($r->{$col})) {
                    $parts[] = "{$label}: {$r->{$col}}";
                }
            }
            return "[{$date}] " . implode(' / ', $parts);
        })->filter(fn ($line) => trim($line) !== '[]')->implode("\n");

        // 過去の支援計画書
        $pastPlans = $student->supportPlans()
            ->with('details')
            ->orderByDesc('created_date')
            ->limit(2)
            ->get();

        $pastPlanText = $pastPlans->map(function ($p) {
            $details = $p->details->map(fn ($d) => "  - [{$d->category}] {$d->support_goal}")->implode("\n");
            return "[{$p->created_date}]\n{$details}";
        })->implode("\n\n");

        // 直近 6 ヶ月の強み(才能)チェック集計
        $strengthsAggregator = app(StrengthsAggregator::class);
        $strengthsSummary = $strengthsAggregator->aggregateForStudent(
            $student->id,
            Carbon::today()->subMonths(6),
            Carbon::today(),
        );
        $strengthsText = $strengthsAggregator->formatAsText($strengthsSummary);

        // サービス種別ごとの語彙・視点でプロンプトを組み立てる
        $serviceType = $this->resolveServiceType($student->classroom_id);
        $terms       = ServiceTypeRegistry::terms($serviceType);
        $facility    = ServiceTypeRegistry::label($serviceType);
        $strengthExample = ServiceTypeRegistry::strengthKeys($serviceType)[0] ?? '集中力';

        $systemPrompt = "あなたは{$facility}の{$terms['service_manager']}です。"
            . "個別支援計画書の作成を支援します。JSON形式のみで回答してください。\n\n"
            . "# 評価の視点\n"
            . ServiceTypeRegistry::aiServiceFocus($serviceType);

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role'    => 'user',
                        'content' => "以下の情報をもとに、個別支援計画書の内容を生成してください。\n\n"
                            . "【{$terms['client']}名】{$student->student_name}\n\n"
                            . "【面接記録】\n{$interviewText}\n\n"
                            . "【{$terms['diary']}記録】\n{$recordsText}\n\n"
                            . "{$strengthsText}\n\n"
                            . "【過去の支援計画書】\n{$pastPlanText}\n\n"
                            . ($validated['context'] ? "【追加情報】\n{$validated['context']}\n\n" : '')
                            . "以下を含むJSONを出力してください。details の各要素には任意で target_strength（強み項目名 / {$terms['diary']}の強みチェック10項目から選択）と target_strength_baseline (現在値 0-10) / target_strength_target (目標値 0-10) を含めることができます:\n"
                            . "{\n"
                            . "  \"life_intention\": \"本人の生活に対する意向\",\n"
                            . "  \"overall_policy\": \"総合的な援助の方針\",\n"
                            . "  \"long_term_goal\": \"長期目標\",\n"
                            . "  \"short_term_goal\": \"短期目標\",\n"
                            . "  \"details\": [{\"category\": \"分野\", \"sub_category\": \"サブ分野\", \"support_goal\": \"目標\", \"support_content\": \"支援内容\", \"target_strength\": \"{$strengthExample}\", \"target_strength_baseline\": 5, \"target_strength_target\": 7}]\n"
                            . "}",
                    ],
                ],
                'temperature'           => 0.5,
                'max_completion_tokens' => 3000,
            ]);

            $content = $response->choices[0]->message->content;

            // Markdownコードブロックを除去
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $content = trim($matches[1]);
            }

            $result = json_decode($content, true);

            // 生成ログ保存
            AiGenerationLog::create([
                'user_id'      => $request->user()->id,
                'model'        => 'gpt-4.1-mini',
                'prompt_type'  => 'support_plan',
                'input_tokens' => $response->usage->promptTokens ?? null,
                'output_tokens' => $response->usage->completionTokens ?? null,
                'student_id'   => $student->id,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result ?? $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * モニタリング評価をAI生成
     */
    public function generateMonitoring(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id'    => 'required|exists:individual_support_plans,id',
            'student_id' => 'required|exists:students,id',
            'detail_id'  => 'nullable|integer',
        ]);

        $plan = IndividualSupportPlan::with('details')->findOrFail($validated['plan_id']);
        $student = Student::findOrFail($validated['student_id']);

        // 過去6ヶ月の連絡帳データ
        $sixMonthsAgo = now()->subMonths(6)->toDateString();
        $records = StudentRecord::where('student_id', $student->id)
            ->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '>=', $sixMonthsAgo))
            ->with('dailyRecord:id,record_date,activity_name')
            ->get();

        // 新スキーマ（5 領域カラム）を使用。legacy の domain1/domain2 は存在しない。
        $domainLabels = [
            'health_life' => '健康・生活',
            'motor_sensory' => '運動・感覚',
            'cognitive_behavior' => '認知・行動',
            'language_communication' => '言語・コミュニケーション',
            'social_relations' => '人間関係・社会性',
        ];
        $recordsText = $records->map(function ($r) use ($domainLabels) {
            $date = $r->dailyRecord->record_date ?? '';
            $parts = [];
            foreach ($domainLabels as $col => $label) {
                if (!empty($r->{$col})) {
                    $parts[] = "{$label}: {$r->{$col}}";
                }
            }
            return "[{$date}] " . implode(' / ', $parts);
        })->filter(fn ($line) => trim($line) !== '[]')->implode("\n");

        $details = $plan->details;
        if ($validated['detail_id'] ?? null) {
            $details = $details->where('id', $validated['detail_id']);
        }

        $detailsText = $details->map(function ($d) {
            $line = "- [{$d->category}/{$d->sub_category}] 目標: {$d->support_goal} / 内容: {$d->support_content}";
            if (!empty($d->target_strength)) {
                $line .= " / 指標: {$d->target_strength} (現在 {$d->target_strength_baseline}→目標 {$d->target_strength_target})";
            }
            return $line;
        })->implode("\n");

        // 計画作成日 (なければ 6 ヶ月前) 〜 今日 の強み(才能)チェック集計
        $strengthsAggregator = app(StrengthsAggregator::class);
        $strengthsFrom = $plan->created_date ? Carbon::parse($plan->created_date) : Carbon::today()->subMonths(6);
        $strengthsSummary = $strengthsAggregator->aggregateForStudent(
            $student->id,
            $strengthsFrom,
            Carbon::today(),
        );
        $strengthsText = $strengthsAggregator->formatAsText($strengthsSummary);

        // サービス種別ごとの語彙・視点でプロンプトを組み立てる
        $serviceType = $this->resolveServiceType($student->classroom_id);
        $terms       = ServiceTypeRegistry::terms($serviceType);
        $facility    = ServiceTypeRegistry::label($serviceType);

        $systemPrompt = "あなたは{$facility}の{$terms['service_manager']}です。"
            . "モニタリング評価を生成します。JSON形式のみで回答してください。\n\n"
            . "# 評価の視点\n"
            . ServiceTypeRegistry::aiServiceFocus($serviceType);

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role'    => 'user',
                        'content' => "【{$terms['client']}名】{$student->student_name}\n\n"
                            . "【計画の目標・支援内容】\n{$detailsText}\n\n"
                            . "【過去6ヶ月の記録】\n{$recordsText}\n\n"
                            . "{$strengthsText}\n\n"
                            . "各目標に対して評価してください。指標 (target_strength) が設定されている目標は、強みチェックの推移を踏まえて達成状況を判断してください。出力形式:\n"
                            . "{\"evaluations\": {\"<detail_id>\": {\"achievement_status\": \"達成/進行中/未着手/継続中/見直し必要\", \"monitoring_comment\": \"150〜200字の評価\"}, ...}}",
                    ],
                ],
                'temperature'           => 0.5,
                'max_completion_tokens' => 2000,
            ]);

            $content = $response->choices[0]->message->content;
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $content = trim($matches[1]);
            }

            $result = json_decode($content, true);

            AiGenerationLog::create([
                'user_id'      => $request->user()->id,
                'model'        => 'gpt-4.1-mini',
                'prompt_type'  => 'monitoring',
                'input_tokens' => $response->usage->promptTokens ?? null,
                'output_tokens' => $response->usage->completionTokens ?? null,
                'student_id'   => $student->id,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result ?? $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * お便り（ニュースレター）セクションをAI生成
     */
    public function generateNewsletter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'newsletter_id' => 'nullable|exists:newsletters,id',
            'section'        => 'required|string',
            'context'        => 'nullable|string',
            'year'           => 'required|integer',
            'month'          => 'required|integer',
            'title'          => 'nullable|string',
        ]);

        $sectionLabels = [
            'greeting'       => 'あいさつ文',
            'event_details'  => '行事の詳細説明',
            'weekly_reports' => '週報・クラスの様子',
            'event_results'  => '行事の結果報告',
            'others'         => 'その他のお知らせ',
        ];

        $label = $sectionLabels[$validated['section']] ?? $validated['section'];

        // ニュースレターは事業所単位で発行されるため、ログイン中のスタッフの
        // 事業所サービス種別から「施設の役割」と「読み手 (保護者/家族)」を切替える。
        $serviceType = $this->resolveServiceType($request->user()?->classroom_id);
        $terms       = ServiceTypeRegistry::terms($serviceType);

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => "{$terms['facility_role']}のスタッフとして、{$terms['guardian']}向けのお便りの文章を作成します。"
                            . "温かみがあり丁寧な表現を心がけてください。",
                    ],
                    [
                        'role'    => 'user',
                        'content' => "{$validated['year']}年{$validated['month']}月号のお便りの"
                            . "「{$label}」セクションの文章を生成してください。\n\n"
                            . ($validated['title'] ? "タイトル：{$validated['title']}\n" : '')
                            . ($validated['context'] ? "参考情報：{$validated['context']}\n" : '')
                            . "\nプレーンテキストで出力してください。",
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 1000,
            ]);

            $generatedText = $response->choices[0]->message->content;

            AiGenerationLog::create([
                'user_id'      => $request->user()->id,
                'model'        => 'gpt-4.1-mini',
                'prompt_type'  => 'newsletter',
                'input_tokens' => $response->usage->promptTokens ?? null,
                'output_tokens' => $response->usage->completionTokens ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'section' => $validated['section'],
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

}
