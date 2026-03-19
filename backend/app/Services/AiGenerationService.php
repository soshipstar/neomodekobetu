<?php

namespace App\Services;

use App\Models\AiGenerationLog;
use App\Models\Classroom;
use App\Models\MonitoringRecord;
use App\Models\Student;
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

        $prompt = $this->buildSupportPlanPrompt($student, $context);

        $startTime = microtime(true);

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '障害児通所支援の個別支援計画書を作成する専門家AIアシスタントです。'
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
            'max_tokens' => 4000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        $this->logGeneration('support_plan', $response, $prompt, $content, $durationMs);

        return $content;
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

        $prompt = $this->buildMonitoringPrompt($record);

        $startTime = microtime(true);

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '障害児通所支援のモニタリング報告書を作成する専門家AIアシスタントです。'
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
            'max_tokens' => 3000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        $this->logGeneration('monitoring_report', $response, $prompt, $content, $durationMs);

        return $content;
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

        $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '放課後等デイサービスの教室だよりを作成するAIアシスタントです。'
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
            'max_tokens' => 3000,
        ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $content = json_decode($response->choices[0]->message->content, true) ?? [];

        $this->logGeneration('newsletter', $response, $prompt, $content, $durationMs);

        return $content;
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

    private function logGeneration(string $type, object $response, string $prompt, array $output, int $durationMs): void
    {
        try {
            AiGenerationLog::create([
                'user_id' => Auth::id(),
                'generation_type' => $type,
                'model' => $response->model ?? config('services.openai.model', 'gpt-5.4-mini-2026-03-17'),
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
