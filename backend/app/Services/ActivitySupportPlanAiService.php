<?php

namespace App\Services;

use App\Models\AiGenerationLog;
use Illuminate\Support\Facades\Log;

/**
 * 活動支援案の AI 生成ロジック (プロンプト構築 + OpenAI 呼び出し)。
 *
 * 同期エンドポイント (ActivitySupportPlanController) と
 * 非同期ジョブ (RunAiGenerationTaskJob) の双方から呼ばれる単一の真実。
 * これにより、同期/非同期でプロンプト・モデル・出力が完全に一致する。
 */
class ActivitySupportPlanAiService
{
    /**
     * 空応答・JSON不正・トークン上限切れに備えた最大試行回数(同期エンドポイントの
     * 待ち時間が伸びすぎないよう 2 回=1リトライに留める。非同期ジョブ側も $tries=2 で再試行)。
     */
    private const MAX_ATTEMPTS = 2;

    /**
     * 五領域への配慮を生成。
     *
     * @param  array{activity_name:string,activity_purpose?:string,activity_content?:string,target_grade?:string}  $input
     * @return array  AI 出力 (five_domains_consideration / other_notes)
     */
    public function generateFiveDomains(array $input, ?int $userId = null): array
    {
        $activityName = (string) ($input['activity_name'] ?? '');
        $activityPurpose = (string) ($input['activity_purpose'] ?? '');
        $activityContent = (string) ($input['activity_content'] ?? '');
        $style = GradeLevelStyleService::forTargetGrade($input['target_grade'] ?? null);

        $prompt = "あなたは児童発達支援・放課後等デイサービスの支援員です。\n";
        $prompt .= "以下の活動について、五領域への配慮とその他の注意点を生成してください。\n\n";
        $prompt .= "【活動名】\n{$activityName}\n\n";
        $prompt .= "【活動の目的】\n{$activityPurpose}\n\n";
        $prompt .= "【活動の内容】\n{$activityContent}\n\n";
        $prompt .= "【対象年齢層: {$style['label']}】\n";
        $prompt .= "{$style['guideline']}\n\n";
        $prompt .= "【この年齢で五領域の配慮として重視すべき観点】\n";
        $prompt .= "{$style['considerations']}\n\n";
        $prompt .= "以下の形式でJSON形式で出力してください。JSONのみを出力し、他の説明は不要です。\n\n";
        $prompt .= "{\n";
        $prompt .= '    "five_domains_consideration": "五領域への配慮を一つの文字列として記載（下記フォーマット参照）",' . "\n";
        $prompt .= '    "other_notes": "活動を行う際の注意点、準備物、安全面での配慮など"' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "【five_domains_considerationのフォーマット】\n";
        $prompt .= "以下の形式で、5つの領域を改行で区切って一つの文字列として記載してください：\n\n";
        $prompt .= "【健康・生活】\n（この活動における健康・生活面での配慮）\n\n";
        $prompt .= "【運動・感覚】\n（この活動における運動・感覚面での配慮）\n\n";
        $prompt .= "【認知・行動】\n（この活動における認知・行動面での配慮）\n\n";
        $prompt .= "【言語・コミュニケーション】\n（この活動における言語・コミュニケーション面での配慮）\n\n";
        $prompt .= "【人間関係・社会性】\n（この活動における人間関係・社会性面での配慮）\n\n";
        $prompt .= "各領域の説明：\n";
        $prompt .= "1. 健康・生活：基本的な生活習慣、健康管理に関すること\n";
        $prompt .= "2. 運動・感覚：身体の動き、感覚の使い方に関すること\n";
        $prompt .= "3. 認知・行動：物事の理解、問題解決、行動のコントロールに関すること\n";
        $prompt .= "4. 言語・コミュニケーション：言葉の理解と表出、コミュニケーションに関すること\n";
        $prompt .= "5. 人間関係・社会性：他者との関わり、社会的なルールの理解に関すること\n\n";
        $prompt .= "出力は日本語で、実用的で具体的な内容にしてください。";

        return $this->run(
            systemPrompt: '放課後等デイサービスの支援案を作成する専門家AIアシスタントです。JSON形式で回答してください。',
            userPrompt: $prompt,
            maxTokens: 4000,
            logType: 'activity_support_plan_five_domains',
            userId: $userId,
        );
    }

