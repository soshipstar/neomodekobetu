<?php

namespace App\Services;

use App\Models\AiGenerationLog;
use App\Models\Classroom;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Support\PiiMasker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
class AiGenerationService
{
    /**
     * Generate individual support plan content using GPT-5.
     *
     * @param  Student  $student
     * @param  array  $context  Additional context (e.g., previous plans, daily records)
     * @return array  Generated plan fields: life_intention, overall_policy, long_term_goal, short_term_goal, details[]
     */
    public function generateSupportPlan(Student $student, array $context = []): array
    {
        $student->load(['classroom', 'guardian', 'dailyRecords.studentRecords', 'supportPlans.details']);

        // 観点5 プライバシー保護: 外部AIへ送る前に児童・保護者の氏名を仮名化する。
        $masker = PiiMasker::forStudent($student);
        $prompt = $masker->mask($this->buildSupportPlanPrompt($student, $context));

        $startTime = microtime(true);

        $systemPrompt = '障害児通所支援の個別支援計画書を作成する専門家AIアシスタントです。'
            . '日本の児童福祉法に基づく放課後等デイサービスの計画を作成します。'
            . 'JSON形式で回答してください。';
        $temperature = 0.7;
        $maxTokens = 4000;

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-2026-03-05'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $temperature,
            'max_completion_tokens' => $maxTokens,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        // ログにはマスク済(=外部送信したのと同じ)入出力を保存し、実名をログに残さない。
        $this->logGeneration('support_plan', $response, $prompt, $content, $durationMs, [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'system_prompt' => $systemPrompt,
            'parameters' => ['response_format' => 'json_object', 'referenced' => ['student_id' => $student->id]],
        ]);

        // 呼び出し側(職員の下書き)へ返すときだけ実名へ復元する。
        return $masker->unmaskArray($content);
    }

    /**
     * Generate monitoring report content using GPT-5.
     *
     * @param  MonitoringRecord  $record
     * @return array  Generated monitoring fields: overall_comment, details[]
     */
    public function generateMonitoringReport(MonitoringRecord $record): array
    {
        $record->load(['plan.details', 'student.dailyRecords.studentRecords']);

        // 観点5 プライバシー保護: 外部AIへ送る前に児童・保護者の氏名を仮名化する。
        $masker = $record->student ? PiiMasker::forStudent($record->student) : new PiiMasker();
        $prompt = $masker->mask($this->buildMonitoringPrompt($record));

        $startTime = microtime(true);

        $systemPrompt = '障害児通所支援のモニタリング報告書を作成する専門家AIアシスタントです。'
            . '支援計画の達成度を評価し、今後の方針を提案します。'
            . 'JSON形式で回答してください。';
        $temperature = 0.7;
        $maxTokens = 3000;

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-2026-03-05'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $temperature,
            'max_completion_tokens' => $maxTokens,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        $this->logGeneration('monitoring_report', $response, $prompt, $content, $durationMs, [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'system_prompt' => $systemPrompt,
            'parameters' => ['response_format' => 'json_object', 'referenced' => [
                'student_id' => $record->student_id,
                'plan_id' => $record->plan_id,
                'monitoring_record_id' => $record->id,
            ]],
        ]);

        return $masker->unmaskArray($content);
    }

    /**
     * Generate newsletter content for a classroom's monthly newsletter.
     *
     * @param  Classroom  $classroom
     * @param  int  $year
     * @param  int  $month
     * @return array  Generated newsletter fields: title, greeting, event_details, weekly_reports, etc.
     */
    public function generateNewsletter(Classroom $classroom, int $year, int $month): array
    {
        $classroom->load(['events', 'weeklyPlans']);

        $prompt = $this->buildNewsletterPrompt($classroom, $year, $month);

        $startTime = microtime(true);

        $systemPrompt = '放課後等デイサービスの教室だよりを作成するAIアシスタントです。'
            . '保護者向けに明るく温かい文体で作成してください。'
            . 'JSON形式で回答してください。';
        $temperature = 0.8;
        $maxTokens = 3000;

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-2026-03-05'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $temperature,
            'max_completion_tokens' => $maxTokens,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        $this->logGeneration('newsletter', $response, $prompt, $content, $durationMs, [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'system_prompt' => $systemPrompt,
            'parameters' => ['response_format' => 'json_object', 'referenced' => [
                'classroom_id' => $classroom->id, 'year' => $year, 'month' => $month,
            ]],
        ]);

        return $content;
    }

