<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationLog;
use App\Models\KakehashiGuardian;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class KakehashiController extends Controller
{
    /**
     * 生徒のかけはし一覧を取得（期間ごと）
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $periods = KakehashiPeriod::where('student_id', $student->id)
            ->with(['staffEntries', 'guardianEntries.guardian:id,full_name'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $periods,
        ]);
    }

    /**
     * かけはしスタッフ記入を保存（新規 or 更新）
     */
    public function store(Request $request, KakehashiPeriod $period): JsonResponse
    {
        $period->load('student');

        if (! $period->student) {
            return response()->json(['success' => false, 'message' => '期間が見つかりません。'], 404);
        }

        $this->authorizeClassroom($request->user(), $period->student);

        $validated = $request->validate([
            'student_wish'               => 'nullable|string',
            'short_term_goal'            => 'nullable|string',
            'long_term_goal'             => 'nullable|string',
            'health_life'                => 'nullable|string',
            'motor_sensory'              => 'nullable|string',
            'cognitive_behavior'         => 'nullable|string',
            'language_communication'     => 'nullable|string',
            'social_relations'           => 'nullable|string',
            'other_challenges'           => 'nullable|string',
            'action'                     => 'nullable|string|in:save,submit,update',
        ]);

        $action = $validated['action'] ?? 'save';
        unset($validated['action']);

        $existing = KakehashiStaff::where('period_id', $period->id)
            ->where('student_id', $period->student_id)
            ->first();

        // 提出済みの場合は update アクションのみ許可
        if ($existing && $existing->is_submitted && $action !== 'update') {
            return response()->json([
                'success' => false,
                'message' => '既に提出済みのため、変更できません。',
            ], 422);
        }

        $isSubmitted = in_array($action, ['submit', 'update']);

        if ($existing) {
            $updateData = $validated;

            if ($action !== 'update') {
                $updateData['is_submitted'] = $isSubmitted;
                if ($isSubmitted) {
                    $updateData['submitted_at'] = now();
                }
            }

            $existing->update($updateData);
            $entry = $existing;
        } else {
            $entry = KakehashiStaff::create(array_merge($validated, [
                'period_id'    => $period->id,
                'student_id'   => $period->student_id,
                'staff_id'     => $request->user()->id,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ]));
        }

        $message = match ($action) {
            'update' => 'かけはしの内容を修正しました。',
            'submit' => 'かけはしを提出しました。',
            default  => '下書きを保存しました。',
        };

        return response()->json([
            'success' => true,
            'data'    => $entry,
            'message' => $message,
        ]);
    }

    /**
     * かけはしスタッフ記入を更新
     */
    public function update(Request $request, KakehashiPeriod $period): JsonResponse
    {
        // store と同じロジックを使用（action=update）
        $request->merge(['action' => 'update']);
        return $this->store($request, $period);
    }

    /**
     * かけはし PDF をダウンロード
     */
    public function pdf(Request $request, KakehashiPeriod $period)
    {
        $period->load(['student.classroom', 'staffEntries', 'guardianEntries']);

        if ($period->student) {
            $this->authorizeClassroom($request->user(), $period->student);
        }

        $filename = 'kakehashi_' . ($period->student->student_name ?? $period->id) . '_' . $period->start_date . '.pdf';

        return PuppeteerPdfService::download('pdf.kakehashi', [
            'period'          => $period,
            'student'         => $period->student,
            'classroom'       => $period->student->classroom ?? null,
            'staffEntries'    => $period->staffEntries,
            'guardianEntries' => $period->guardianEntries,
        ], $filename);
    }

    /**
     * かけはし内容をAI生成（期間内の連絡帳データを参照）
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'period_id'  => 'required|exists:kakehashi_periods,id',
        ]);

        $student = Student::with('classroom')->findOrFail($validated['student_id']);
        $this->authorizeClassroom($request->user(), $student);

        $period = KakehashiPeriod::findOrFail($validated['period_id']);

        // 対象期間の開始日から5か月前の連絡帳データを取得（レガシー準拠）
        $periodStart = $period->start_date;
        $fiveMonthsBefore = (clone $periodStart)->subMonths(5);

        $records = StudentRecord::where('student_id', $student->id)
            ->whereHas('dailyRecord', function ($q) use ($fiveMonthsBefore, $periodStart) {
                $q->where('record_date', '>=', $fiveMonthsBefore->toDateString())
                  ->where('record_date', '<', $periodStart->toDateString());
            })
            ->with('dailyRecord:id,record_date')
            ->where(function ($q) {
                $q->whereNotNull('domain1_content')->orWhereNotNull('domain2_content');
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // 領域ごとにデータを集約（レガシー準拠）
        $domainNames = [
            'health_life' => '健康・生活',
            'motor_sensory' => '運動・感覚',
            'cognitive_behavior' => '認知・行動',
            'language_communication' => '言語・コミュニケーション',
            'social_relations' => '人間関係・社会性',
        ];

        $domainData = array_fill_keys(array_keys($domainNames), []);

        foreach ($records as $record) {
            $date = $record->dailyRecord ? date('Y年m月d日', strtotime($record->dailyRecord->record_date)) : '';
            if ($record->domain1 && $record->domain1_content) {
                $domainData[$record->domain1][] = "【{$date}】" . $record->domain1_content;
            }
            if ($record->domain2 && $record->domain2_content) {
                $domainData[$record->domain2][] = "【{$date}】" . $record->domain2_content;
            }
        }

        $recordsSummary = '';
        foreach ($domainData as $domain => $contents) {
            if (!empty($contents)) {
                $recordsSummary .= "\n■ " . ($domainNames[$domain] ?? $domain) . "\n";
                $recordsSummary .= implode("\n", array_slice($contents, 0, 10)) . "\n";
            }
        }

        if (empty(trim($recordsSummary))) {
            return response()->json([
                'success' => false,
                'message' => '直近5か月の連絡帳データが見つかりません。この生徒の連絡帳データを入力してください。',
            ], 422);
        }

        // 面談記録を取得（直近5か月、レガシー準拠）
        $interviewRecords = \App\Models\StudentInterview::where('student_id', $student->id)
            ->where('interview_date', '>=', $fiveMonthsBefore->toDateString())
            ->where('interview_date', '<', $periodStart->toDateString())
            ->orderByDesc('interview_date')
            ->limit(10)
            ->get();

        $interviewSummary = '';
        if ($interviewRecords->isNotEmpty()) {
            $schoolNotes = [];
            $homeNotes = [];
            $troubleNotes = [];
            $childWishes = [];

            foreach ($interviewRecords as $interview) {
                $date = date('Y年m月d日', strtotime($interview->interview_date));
                if ($interview->check_school && !empty($interview->check_school_note)) {
                    $schoolNotes[] = "【{$date}】" . $interview->check_school_note;
                }
                if ($interview->check_home && !empty($interview->check_home_note)) {
                    $homeNotes[] = "【{$date}】" . $interview->check_home_note;
                }
                if ($interview->check_troubles && !empty($interview->check_troubles_note)) {
                    $troubleNotes[] = "【{$date}】" . $interview->check_troubles_note;
                }
                if (!empty($interview->child_wish)) {
                    $childWishes[] = "【{$date}】" . $interview->child_wish;
                }
            }

            if (!empty($schoolNotes)) {
                $interviewSummary .= "\n■ 学校での様子\n" . implode("\n", array_slice($schoolNotes, 0, 5)) . "\n";
            }
            if (!empty($homeNotes)) {
                $interviewSummary .= "\n■ 家庭での様子\n" . implode("\n", array_slice($homeNotes, 0, 5)) . "\n";
            }
            if (!empty($troubleNotes)) {
                $interviewSummary .= "\n■ 困りごと・悩み\n" . implode("\n", array_slice($troubleNotes, 0, 5)) . "\n";
            }
            if (!empty($childWishes)) {
                $interviewSummary .= "\n■ 児童の願い\n" . implode("\n", array_slice($childWishes, 0, 5)) . "\n";
            }
        }

        // 前回のスタッフかけはしの目標を取得（レガシー準拠）
        $previousEntry = KakehashiStaff::where('student_id', $student->id)
            ->where('is_submitted', true)
            ->whereHas('period', function ($q) use ($period) {
                $q->where('submission_deadline', '<', $period->submission_deadline);
            })
            ->orderByDesc('created_at')
            ->first();

        $previousShortTermGoal = $previousEntry->short_term_goal ?? null;
        $previousLongTermGoal = $previousEntry->long_term_goal ?? null;

        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI APIキーが設定されていません。管理者に連絡してください。',
                ], 422);
            }

            $client = \OpenAI::client($apiKey);
            $aiModel = 'gpt-5.2';
            $totalInputTokens = 0;
            $totalOutputTokens = 0;

            // === 1. 五領域の課題を生成（レガシー準拠） ===
            $domainsPrompt = "あなたは発達支援・特別支援教育の専門スタッフです。以下の生徒の直近5か月の連絡帳記録を詳細に分析し、今後6か月間の具体的な支援課題を各領域ごとに300文字程度でまとめてください。\n\n"
                . "【生徒情報】\n名前: {$student->student_name}\n\n"
                . "【直近5か月の連絡帳記録】\n{$recordsSummary}\n\n"
                . "【分析と課題作成の指針】\n"
                . "以下の5つの領域について、記録から読み取れる具体的な事実を基に、今後6か月間で取り組むべき課題を300文字程度で詳細に記述してください。\n\n"
                . "■ 健康・生活\n- 食事、排泄、睡眠、衛生管理、身だしなみ、安全意識などの実態\n- 観察された具体的な行動や変化\n- 今後の支援目標と具体的なアプローチ\n\n"
                . "■ 運動・感覚\n- 粗大運動（歩く、走る、跳ぶ等）、微細運動（書く、切る、つまむ等）の状況\n- 感覚過敏・鈍麻、協調性、身体の使い方の特徴\n- 改善が見られた点と今後強化すべき点\n\n"
                . "■ 認知・行動\n- 注意集中、記憶、理解力、問題解決能力の実態\n- こだわりやパターン、衝動性、活動への取り組み方\n- 成長が見られた認知面と支援が必要な行動面\n\n"
                . "■ 言語・コミュニケーション\n- 言語理解（指示理解、質問理解等）と言語表出（発語、文章構成等）\n- 非言語コミュニケーション（視線、ジェスチャー、表情等）\n- コミュニケーション意欲や手段の変化\n\n"
                . "■ 人間関係・社会性\n- 他者への関心、対人距離、集団参加の様子\n- 感情表現、感情調整、共感性の発達\n- 友達関係、協調性、ルール理解の状況\n\n"
                . "**重要**: 以下のJSON形式のみを出力してください。各領域300文字程度で、観察事実に基づく具体的な内容を記述してください。\n"
                . "{\n"
                . "  \"health_life\": \"健康・生活の課題（300文字程度、具体的な観察事実と支援方針）\",\n"
                . "  \"motor_sensory\": \"運動・感覚の課題（300文字程度、具体的な観察事実と支援方針）\",\n"
                . "  \"cognitive_behavior\": \"認知・行動の課題（300文字程度、具体的な観察事実と支援方針）\",\n"
                . "  \"language_communication\": \"言語・コミュニケーションの課題（300文字程度、具体的な観察事実と支援方針）\",\n"
                . "  \"social_relations\": \"人間関係・社会性の課題（300文字程度、具体的な観察事実と支援方針）\"\n"
                . "}";

            $domainsResponse = $client->chat()->create([
                'model'    => $aiModel,
                'messages' => [
                    ['role' => 'user', 'content' => $domainsPrompt],
                ],
                'response_format'       => ['type' => 'json_object'],
                'temperature'           => 0.6,
                'max_completion_tokens' => 2500,
            ]);

            $totalInputTokens += $domainsResponse->usage->promptTokens ?? 0;
            $totalOutputTokens += $domainsResponse->usage->completionTokens ?? 0;

            $domainsContent = $domainsResponse->choices[0]->message->content;
            $domainsData = json_decode($domainsContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('OpenAI domains response parse failed', ['response' => $domainsContent]);
                throw new \Exception('五領域の課題生成に失敗しました。');
            }

            // === 2. 短期目標を生成 ===
            $shortTermPrompt = "以下の五領域の課題分析と面談記録から、今後6か月間の短期目標を300文字程度で作成してください。\n\n"
                . "【五領域の課題】\n"
                . "■健康・生活\n" . ($domainsData['health_life'] ?? '') . "\n\n"
                . "■運動・感覚\n" . ($domainsData['motor_sensory'] ?? '') . "\n\n"
                . "■認知・行動\n" . ($domainsData['cognitive_behavior'] ?? '') . "\n\n"
                . "■言語・コミュニケーション\n" . ($domainsData['language_communication'] ?? '') . "\n\n"
                . "■人間関係・社会性\n" . ($domainsData['social_relations'] ?? '') . "";

            if (!empty(trim($interviewSummary))) {
                $shortTermPrompt .= "\n\n【面談記録からの情報】\n" . $interviewSummary;
            }

            $shortTermPrompt .= "\n\n【短期目標作成の要点】\n"
                . "- 各領域の課題を総合的に考慮し、優先度の高いものから記述\n"
                . "- 面談で把握した学校での様子、家庭での様子、困りごと・悩みを考慮する\n"
                . "- 児童の願いや希望を尊重した目標設定を心がける\n"
                . "- 具体的で測定可能な目標（例：「〜ができるようになる」）\n"
                . "- 6か月後に達成可能な現実的な目標設定\n"
                . "- 本人の強みや興味を活かした支援の視点\n\n"
                . "短期目標（6か月）を300文字程度の文章で記述してください（JSON不要、テキストのみ）：";

            $shortTermResponse = $client->chat()->create([
                'model'    => $aiModel,
                'messages' => [
                    ['role' => 'user', 'content' => $shortTermPrompt],
                ],
                'temperature'           => 0.6,
                'max_completion_tokens' => 800,
            ]);

            $totalInputTokens += $shortTermResponse->usage->promptTokens ?? 0;
            $totalOutputTokens += $shortTermResponse->usage->completionTokens ?? 0;
            $shortTermGoal = trim($shortTermResponse->choices[0]->message->content);

            // === 3. 長期目標を生成 ===
            $longTermPrompt = "以下の情報から、今後1年以上を見据えた長期目標を350文字程度で作成してください。\n\n"
                . "【短期目標（今後6か月）】\n{$shortTermGoal}";

            if (!empty(trim($interviewSummary))) {
                $longTermPrompt .= "\n\n【面談記録からの情報】\n" . $interviewSummary;
            }

            if ($previousLongTermGoal || $previousShortTermGoal) {
                $longTermPrompt .= "\n\n【前回の長期目標】\n" . $previousLongTermGoal
                    . "\n\n【長期目標作成の要点】\n"
                    . "- 前回の長期目標からの成長や変化を考慮\n"
                    . "- 短期目標の達成を踏まえた次のステップ\n"
                    . "- 面談で把握した学校での様子、家庭での様子、困りごと・悩みを踏まえる\n"
                    . "- 児童の願いや希望を長期的な視点で実現できるよう目標に反映する\n"
                    . "- 1年後～数年後の本人の姿を具体的にイメージ\n"
                    . "- 社会参加や自立に向けた視点を含める\n"
                    . "- 本人の可能性を信じた前向きな目標設定\n\n"
                    . "前回の長期目標を参考にしつつ、短期目標の達成と面談記録の内容を加味して、より発展的な長期目標を350文字程度で記述してください。";
            } else {
                $longTermPrompt .= "\n\n【長期目標作成の要点】\n"
                    . "- 短期目標の延長線上にある1年後～数年後の姿\n"
                    . "- 面談で把握した学校での様子、家庭での様子、困りごと・悩みを踏まえる\n"
                    . "- 児童の願いや希望を長期的な視点で実現できるよう目標に反映する\n"
                    . "- 生活の質（QOL）向上や社会参加の視点\n"
                    . "- 本人の特性や強みを活かした自立への道筋\n"
                    . "- 将来の進路や生活場面を見据えた具体的な目標\n"
                    . "- 本人と家族が希望する将来像\n\n"
                    . "短期目標と面談記録の内容を踏まえて、1年以上先を見据えた長期的な成長目標を350文字程度で記述してください。";
            }

            $longTermPrompt .= "\n\n長期目標（1年以上）を文章で記述してください（JSON不要、テキストのみ）：";

            $longTermResponse = $client->chat()->create([
                'model'    => $aiModel,
                'messages' => [
                    ['role' => 'user', 'content' => $longTermPrompt],
                ],
                'temperature'           => 0.6,
                'max_completion_tokens' => 900,
            ]);

            $totalInputTokens += $longTermResponse->usage->promptTokens ?? 0;
            $totalOutputTokens += $longTermResponse->usage->completionTokens ?? 0;
            $longTermGoal = trim($longTermResponse->choices[0]->message->content);

            // ログ保存
            try {
                AiGenerationLog::create([
                    'user_id'       => $request->user()->id,
                    'model'         => $aiModel,
                    'prompt_type'   => 'kakehashi',
                    'input_tokens'  => $totalInputTokens,
                    'output_tokens' => $totalOutputTokens,
                    'student_id'    => $student->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to log AI generation', ['error' => $e->getMessage()]);
            }

            $result = [
                'student_wish'           => '',
                'short_term_goal'        => $shortTermGoal,
                'long_term_goal'         => $longTermGoal,
                'health_life'            => $domainsData['health_life'] ?? '',
                'motor_sensory'          => $domainsData['motor_sensory'] ?? '',
                'cognitive_behavior'     => $domainsData['cognitive_behavior'] ?? '',
                'language_communication' => $domainsData['language_communication'] ?? '',
                'social_relations'       => $domainsData['social_relations'] ?? '',
            ];

            return response()->json([
                'success'      => true,
                'data'         => $result,
                'record_count' => $records->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 保護者入力かけはしの表示/非表示を切り替え
     */
    public function toggleGuardianHidden(Request $request, KakehashiPeriod $period): JsonResponse
    {
        $period->load('student');

        if (! $period->student) {
            return response()->json(['success' => false, 'message' => '期間が見つかりません。'], 404);
        }

        $this->authorizeClassroom($request->user(), $period->student);

        $entry = KakehashiGuardian::where('period_id', $period->id)
            ->where('student_id', $period->student_id)
            ->first();

        if (! $entry) {
            return response()->json(['success' => false, 'message' => 'かけはしが見つかりませんでした。'], 404);
        }

        $entry->update([
            'is_hidden' => ! $entry->is_hidden,
        ]);

        $message = $entry->is_hidden
            ? '保護者用かけはしを非表示にしました。'
            : '保護者用かけはしを再表示しました。';

        return response()->json([
            'success' => true,
            'data'    => $entry,
            'message' => $message,
        ]);
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