    /**
     * スケジュールをもとに活動内容を生成。
     *
     * @param  array{activity_name:string,activity_purpose?:string,total_duration:int,schedule:array,target_grade?:string}  $input
     */
    public function generateScheduleContent(array $input, ?int $userId = null): array
    {
        $activityName = (string) ($input['activity_name'] ?? '');
        $activityPurpose = (string) ($input['activity_purpose'] ?? '');
        $totalDuration = (int) ($input['total_duration'] ?? 0);
        $schedule = (array) ($input['schedule'] ?? []);
        $targetGrade = (string) ($input['target_grade'] ?? '');
        $style = GradeLevelStyleService::forTargetGrade($targetGrade);

        $gradeLabels = [
            'preschool' => '小学生未満',
            'elementary' => '小学生',
            'junior_high' => '中学生',
            'high_school' => '高校生',
        ];
        $targetGradeText = '';
        if ($targetGrade) {
            $grades = array_map(fn ($g) => $gradeLabels[trim($g)] ?? trim($g), explode(',', $targetGrade));
            $targetGradeText = implode('、', $grades);
        }

        $scheduleText = '';
        foreach ($schedule as $i => $item) {
            $num = $i + 1;
            $type = ($item['type'] ?? '') === 'routine' ? '毎日の支援' : '主活動';
            $name = $item['name'] ?? '';
            $duration = $item['duration'] ?? 15;
            $content = $item['content'] ?? '';
            $scheduleText .= "{$num}. {$name}（{$type}）- {$duration}分";
            if ($content) {
                $scheduleText .= "\n   内容: {$content}";
            }
            $scheduleText .= "\n";
        }

        $prompt = "あなたは児童発達支援・放課後等デイサービスの経験豊富な支援員です。\n以下の活動について、スケジュールと時間配分に基づいた詳細な活動内容を生成してください。\n\n";
        $prompt .= "【活動名】{$activityName}\n";
        if ($activityPurpose) {
            $prompt .= "【活動の目的】{$activityPurpose}\n";
        }
        $prompt .= "【総活動時間】{$totalDuration}分\n";
        if ($targetGradeText) {
            $prompt .= "【対象年齢層】{$targetGradeText} (主たる対象: {$style['label']})\n";
        } else {
            $prompt .= "【対象年齢層】(主たる対象: {$style['label']})\n";
        }
        $prompt .= "\n【対象年齢層の表現スタイルと配慮】\n{$style['guideline']}\n";
        $prompt .= "\n【活動スケジュール】\n{$scheduleText}\n";
        $prompt .= "\n以下の形式でJSON形式で出力してください。JSONのみを出力し、他の説明は不要です。\n\n";
        $prompt .= "{\n";
        $prompt .= '    "activity_content": "活動の内容（詳細な活動の流れと準備物）",' . "\n";
        $prompt .= '    "other_notes": "活動時の配慮事項"' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "【活動内容（activity_content）の作成ガイドライン】\n";
        $prompt .= "1. スケジュールの順番と時間配分を厳守してください\n";
        $prompt .= "2. タイムスケジュールの一覧表は不要です。活動の流れから始めてください\n";
        $prompt .= "3. 以下の構成で記載してください：\n\n";
        $prompt .= "■ 詳細な活動の流れ\n\n";
        $prompt .= "【活動1: ○○】（○○分）\n";
        $prompt .= "・導入：子どもたちへの声かけ、準備\n";
        $prompt .= "・展開：具体的な活動内容\n";
        $prompt .= "・スタッフの役割と配置\n\n";
        $prompt .= "【活動2: ○○】（○○分）\n";
        $prompt .= "...\n\n";
        $prompt .= "■ 準備物\n";
        $prompt .= "- 活動に必要な物品リスト\n\n";
        $prompt .= "4. 「毎日の支援」はルーティーン活動なので、簡潔に記載\n";
        $prompt .= "5. 「主活動」はメインの活動なので、詳細に記載\n";
        $prompt .= "6. 子どもの発達段階に合わせた声かけの例を含める\n";
        $prompt .= "7. 活動の切り替え時の工夫も記載\n\n";
        $prompt .= "【その他（other_notes）の作成ガイドライン】\n";
        $prompt .= "以下の内容を記載してください：\n";
        $prompt .= "- 安全面での注意点\n";
        $prompt .= "- 個別支援が必要な子どもへの配慮\n";
        $prompt .= "- 活動中の見守りポイント\n\n";
        $prompt .= "出力は日本語で、実用的で具体的な内容にしてください。";

        return $this->run(
            systemPrompt: '放課後等デイサービスの活動計画を作成する専門家AIアシスタントです。JSON形式で回答してください。',
            userPrompt: $prompt,
            maxTokens: 6000,
            logType: 'activity_support_plan_schedule',
            userId: $userId,
        );
    }