    /**
     * Generate self-evaluation summary (別紙3) using GPT.
     *
     * @param  array  $guardianSummary  保護者評価集計データ
     * @param  array  $staffSummary  スタッフ評価集計データ
     * @param  string  $classroomName  事業所名
     * @return array  Generated: strengths[] and weaknesses[] (each with current_status, issues, improvement_plan)
     */
    public function generateSelfEvaluationSummary(array $guardianSummary, array $staffSummary, string $classroomName): array
    {
        $prompt = $this->buildSelfEvaluationPrompt($guardianSummary, $staffSummary, $classroomName);

        $startTime = microtime(true);

        $systemPrompt = '放課後等デイサービスの事業所における自己評価結果（別紙3）を作成するAIアシスタントです。'
            . '保護者評価と従業者評価の集計結果から、事業所の強みと弱みを分析してください。'
            . 'JSON形式で回答してください。';
        $temperature = 0.7;
        $maxTokens = 3000;

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY"));
        $client = \OpenAI::client($apiKey);
        $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-2026-03-05'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $temperature,
            'max_completion_tokens' => $maxTokens,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        $this->logGeneration('self_evaluation_summary', $response, $prompt, $content, $durationMs, [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'system_prompt' => $systemPrompt,
            'parameters' => ['response_format' => 'json_object', 'referenced' => ['classroom_name' => $classroomName]],
        ]);

        return $content;
    }

    /**
     * テキストからベクトル埋め込み (float配列) を生成する。
     *
     * vector_embeddings.embedding は vector(1536) のため、1536次元を返す
     * text-embedding-3-small (config: services.openai.embedding_model) を使用する。
     * EmbeddingService::embed() / VectorSearchService から呼ばれる。
     *
     * @param  string  $text
     * @return array<int, float>  埋め込みベクトル
     */
    public function generateEmbedding(string $text): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $client = \OpenAI::client($apiKey);

