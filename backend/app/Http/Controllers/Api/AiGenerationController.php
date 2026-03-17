<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationLog;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use App\Models\Newsletter;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\VectorEmbedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class AiGenerationController extends Controller
{
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

        $recordsText = $records->map(function ($r) {
            $date = $r->dailyRecord->record_date ?? '';
            return "[{$date}] {$r->domain1}: {$r->domain1_content}";
        })->implode("\n");

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

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは児童発達支援施設の児童発達支援管理責任者です。個別支援計画書の作成を支援します。JSON配列で回答してください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "以下の情報をもとに、個別支援計画書の内容を生成してください。\n\n"
                            . "【児童名】{$student->student_name}\n\n"
                            . "【面接記録】\n{$interviewText}\n\n"
                            . "【連絡帳記録】\n{$recordsText}\n\n"
                            . "【過去の支援計画書】\n{$pastPlanText}\n\n"
                            . ($validated['context'] ? "【追加情報】\n{$validated['context']}\n\n" : '')
                            . "以下を含むJSONを出力してください:\n"
                            . "{\n"
                            . "  \"life_intention\": \"本人の生活に対する意向\",\n"
                            . "  \"overall_policy\": \"総合的な援助の方針\",\n"
                            . "  \"long_term_goal\": \"長期目標\",\n"
                            . "  \"short_term_goal\": \"短期目標\",\n"
                            . "  \"details\": [{\"category\": \"分野\", \"sub_category\": \"サブ分野\", \"support_goal\": \"目標\", \"support_content\": \"支援内容\"}]\n"
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
                'model'        => 'gpt-4o',
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

        $recordsText = $records->map(function ($r) {
            $date = $r->dailyRecord->record_date ?? '';
            return "[{$date}] {$r->domain1}: {$r->domain1_content} / {$r->domain2}: {$r->domain2_content}";
        })->implode("\n");

        $details = $plan->details;
        if ($validated['detail_id'] ?? null) {
            $details = $details->where('id', $validated['detail_id']);
        }

        $detailsText = $details->map(fn ($d) => "- [{$d->category}/{$d->sub_category}] 目標: {$d->support_goal} / 内容: {$d->support_content}")->implode("\n");

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは児童発達支援施設の児童発達支援管理責任者です。モニタリング評価を生成します。JSON形式のみで回答してください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "【児童名】{$student->student_name}\n\n"
                            . "【計画の目標・支援内容】\n{$detailsText}\n\n"
                            . "【過去6ヶ月の記録】\n{$recordsText}\n\n"
                            . "各目標に対して評価してください。出力形式:\n"
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
                'model'        => 'gpt-4o',
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

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => '児童発達支援施設のスタッフとして、保護者向けのお便りの文章を作成します。温かみがあり丁寧な表現を心がけてください。',
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
                'model'        => 'gpt-4o',
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

    /**
     * 類似事例検索（ベクトル検索）
     */
    public function similarCases(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query'      => 'required|string|max:1000',
            'limit'      => 'nullable|integer|min:1|max:20',
            'type'       => 'nullable|string|in:support_plan,monitoring,interview',
        ]);

        try {
            // クエリのembeddingを生成
            $embeddingResponse = $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $validated['query'],
            ]);

            $queryEmbedding = $embeddingResponse->embeddings[0]->embedding;
            $limit = $validated['limit'] ?? 5;

            // ベクトル類似検索（コサイン類似度）
            $results = VectorEmbedding::query()
                ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('embeddable_type', $type))
                ->limit($limit * 3) // 余分に取得してスコアでフィルタ
                ->get();

            // コサイン類似度で並び替え
            $scored = $results->map(function ($item) use ($queryEmbedding) {
                $embedding = json_decode($item->embedding, true);
                if (! is_array($embedding)) {
                    return null;
                }

                $score = $this->cosineSimilarity($queryEmbedding, $embedding);
                $item->similarity_score = $score;

                return $item;
            })
            ->filter()
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();

            return response()->json([
                'success' => true,
                'data'    => $scored,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '検索中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 生徒の成長分析をAI生成
     */
    public function analyzeStudentProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'period'     => 'nullable|string|in:3months,6months,1year',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $period = $validated['period'] ?? '6months';

        $startDate = match ($period) {
            '3months' => now()->subMonths(3)->toDateString(),
            '1year'   => now()->subYear()->toDateString(),
            default   => now()->subMonths(6)->toDateString(),
        };

        $records = StudentRecord::where('student_id', $student->id)
            ->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '>=', $startDate))
            ->with('dailyRecord:id,record_date,activity_name')
            ->orderBy('id')
            ->get();

        $recordsText = $records->map(function ($r) {
            $date = $r->dailyRecord->record_date ?? '';
            return "[{$date}] {$r->domain1}: {$r->domain1_content} / {$r->domain2}: {$r->domain2_content} / メモ: {$r->daily_note}";
        })->implode("\n");

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは児童発達支援の専門家です。連絡帳の記録から子どもの成長を分析します。JSON形式で回答してください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "【児童名】{$student->student_name}\n"
                            . "【期間】過去{$period}\n\n"
                            . "【連絡帳記録】\n{$recordsText}\n\n"
                            . "5領域ごとの成長分析を行ってください。出力形式:\n"
                            . "{\"domains\": [{\"name\": \"領域名\", \"progress\": \"成長の傾向\", \"strengths\": \"強み\", \"areas_to_improve\": \"課題\", \"recommendation\": \"今後の支援提案\"}], \"overall_summary\": \"全体的な成長のまとめ\"}",
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

            return response()->json([
                'success' => true,
                'data'    => $result ?? $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI分析中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * コサイン類似度を計算
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }
}