    /**
     * OpenAI 呼び出し共通処理。
     */
    private function run(string $systemPrompt, string $userPrompt, int $maxTokens, string $logType, ?int $userId): array
    {
        $startTime = microtime(true);
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI APIキーが設定されていません。');
        }
        $client = \OpenAI::client($apiKey);

        // 空応答・不正JSON・トークン上限での打ち切りで「完了したのに空白」になる事象への対策。
        // 失敗を握りつぶさず、数回リトライし、それでも駄目なら例外を投げて呼び出し側で失敗扱いにする。
        $lastError = '不明なエラー';
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = $client->chat()->create([
                'model' => 'gpt-5.4-2026-03-05',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_completion_tokens' => $maxTokens,
            ]);

            $raw = (string) ($response->choices[0]->message->content ?? '');
            $finish = $response->choices[0]->finishReason ?? null;
            $decoded = json_decode($raw, true);

            // 期待キーに実体があるか確認(空オブジェクト {} や空文字だけの応答も「空白」とみなす)。
            $nonEmpty = is_array($decoded)
                ? array_filter($decoded, fn ($v) => is_string($v) ? trim($v) !== '' : ! empty($v))
                : [];

            if ($nonEmpty !== []) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->logGeneration($logType, $response, $userPrompt, $decoded, $durationMs, $userId);

                return $decoded;
            }

            // 失敗の理由を分類してリトライ。トークン上限切れは最頻の原因(推論モデルが出力前に予算を使い切る)。
            $lastError = match (true) {
                $finish === 'length'  => '応答がトークン上限で打ち切られました',
                $raw === ''           => '応答が空でした',
                ! is_array($decoded)  => 'JSON解析に失敗しました',
                default               => '生成内容が空でした',
            };
            Log::warning('ActivitySupportPlan AI: empty/invalid response, retrying', [
                'type' => $logType, 'attempt' => $attempt, 'finish_reason' => $finish,
                'content_excerpt' => mb_substr($raw, 0, 200),
            ]);
        }

        // 全リトライ失敗。呼び出し側(コントローラ)が catch して 500 を返し、フロントは「完了」を出さない。
        throw new \RuntimeException("AI応答を取得できませんでした({$lastError})。お手数ですが、もう一度お試しください。");
    }

    private function logGeneration(string $type, object $response, string $prompt, array $output, int $durationMs, ?int $userId): void
    {
        try {
            AiGenerationLog::create([
                'user_id'           => $userId,
                'generation_type'   => $type,
                'model'             => $response->model ?? config('services.openai.model', 'gpt-5.4-2026-03-05'),
                'prompt_tokens'     => $response->usage->promptTokens ?? 0,
                'completion_tokens' => $response->usage->completionTokens ?? 0,
                'input_data'        => ['prompt' => mb_substr($prompt, 0, 5000)],
                'output_data'       => $output,
                'duration_ms'       => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log AI generation', ['error' => $e->getMessage()]);
        }
    }
}