        $response = $client->embeddings()->create([
            'model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildSupportPlanPrompt(Student $student, array $context): string
    {
        $recentRecords = $student->dailyRecords()
            ->with('studentRecords')
            ->orderByDesc('record_date')
            ->limit(30)
            ->get();

        $previousPlan = $student->supportPlans()
            ->with('details')
            ->orderByDesc('created_date')
            ->first();

        $prompt = "以下の情報をもとに個別支援計画書を作成してください。\n\n";
        $prompt .= "【児童名】{$student->student_name}\n";
        $prompt .= "【学年】{$student->grade_level}\n";
        $prompt .= "【教室】{$student->classroom->classroom_name}\n\n";

        if ($previousPlan) {
            $prompt .= "【前回の計画】\n";
            $prompt .= "・本人の願い: {$previousPlan->life_intention}\n";
            $prompt .= "・支援方針: {$previousPlan->overall_policy}\n";
            $prompt .= "・長期目標: {$previousPlan->long_term_goal}\n";
            $prompt .= "・短期目標: {$previousPlan->short_term_goal}\n\n";
        }

        if ($recentRecords->isNotEmpty()) {
            $prompt .= "【最近の活動記録（直近30件）】\n";
            foreach ($recentRecords as $record) {
                $prompt .= "- {$record->record_date->format('Y/m/d')}: {$record->activity_name}\n";
                foreach ($record->studentRecords->where('student_id', $student->id) as $sr) {
                    if ($sr->notes) {
                        $prompt .= "  備考: {$sr->notes}\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        if (! empty($context['additional_notes'])) {
            $prompt .= "【追加情報】\n{$context['additional_notes']}\n\n";
        }

        $prompt .= "以下のJSON形式で出力してください:\n";
        $prompt .= "{\n";
        $prompt .= '  "life_intention": "本人の願い・保護者の願い",' . "\n";
        $prompt .= '  "overall_policy": "総合的な支援方針",' . "\n";
        $prompt .= '  "long_term_goal": "長期目標",' . "\n";
        $prompt .= '  "short_term_goal": "短期目標",' . "\n";
        $prompt .= '  "details": [' . "\n";
        $prompt .= '    {"domain": "健康・生活", "current_status": "現在の状況", "goal": "目標", "support_content": "支援内容"},' . "\n";
        $prompt .= '    {"domain": "運動・感覚", "current_status": "...", "goal": "...", "support_content": "..."},' . "\n";
        $prompt .= '    {"domain": "認知・行動", "current_status": "...", "goal": "...", "support_content": "..."},' . "\n";
        $prompt .= '    {"domain": "言語・コミュニケーション", "current_status": "...", "goal": "...", "support_content": "..."},' . "\n";
        $prompt .= '    {"domain": "人間関係・社会性", "current_status": "...", "goal": "...", "support_content": "..."}' . "\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    private function buildMonitoringPrompt(MonitoringRecord $record): string
    {
        $plan = $record->plan;
        $student = $record->student;

        $prompt = "以下の支援計画に対するモニタリング報告を作成してください。\n\n";
        $prompt .= "【児童名】{$student->student_name}\n";
        $prompt .= "【計画の目標】\n";
        $prompt .= "・長期目標: {$plan->long_term_goal}\n";
        $prompt .= "・短期目標: {$plan->short_term_goal}\n\n";

        $prompt .= "【計画の詳細】\n";
        foreach ($plan->details as $detail) {
            $prompt .= "- {$detail->domain}: 目標「{$detail->goal}」 支援内容「{$detail->support_content}」\n";
        }

        $prompt .= "\n以下のJSON形式で出力してください:\n";
        $prompt .= "{\n";
        $prompt .= '  "overall_comment": "総合所見",' . "\n";
        $prompt .= '  "short_term_goal_achievement": "達成/一部達成/未達成",' . "\n";
        $prompt .= '  "long_term_goal_achievement": "達成/一部達成/未達成",' . "\n";
        $prompt .= '  "details": [' . "\n";
        $prompt .= '    {"domain": "健康・生活", "achievement": "達成度", "comment": "所見", "next_action": "次期の方針"}' . "\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    private function buildNewsletterPrompt(Classroom $classroom, int $year, int $month): string
    {
        $prompt = "以下の情報をもとに{$year}年{$month}月の教室だよりを作成してください。\n\n";
        $prompt .= "【教室名】{$classroom->classroom_name}\n\n";

        $events = $classroom->events()
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->get();

        if ($events->isNotEmpty()) {
            $prompt .= "【今月のイベント】\n";
            foreach ($events as $event) {
                $prompt .= "- {$event->event_date->format('m/d')}: {$event->title}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "以下のJSON形式で出力してください:\n";
        $prompt .= "{\n";
        $prompt .= '  "title": "教室だよりのタイトル",' . "\n";
        $prompt .= '  "greeting": "挨拶文",' . "\n";
        $prompt .= '  "event_details": "イベント詳細",' . "\n";
        $prompt .= '  "weekly_reports": "週間報告",' . "\n";
        $prompt .= '  "requests": "お願い事項",' . "\n";
        $prompt .= '  "others": "その他"' . "\n";
        $prompt .= "}\n";

        return $prompt;
    }

    private function buildSelfEvaluationPrompt(array $guardianSummary, array $staffSummary, string $classroomName): string
    {
        $guardianLines = [];
        foreach ($guardianSummary as $item) {
            $total = ($item->yes_count ?? 0) + ($item->neutral_count ?? 0) + ($item->no_count ?? 0);
            $pct = $total > 0 ? round(($item->yes_count / $total) * 100, 1) : 0;
            $guardianLines[] = "Q{$item->question_number}({$item->category}): {$item->question_text} → はい{$pct}%";
        }

        $staffLines = [];
        foreach ($staffSummary as $item) {
            $total = ($item->yes_count ?? 0) + ($item->neutral_count ?? 0) + ($item->no_count ?? 0);
            $pct = $total > 0 ? round(($item->yes_count / $total) * 100, 1) : 0;
            $staffLines[] = "Q{$item->question_number}({$item->category}): {$item->question_text} → はい{$pct}%";
        }

        return <<<PROMPT
事業所名: {$classroomName}

以下は保護者評価と従業者（スタッフ）評価の集計結果です。
これを分析し、事業所の「強み」3つと「弱み」3つを特定してください。

【保護者評価】
{$this->joinLines($guardianLines)}

【従業者評価】
{$this->joinLines($staffLines)}

以下のJSON形式で回答してください:
{
  "strengths": [
    {
      "current_status": "工夫していることや意識的に行っている取組等",
      "improvement_plan": "さらに充実を図るための取組等"
    },
    // 計3項目
  ],
  "weaknesses": [
    {
      "issues": "事業所として考えている課題の要因等",
      "improvement_plan": "改善に向けて必要な取組や工夫が必要な点等"
    },
    // 計3項目
  ]
}

強みは「はい」の割合が高い項目から、弱みは低い項目から選んでください。
具体的な取組内容を記載してください。一般的な文言ではなく、質問内容に即した具体策を書いてください。
PROMPT;
    }

    private function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * 生成ログを記録する (観点10 検証可能性)。
     *
     * @param  array{temperature?:float,max_tokens?:int,system_prompt?:string,parameters?:array}  $meta
     */
    private function logGeneration(string $type, object $response, string $prompt, array $output, int $durationMs, array $meta = []): void
    {
        try {
            // finish_reason 等の応答メタも parameters に保存し再現性を高める
            $parameters = $meta['parameters'] ?? [];
            $finishReason = $response->choices[0]->finishReason ?? null;
            if ($finishReason) {
                $parameters['finish_reason'] = $finishReason;
            }

            AiGenerationLog::create([
                'user_id' => Auth::id(),
                'generation_type' => $type,
                'model' => $response->model ?? config('services.openai.model', 'gpt-5.4-2026-03-05'),
                'temperature' => $meta['temperature'] ?? null,
                'max_tokens' => $meta['max_tokens'] ?? null,
                'system_prompt' => $meta['system_prompt'] ?? null,
                'parameters' => $parameters ?: null,
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
