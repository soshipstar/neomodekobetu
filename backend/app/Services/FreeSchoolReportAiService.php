<?php

namespace App\Services;

use App\Models\ActivitySupportPlan;
use App\Models\DailyRecord;
use App\Models\IndividualSupportPlan;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\Log;

/**
 * フリースクール用報告書を AI で初稿生成するサービス。
 *
 * 入力:
 *   - 児童 (Student)
 *   - 活動日 (DailyRecord) — 連絡帳 + 支援案 + その日の観察記録のソース
 *   - 個別支援計画 (IndividualSupportPlan) — 長期/短期目標を引用
 *
 * 出力 (4 セクション):
 *   - activity_summary       : 活動概要 (どんな活動を何分行ったか)
 *   - support_consideration  : 支援内容と五領域への配慮 (支援案から)
 *   - child_observation      : 本人の様子・取り組み (連絡帳 + 観察記録から)
 *   - evaluation_and_next    : 評価・今後の課題 (個別支援計画の目標と照らして)
 *
 * 文体は学年別に GradeLevelStyleService で調整。
 * モデルは要望通り gpt-5.4-mini-2026-03-17。
 */
class FreeSchoolReportAiService
{
    public const MODEL = 'gpt-5.4-mini-2026-03-17';

    /**
     * @return array{
     *   activity_summary: string,
     *   support_consideration: string,
     *   child_observation: string,
     *   evaluation_and_next: string,
     * }
     */
    public function generate(Student $student, DailyRecord $record): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI APIキーが未設定です。');
        }

        $style = GradeLevelStyleService::forStudentGrade($student->grade_level);

        // 当日の連絡帳統合文 (あれば本人の様子の素材として最重要)
        $integrated = IntegratedNote::where('daily_record_id', $record->id)
            ->where('student_id', $student->id)
            ->first();

        // 当日の児童ごとの観察記録 (5 領域)
        $studentRecord = StudentRecord::where('daily_record_id', $record->id)
            ->where('student_id', $student->id)
            ->first();

        // 当日の活動の支援案 (五領域への配慮の素材)
        $supportPlan = $record->support_plan_id
            ? ActivitySupportPlan::find($record->support_plan_id)
            : null;

        // 児童の個別支援計画 (目標と照らして評価する素材)
        $individualPlan = IndividualSupportPlan::where('student_id', $student->id)
            ->where('is_official', true)
            ->orderByDesc('created_date')
            ->first();

        $prompt = $this->buildPrompt($student, $record, $style, $integrated, $studentRecord, $supportPlan, $individualPlan);

        $client = \OpenAI::client($apiKey);

        try {
            $response = $client->chat()->create([
                'model' => self::MODEL,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは特別支援教育・放課後等デイサービスの専門家です。'
                            . 'フリースクール (学校提出用) の報告書を作成します。'
                            . '保護者・本人・学校が読んで前向きな気持ちになる、温かく具体的な文章を書きます。'
                            . '本人の名前を文中で出すときは必ず「○○さん」(さん付け) にし、'
                            . '「くん」「ちゃん」「呼び捨て」「○○様」は使いません。'
                            . '他の児童は「友だち」と表記します。'
                            . '出力は JSON のみで、他の文字は出しません。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_completion_tokens' => 2500,
            ]);

            $raw = $response->choices[0]->message->content ?? '{}';
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) $decoded = [];

            return [
                'activity_summary'      => trim((string) ($decoded['activity_summary'] ?? '')),
                'support_consideration' => trim((string) ($decoded['support_consideration'] ?? '')),
                'child_observation'     => trim((string) ($decoded['child_observation'] ?? '')),
                'evaluation_and_next'   => trim((string) ($decoded['evaluation_and_next'] ?? '')),
            ];
        } catch (\Throwable $e) {
            Log::error('FreeSchoolReportAiService::generate failed', [
                'student_id' => $student->id,
                'record_id'  => $record->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function buildPrompt(
        Student $student,
        DailyRecord $record,
        array $style,
        ?IntegratedNote $integrated,
        ?StudentRecord $studentRecord,
        ?ActivitySupportPlan $supportPlan,
        ?IndividualSupportPlan $individualPlan,
    ): string {
        $name = $student->student_name ?? '本人';
        $date = $record->record_date?->format('Y年n月j日') ?? '';
        $activityName = $record->activity_name ?? '';

        $p = "以下のデータを統合し、学校に提出するフリースクール報告書として\n";
        $p .= "4 つのセクションを生成してください。\n\n";

        $p .= "【対象児童】{$name}\n";
        $p .= "【活動日】{$date}\n";
        $p .= "【活動名】{$activityName}\n";
        $p .= "【発達段階の目安】{$style['label']}\n\n";
        $p .= "【文体ガイドライン】\n{$style['guideline']}\n\n";

        if ($supportPlan) {
            $p .= "----- 当日の支援案 -----\n";
            if ($supportPlan->activity_purpose) $p .= "活動の目的: {$supportPlan->activity_purpose}\n";
            if ($supportPlan->activity_content) $p .= "活動の計画内容:\n{$supportPlan->activity_content}\n";
            if ($supportPlan->five_domains_consideration) {
                $p .= "五領域への配慮:\n{$supportPlan->five_domains_consideration}\n";
            }
            if ($supportPlan->other_notes) $p .= "その他の留意点:\n{$supportPlan->other_notes}\n";
            $p .= "\n";
        }

        if ($integrated && $integrated->integrated_content) {
            $p .= "----- 当日の連絡帳統合文 (保護者向けに作成済) -----\n";
            $p .= "{$integrated->integrated_content}\n\n";
        }

        if ($studentRecord) {
            $p .= "----- 当日の観察記録 (五領域別) -----\n";
            if ($studentRecord->health_life)            $p .= "【健康・生活】{$studentRecord->health_life}\n";
            if ($studentRecord->motor_sensory)          $p .= "【運動・感覚】{$studentRecord->motor_sensory}\n";
            if ($studentRecord->cognitive_behavior)     $p .= "【認知・行動】{$studentRecord->cognitive_behavior}\n";
            if ($studentRecord->language_communication) $p .= "【言語・コミュニケーション】{$studentRecord->language_communication}\n";
            if ($studentRecord->social_relations)       $p .= "【人間関係・社会性】{$studentRecord->social_relations}\n";
            if ($studentRecord->notes)                  $p .= "メモ: {$studentRecord->notes}\n";
            $p .= "\n";
        }

        if ($individualPlan) {
            $p .= "----- 児童の個別支援計画 (目標との照合用) -----\n";
            if ($individualPlan->long_term_goal)  $p .= "長期目標: {$individualPlan->long_term_goal}\n";
            if ($individualPlan->short_term_goal) $p .= "短期目標: {$individualPlan->short_term_goal}\n";
            $p .= "\n";
        }

        $p .= "----- 生成指示 -----\n";
        $p .= "次の 4 セクションを、それぞれ A4 用紙の見やすい段落として、学校提出に相応しい\n";
        $p .= "丁寧で前向きな文章にまとめてください。読み手は学校の教員と保護者です。\n\n";
        $p .= "1. activity_summary (活動概要): 活動名・所要時間・活動の目的を含め、\n";
        $p .= "   その日に何を行ったかを 4〜6 行で要約。\n";
        $p .= "2. support_consideration (支援内容と五領域への配慮): 支援案の五領域への配慮\n";
        $p .= "   と実際の支援者の関わりを統合し、項目立てて記述。各領域 2〜3 行。\n";
        $p .= "3. child_observation (本人の様子・取り組み): 連絡帳統合文と観察記録から、\n";
        $p .= "   本人がどんな表情・行動・発言で取り組んだかを具体的に 6〜10 行で。\n";
        $p .= "   ポジティブで温かい表現を使用。「友だち」「保護者」表記を統一。\n";
        $p .= "4. evaluation_and_next (評価・今後の課題): 個別支援計画の目標と照らし、\n";
        $p .= "   今日の取り組みがどの目標に寄与したか、次に挑戦するとよい点を 4〜8 行で。\n\n";
        $p .= "重要:\n";
        $p .= "・本人 ({$name}) を「お子様」「子ども」と呼ばず、名前または「本人」とする。\n";
        $p .= "・本人の名前を出すときは必ず「さん」付けにする。\n";
        $p .= "  例: 「{$name}さん」(姓+さん / 姓名+さん どちらでも可)。\n";
        $p .= "  「{$name}くん」「{$name}ちゃん」「{$name}」(呼び捨て)、「{$name}様」は使わない。\n";
        $p .= "  「本人」「ご本人」と呼ぶ場合は「さん」は不要。\n";
        $p .= "・他の児童は「友だち」と表記する (「友達」NG)。\n";
        $p .= "・ネガティブな接続詞 (「しかし」「ですが」) は避ける。\n";
        $p .= "・出力は次の JSON 形式のみ:\n";
        $p .= "{\n";
        $p .= "  \"activity_summary\": \"...\",\n";
        $p .= "  \"support_consideration\": \"...\",\n";
        $p .= "  \"child_observation\": \"...\",\n";
        $p .= "  \"evaluation_and_next\": \"...\"\n";
        $p .= "}\n";

        return $p;
    }
}
