<?php

namespace App\Services;

use App\Models\AiGenerationLog;
use App\Models\Classroom;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Services\AiIdentityMasker;
use App\Services\AiPromptDirectives;
use App\Services\AiPromptSanitizer;
use App\Services\OpenAiClientFactory;
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

        // AISI R1 + R2 (2026-05-17): Sanitizer + Masker を 1 リクエスト 1 セット用意
        $sanitizer = new AiPromptSanitizer();
        $masker = new AiIdentityMasker();
        $this->registerStudentNames($masker, $student);

        $prompt = $this->buildSupportPlanPrompt($student, $context, $sanitizer, $masker);

        $startTime = microtime(true);

        // AISI R6 (2026-05-17): OpenAiClientFactory 経由で organization / ZDR を反映
        $client = OpenAiClientFactory::make();
        $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => AiPromptDirectives::systemBase($sanitizer)
                        . '障害児通所支援の個別支援計画書を作成する専門家AIアシスタントです。'
                        . '日本の児童福祉法に基づく放課後等デイサービスの計画を作成します。'
                        . 'JSON形式で回答してください。',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.7,
            'max_completion_tokens' => 4000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $maskedRaw = (string) ($response->choices[0]->message->content ?? '');

        // 漏洩検知 (system prompt や API キーが応答に紛れ込んでいないか)
        $maskedRaw = $sanitizer->postProcess($maskedRaw, [
            'generation_type' => 'support_plan',
            'student_id'      => $student->id,
        ]);

        // AISI R7 (2026-05-17): Moderation API による出力層フィルタ。
        // flagged 時は master_admin_audit_logs + Log::warning に記録するのみで、出力自体は維持する。
        // (HITL レビュー前提のため出力を破棄せず職員確認に任せる方針)
        self::recordModerationFlag(self::moderate($maskedRaw), [
            'generation_type' => 'support_plan',
            'student_id'      => $student->id,
        ]);

        $maskedDecoded = json_decode($maskedRaw, true) ?? [];

        // ログには「マスク後」のプロンプトと応答を保存 (実名が ai_generation_logs に残らない設計)
        $this->logGeneration('support_plan', $response, $prompt, $maskedDecoded, $durationMs);

        // 呼出元に返す値だけ unmask して実名を復元
        return $this->unmaskDecoded($masker, $maskedDecoded);
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

        // AISI R1 + R2 (2026-05-17)
        $sanitizer = new AiPromptSanitizer();
        $masker = new AiIdentityMasker();
        if ($record->student) {
            $this->registerStudentNames($masker, $record->student);
        }

        $prompt = $this->buildMonitoringPrompt($record, $sanitizer, $masker);

        $startTime = microtime(true);

        // AISI R6 (2026-05-17): OpenAiClientFactory 経由で organization / ZDR を反映
        $client = OpenAiClientFactory::make();
        $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => AiPromptDirectives::systemBase($sanitizer)
                        . '障害児通所支援のモニタリング報告書を作成する専門家AIアシスタントです。'
                        . '支援計画の達成度を評価し、今後の方針を提案します。'
                        . 'JSON形式で回答してください。',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.7,
            'max_completion_tokens' => 3000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $maskedRaw = (string) ($response->choices[0]->message->content ?? '');
        $maskedRaw = $sanitizer->postProcess($maskedRaw, [
            'generation_type' => 'monitoring_report',
            'record_id'       => $record->id,
        ]);

        // AISI R7: Moderation API による出力層フィルタ
        self::recordModerationFlag(self::moderate($maskedRaw), [
            'generation_type' => 'monitoring_report',
            'record_id'       => $record->id,
        ]);

        $maskedDecoded = json_decode($maskedRaw, true) ?? [];

        $this->logGeneration('monitoring_report', $response, $prompt, $maskedDecoded, $durationMs);

        return $this->unmaskDecoded($masker, $maskedDecoded);
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

        // AISI R1 + R2 (2026-05-17): 教室名のみ仮名化対象 (児童は出てこない想定だが念のため)
        $sanitizer = new AiPromptSanitizer();
        $masker = new AiIdentityMasker();
        if ($classroom->classroom_name) {
            $masker->register($classroom->classroom_name, 'classroom');
        }

        $prompt = $this->buildNewsletterPrompt($classroom, $year, $month, $sanitizer, $masker);

        $startTime = microtime(true);

        // AISI R6 (2026-05-17): OpenAiClientFactory 経由で organization / ZDR を反映
        $client = OpenAiClientFactory::make();
        $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => AiPromptDirectives::systemBase($sanitizer)
                        . '放課後等デイサービスの教室だよりを作成するAIアシスタントです。'
                        . '保護者向けに明るく温かい文体で作成してください。'
                        . 'JSON形式で回答してください。',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.8,
            'max_completion_tokens' => 3000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $maskedRaw = (string) ($response->choices[0]->message->content ?? '');
        $maskedRaw = $sanitizer->postProcess($maskedRaw, [
            'generation_type' => 'newsletter',
            'classroom_id'    => $classroom->id,
        ]);

        // AISI R7: Moderation API による出力層フィルタ
        self::recordModerationFlag(self::moderate($maskedRaw), [
            'generation_type' => 'newsletter',
            'classroom_id'    => $classroom->id,
        ]);

        $maskedDecoded = json_decode($maskedRaw, true) ?? [];

        $this->logGeneration('newsletter', $response, $prompt, $maskedDecoded, $durationMs);

        return $this->unmaskDecoded($masker, $maskedDecoded);
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
        // AISI R1 + R2 (2026-05-17): 事業所名のみ仮名化 (集計は氏名を含まない設計)
        $sanitizer = new AiPromptSanitizer();
        $masker = new AiIdentityMasker();
        if ($classroomName !== '') {
            $masker->register($classroomName, 'classroom');
        }

        $prompt = $this->buildSelfEvaluationPrompt($guardianSummary, $staffSummary, $classroomName, $sanitizer, $masker);

        $startTime = microtime(true);

        // AISI R6 (2026-05-17): OpenAiClientFactory 経由で organization / ZDR を反映
        $client = OpenAiClientFactory::make();
        $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => AiPromptDirectives::systemBase($sanitizer)
                        . '放課後等デイサービスの事業所における自己評価結果（別紙3）を作成するAIアシスタントです。'
                        . '保護者評価と従業者評価の集計結果から、事業所の強みと弱みを分析してください。'
                        . 'JSON形式で回答してください。',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.7,
            'max_completion_tokens' => 3000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $maskedRaw = (string) ($response->choices[0]->message->content ?? '');
        $maskedRaw = $sanitizer->postProcess($maskedRaw, [
            'generation_type' => 'self_evaluation_summary',
        ]);

        // AISI R7: Moderation API による出力層フィルタ
        self::recordModerationFlag(self::moderate($maskedRaw), [
            'generation_type' => 'self_evaluation_summary',
        ]);

        $maskedDecoded = json_decode($maskedRaw, true) ?? [];

        $this->logGeneration('self_evaluation_summary', $response, $prompt, $maskedDecoded, $durationMs);

        return $this->unmaskDecoded($masker, $maskedDecoded);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildSupportPlanPrompt(
        Student $student,
        array $context,
        AiPromptSanitizer $san,
        AiIdentityMasker $masker,
    ): string {
        $recentRecords = $student->dailyRecords()
            ->with('studentRecords')
            ->orderByDesc('record_date')
            ->limit(30)
            ->get();

        $previousPlan = $student->supportPlans()
            ->with('details')
            ->orderByDesc('created_date')
            ->first();

        // 仮名化済の表示名 (placeholder) を取得
        $studentLabel   = $masker->placeholderFor($student->student_name) ?: '対象児童 A';
        $classroomLabel = $masker->placeholderFor($student->classroom?->classroom_name ?? '') ?: '事業所 A';

        $prompt = "以下の情報をもとに個別支援計画書を作成してください。\n\n";
        $prompt .= "【児童名】{$studentLabel}\n";
        $prompt .= "【学年】{$student->grade_level}\n";
        $prompt .= "【教室】{$classroomLabel}\n\n";

        if ($previousPlan) {
            // 前回計画の本文中に実名 (student_name 等) が混入していてもマスク済にする
            $prompt .= "【前回の計画】\n";
            $prompt .= "・本人の願い: " . $masker->mask((string) $previousPlan->life_intention) . "\n";
            $prompt .= "・支援方針: "   . $masker->mask((string) $previousPlan->overall_policy) . "\n";
            $prompt .= "・長期目標: "   . $masker->mask((string) $previousPlan->long_term_goal) . "\n";
            $prompt .= "・短期目標: "   . $masker->mask((string) $previousPlan->short_term_goal) . "\n\n";
        }

        if ($recentRecords->isNotEmpty()) {
            $prompt .= "【最近の活動記録（直近30件）】\n";
            foreach ($recentRecords as $record) {
                $prompt .= "- {$record->record_date->format('Y/m/d')}: " . $masker->mask((string) $record->activity_name) . "\n";
                foreach ($record->studentRecords->where('student_id', $student->id) as $sr) {
                    if ($sr->notes) {
                        // 自由記述はマスクしたうえで、デリミタで囲って「データ部」として明示
                        $prompt .= "  備考: " . $san->wrap($masker->mask((string) $sr->notes), 'NOTES') . "\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        if (! empty($context['additional_notes'])) {
            $prompt .= "【追加情報】\n"
                . $san->wrap($masker->mask((string) $context['additional_notes']), 'EXTRA')
                . "\n\n";
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

    private function buildMonitoringPrompt(
        MonitoringRecord $record,
        AiPromptSanitizer $san,
        AiIdentityMasker $masker,
    ): string {
        $plan = $record->plan;
        $student = $record->student;

        $studentLabel = $student ? ($masker->placeholderFor($student->student_name) ?: '対象児童 A') : '対象児童';

        $prompt = "以下の支援計画に対するモニタリング報告を作成してください。\n\n";
        $prompt .= "【児童名】{$studentLabel}\n";
        $prompt .= "【計画の目標】\n";
        $prompt .= "・長期目標: " . $masker->mask((string) $plan->long_term_goal) . "\n";
        $prompt .= "・短期目標: " . $masker->mask((string) $plan->short_term_goal) . "\n\n";

        $prompt .= "【計画の詳細】\n";
        foreach ($plan->details as $detail) {
            $prompt .= "- {$detail->domain}: 目標「"
                . $masker->mask((string) $detail->goal) . "」 支援内容「"
                . $masker->mask((string) $detail->support_content) . "」\n";
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

    private function buildNewsletterPrompt(
        Classroom $classroom,
        int $year,
        int $month,
        AiPromptSanitizer $san,
        AiIdentityMasker $masker,
    ): string {
        $classroomLabel = $masker->placeholderFor($classroom->classroom_name) ?: '事業所 A';

        $prompt = "以下の情報をもとに{$year}年{$month}月の教室だよりを作成してください。\n\n";
        $prompt .= "【教室名】{$classroomLabel}\n\n";

        $events = $classroom->events()
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->get();

        if ($events->isNotEmpty()) {
            $prompt .= "【今月のイベント】\n";
            foreach ($events as $event) {
                $prompt .= "- {$event->event_date->format('m/d')}: " . $masker->mask((string) $event->title) . "\n";
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

    private function buildSelfEvaluationPrompt(
        array $guardianSummary,
        array $staffSummary,
        string $classroomName,
        AiPromptSanitizer $san,
        AiIdentityMasker $masker,
    ): string {
        $classroomName = $masker->placeholderFor($classroomName) ?: '事業所 A';

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

    private function logGeneration(string $type, object $response, string $prompt, array $output, int $durationMs): void
    {
        try {
            AiGenerationLog::create([
                'user_id' => Auth::id(),
                'generation_type' => $type,
                'model' => $response->model ?? config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
                'prompt_tokens' => $response->usage->promptTokens ?? 0,
                'completion_tokens' => $response->usage->completionTokens ?? 0,
                // AISI R2 (2026-05-17): prompt / output 共に **マスク済** の文字列を保存。
                // 呼出側で unmask されるのは呼出元への戻り値のみで、
                // ai_generation_logs テーブルには実名が残らない設計。
                'input_data' => ['prompt' => mb_substr($prompt, 0, 5000)],
                'output_data' => $output,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log AI generation', ['error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // AISI R1 / R2 (2026-05-17) — Sanitizer / Masker 共通ヘルパー
    // =========================================================================

    /**
     * Student に紐付く主要な実名 (児童名、保護者名、教室名、過去計画の各種記名)
     * を AiIdentityMasker に登録する。
     */
    private function registerStudentNames(AiIdentityMasker $masker, Student $student): void
    {
        if ($student->student_name) {
            $masker->register((string) $student->student_name, 'student');
        }
        if ($student->classroom?->classroom_name) {
            $masker->register((string) $student->classroom->classroom_name, 'classroom');
        }
        if ($student->guardian?->full_name) {
            $masker->register((string) $student->guardian->full_name, 'guardian');
        }

        // 過去計画に含まれる職員/保護者の記名も保護対象に
        foreach ($student->relationLoaded('supportPlans') ? $student->supportPlans : [] as $p) {
            if (! empty($p->student_name)) {
                $masker->register((string) $p->student_name, 'student');
            }
            if (! empty($p->staff_signer_name)) {
                $masker->register((string) $p->staff_signer_name, 'staff');
            }
            if (! empty($p->consent_name)) {
                $masker->register((string) $p->consent_name, 'guardian');
            }
            if (! empty($p->manager_name)) {
                $masker->register((string) $p->manager_name, 'staff');
            }
        }
    }

    /**
     * AI 応答の decoded array について、再帰的に文字列を unmask して返す。
     * (配列や入れ子の details[] にも対応)
     */
    private function unmaskDecoded(AiIdentityMasker $masker, array $decoded): array
    {
        return $this->walkUnmask($decoded, $masker);
    }

    /** @return mixed */
    private function walkUnmask(mixed $value, AiIdentityMasker $masker): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->walkUnmask($v, $masker);
            }
            return $out;
        }
        if (is_string($value)) {
            return $masker->unmask($value);
        }
        return $value;
    }

    // =========================================================================
    // AISI R7 (2026-05-17) — OpenAI Moderation API による出力フィルタ
    // =========================================================================

    /**
     * 任意のテキストを OpenAI Moderation API に送り、有害カテゴリの検出結果を返す。
     *
     * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R7 (2026-05-17):
     *  - V1 有害情報の出力制御 / 表 3-2 ③ 出力層フィルタリング
     *
     * Moderation API は OpenAI が無料で提供しており、
     * hate / self-harm / sexual / violence / harassment 等のカテゴリを返す。
     * 本メソッドは戻り値だけ提供し、呼出側が flagged の扱い (warn / redact / 再生成)
     * を判断する設計とする。
     *
     * @return array{flagged: bool, categories: string[], scores: array<string, float>, error: ?string}
     */
    public static function moderate(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['flagged' => false, 'categories' => [], 'scores' => [], 'error' => null];
        }

        try {
            $client = OpenAiClientFactory::make();
            $response = $client->moderations()->create([
                'model' => 'omni-moderation-latest',
                'input' => mb_substr($text, 0, 8000),  // Moderation API は十分大きいが念のため切詰
            ]);

            $result = $response->results[0] ?? null;
            if ($result === null) {
                return ['flagged' => false, 'categories' => [], 'scores' => [], 'error' => 'empty_response'];
            }

            $flaggedCats = [];
            $scores = [];
            $cats = (array) ($result->categories ?? []);
            foreach ($cats as $name => $flagged) {
                if ($flagged) $flaggedCats[] = $name;
            }
            $catScores = (array) ($result->categoryScores ?? $result->category_scores ?? []);
            foreach ($catScores as $name => $score) {
                $scores[$name] = (float) $score;
            }

            return [
                'flagged'    => (bool) ($result->flagged ?? false),
                'categories' => $flaggedCats,
                'scores'     => $scores,
                'error'      => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('OpenAI Moderation API call failed', [
                'error' => $e->getMessage(),
            ]);
            return [
                'flagged'    => false,
                'categories' => [],
                'scores'     => [],
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Moderation 結果が flagged な場合に Log::warning + master_admin_audit_logs に記録するヘルパー。
     *
     * @param array{flagged: bool, categories: string[], scores: array<string, float>, error: ?string} $result
     */
    public static function recordModerationFlag(array $result, array $context = []): void
    {
        // AI-04 修正: fail-open を fail-warn に変更。Moderation API が
        // 障害 (503/レート制限/タイムアウト) で落ちた場合、旧実装は flagged=false
        // で素通りし何も記録しなかった。これでは有害判定をすり抜けたのか API が
        // 落ちたのか区別できないため、error がある場合も監査ログに記録する。
        $hasError = ! empty($result['error']);
        if (! $result['flagged'] && ! $hasError) return;

        $action = $result['flagged'] ? 'ai_moderation_flagged' : 'ai_moderation_unavailable';

        Log::warning(
            $result['flagged'] ? 'AI output flagged by Moderation API' : 'AI Moderation unavailable (fail-warn)',
            array_merge([
                'categories' => $result['categories'],
                'scores'     => $result['scores'],
                'error'      => $result['error'] ?? null,
            ], $context)
        );

        try {
            if (class_exists('\\App\\Models\\MasterAdminAuditLog')) {
                \App\Models\MasterAdminAuditLog::create([
                    'master_user_id' => Auth::id(),
                    'action'         => $action,
                    'context'        => array_merge([
                        'categories' => $result['categories'],
                        'scores'     => $result['scores'],
                        'error'      => $result['error'] ?? null,
                    ], $context),
                ]);
            }
        } catch (\Throwable $e) {
            // 記録失敗は無視 (本処理は継続)
        }
    }

    // =========================================================================
    // ベクター検索用の埋め込み生成 (AI-07 修正)
    // =========================================================================

    /**
     * テキストから埋め込みベクトルを生成する。
     *
     * EmbeddingService::embed() から呼ばれる。旧来このメソッドが未実装で、
     * 支援計画承認時の GenerateEmbeddingJob が必ず BadMethodCallException で
     * 失敗していた (AI-07)。OpenAI Embeddings API を呼ぶ実装を追加する。
     *
     * 注意 (AISI 観点5): 呼出側 (GenerateEmbeddingJob) で既に PiiMasker /
     * AiIdentityMasker により氏名を仮名化済みのテキストを渡す前提。
     * 識別は metadata.student_id で行うため、ベクトル本体に実名は不要。
     *
     * @return array<int, float> 浮動小数点ベクトル
     * @throws \RuntimeException API 障害時
     */
    public function generateEmbedding(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $client = OpenAiClientFactory::make();
        $model = config('services.openai.embedding_model', 'text-embedding-3-small');

        $response = $client->embeddings()->create([
            'model' => $model,
            'input' => $text,
        ]);

        $vector = $response->embeddings[0]->embedding ?? null;
        if (! is_array($vector) || empty($vector)) {
            throw new \RuntimeException('埋め込みベクトルの生成に失敗しました (空の応答)。');
        }

        // 念のため float にキャスト
        return array_map(static fn ($v) => (float) $v, $vector);
    }
}
