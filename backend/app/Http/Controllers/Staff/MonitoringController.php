<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MonitoringController extends Controller
{
    /**
     * 生徒のモニタリング記録一覧を取得
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $records = $student->monitoringRecords()
            ->with(['details', 'plan.details'])
            ->orderByDesc('monitoring_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * モニタリング記録を新規作成
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $validated = $request->validate([
            'plan_id'                        => 'required|exists:individual_support_plans,id',
            'monitoring_date'                => 'required|date',
            'overall_comment'                => 'nullable|string',
            'short_term_goal_achievement'    => 'nullable|string',
            'long_term_goal_achievement'     => 'nullable|string',
            'is_official'                    => 'boolean',
            'details'                        => 'nullable|array',
            'details.*.domain'               => 'nullable|string',
            'details.*.achievement_level'    => 'nullable|string',
            'details.*.comment'              => 'nullable|string',
            'details.*.next_action'          => 'nullable|string',
            'details.*.sort_order'           => 'nullable|integer',
        ]);

        $record = DB::transaction(function () use ($request, $student, $validated) {
            $record = MonitoringRecord::create([
                'plan_id'                     => $validated['plan_id'],
                'student_id'                  => $student->id,
                'classroom_id'                => $student->classroom_id,
                'monitoring_date'             => $validated['monitoring_date'],
                'overall_comment'             => $validated['overall_comment'] ?? null,
                'short_term_goal_achievement' => $validated['short_term_goal_achievement'] ?? null,
                'long_term_goal_achievement'  => $validated['long_term_goal_achievement'] ?? null,
                'is_official'                 => $validated['is_official'] ?? false,
                'created_by'                  => $request->user()->id,
            ]);

            if (! empty($validated['details'])) {
                foreach ($validated['details'] as $index => $detail) {
                    MonitoringDetail::create([
                        'monitoring_id'      => $record->id,
                        'domain'             => $detail['domain'] ?? null,
                        'achievement_level'  => $detail['achievement_level'] ?? null,
                        'comment'            => $detail['comment'] ?? null,
                        'next_action'        => $detail['next_action'] ?? null,
                        'sort_order'         => $detail['sort_order'] ?? $index,
                    ]);
                }
            }

            return $record;
        });

        return response()->json([
            'success' => true,
            'data'    => $record->load('details'),
            'message' => 'モニタリング記録を作成しました。',
        ], 201);
    }

    /**
     * モニタリング記録を更新
     */
    public function update(Request $request, MonitoringRecord $monitoring): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $monitoring->student);

        $validated = $request->validate([
            'monitoring_date'                => 'sometimes|date',
            'overall_comment'                => 'nullable|string',
            'short_term_goal_achievement'    => 'nullable|string',
            'long_term_goal_achievement'     => 'nullable|string',
            'is_official'                    => 'boolean',
            'details'                        => 'nullable|array',
            'details.*.domain'               => 'nullable|string',
            'details.*.achievement_level'    => 'nullable|string',
            'details.*.comment'              => 'nullable|string',
            'details.*.next_action'          => 'nullable|string',
            'details.*.sort_order'           => 'nullable|integer',
        ]);

        DB::transaction(function () use ($monitoring, $validated) {
            $monitoring->update(collect($validated)->except('details')->toArray());

            if (isset($validated['details'])) {
                $monitoring->details()->delete();

                foreach ($validated['details'] as $index => $detail) {
                    MonitoringDetail::create([
                        'monitoring_id'      => $monitoring->id,
                        'domain'             => $detail['domain'] ?? null,
                        'achievement_level'  => $detail['achievement_level'] ?? null,
                        'comment'            => $detail['comment'] ?? null,
                        'next_action'        => $detail['next_action'] ?? null,
                        'sort_order'         => $detail['sort_order'] ?? $index,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $monitoring->fresh('details'),
            'message' => 'モニタリング記録を更新しました。',
        ]);
    }

    /**
     * AI でモニタリング評価を生成
     * 各目標に対して過去6ヶ月の連絡帳記録から評価コメントを自動生成
     */
    public function generateAi(Request $request, MonitoringRecord $monitoring): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $monitoring->student);

        $monitoring->load(['plan.details', 'student']);
        $student = $monitoring->student;
        $planDetails = $monitoring->plan->details;

        if ($planDetails->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '計画の明細が見つかりません。',
            ], 422);
        }

        // 過去6ヶ月の連絡帳データを取得
        $sixMonthsAgo = now()->subMonths(6)->toDateString();
        $studentRecords = StudentRecord::where('student_id', $student->id)
            ->whereHas('dailyRecord', function ($q) use ($sixMonthsAgo) {
                $q->where('record_date', '>=', $sixMonthsAgo);
            })
            ->with('dailyRecord:id,record_date,activity_name,common_activity')
            ->orderByDesc('id')
            ->get();

        // 5領域ごとに記録をグループ化
        $recordsByDomain = $this->groupRecordsByDomain($studentRecords);

        $generatedEvaluations = [];

        foreach ($planDetails as $detail) {
            if (empty($detail->goal)) {
                $generatedEvaluations[$detail->id] = [
                    'achievement_status' => '',
                    'monitoring_comment' => '支援目標が設定されていないため、評価を生成できません。',
                ];
                continue;
            }

            $relatedRecords = $this->getRelatedRecords($recordsByDomain, $detail->domain, $detail->domain);

            if (empty($relatedRecords)) {
                $generatedEvaluations[$detail->id] = [
                    'achievement_status' => '',
                    'monitoring_comment' => '過去6ヶ月間にこの分野に関連する記録がありませんでした。',
                ];
                continue;
            }

            // AI で評価生成
            $recordsText = collect($relatedRecords)->map(function ($r, $i) {
                $text = ($i + 1) . ". [{$r['date']}] 活動: {$r['activity']}";
                if (! empty($r['content'])) {
                    $text .= "\n   記録: {$r['content']}";
                }
                return $text;
            })->implode("\n");

            try {
                $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
                if (empty($apiKey)) {
                    $generatedEvaluations[$detail->id] = ['achievement_status' => '', 'monitoring_comment' => 'OpenAI APIキーが未設定です。'];
                    continue;
                }

                $client = \OpenAI::client($apiKey);

                // カテゴリ/サブカテゴリ情報（旧システム互換）
                $category = $detail->category ?? $detail->domain ?? '';
                $subCategory = $detail->sub_category ?? $detail->domain ?? '';
                $supportGoal = $detail->support_goal ?? $detail->goal ?? '';
                $supportContent = $detail->support_content ?? '';

                $response = $client->chat()->create([
                    'model'    => 'gpt-4o',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'あなたは個別支援教育の経験豊富な児童発達支援管理責任者です。'
                                . 'モニタリング評価を専門的かつ保護者にも分かりやすく行います。'
                                . '指定された形式でのみ回答してください。',
                        ],
                        [
                            'role'    => 'user',
                            'content' => "以下の支援目標に対して、過去6ヶ月間の連絡帳記録（" . count($relatedRecords) . "件）を分析し、モニタリング評価を行ってください。\n\n"
                                . "【児童氏名】{$student->student_name}\n"
                                . "【支援目標の分野】{$category} > {$subCategory}\n"
                                . "【支援目標】{$supportGoal}\n"
                                . "【支援内容（施設での取り組み）】{$supportContent}\n\n"
                                . "【過去6ヶ月間の連絡帳記録（この分野に関する記録）】\n{$recordsText}\n\n"
                                . "【評価の観点】\n"
                                . "1. 上記の連絡帳記録から、支援目標に対する子どもの取り組みや変化を読み取ってください\n"
                                . "2. 具体的なエピソードや行動を踏まえて評価してください\n"
                                . "3. 支援内容が適切に実施されているか、効果が出ているかを判断してください\n\n"
                                . "【出力形式】JSON only:\n"
                                . "{\"achievement_status\": \"達成状況（「達成」「進行中」「未着手」「継続中」「見直し必要」のいずれか）\", "
                                . "\"monitoring_comment\": \"評価コメント（150〜200字程度。連絡帳の記録を踏まえた具体的な評価と、今後の支援の方向性を含める）\"}",
                        ],
                    ],
                    'response_format'       => ['type' => 'json_object'],
                    'temperature'           => 0.5,
                    'max_completion_tokens' => 800,
                ]);

                $content = $response->choices[0]->message->content;
                if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                    $content = trim($matches[1]);
                }

                $result = json_decode($content, true);
                $generatedEvaluations[$detail->id] = [
                    'achievement_status'  => $result['achievement_status'] ?? $result['achievement_level'] ?? '',
                    'monitoring_comment'  => $result['monitoring_comment'] ?? $result['comment'] ?? $content,
                ];
            } catch (\Exception $e) {
                $generatedEvaluations[$detail->id] = [
                    'achievement_status'  => '',
                    'monitoring_comment'  => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $generatedEvaluations,
        ]);
    }

    /**
     * フロント用: plan_id + student_id からAI評価を一括生成
     * 既存のgenerateAi()を内部的に利用
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'plan_id'    => 'required|exists:individual_support_plans,id',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->authorizeClassroom($request->user(), $student);

        $plan = \App\Models\IndividualSupportPlan::with('details')->findOrFail($validated['plan_id']);

        // MonitoringRecord があれば取得、なければダミーで生成
        $monitoring = MonitoringRecord::where('student_id', $student->id)
            ->where('plan_id', $plan->id)
            ->first();

        if (!$monitoring) {
            // 既存のモニタリングレコードがなくても計画の詳細からAI生成
            $monitoring = new MonitoringRecord();
            $monitoring->student_id = $student->id;
            $monitoring->plan_id = $plan->id;
            $monitoring->setRelation('plan', $plan);
            $monitoring->setRelation('student', $student);
        }

        // generateAi を呼び出し
        $fakeRequest = Request::create('', 'POST');
        $fakeRequest->setUserResolver(fn () => $request->user());
        $response = $this->generateAi($fakeRequest, $monitoring);
        $evaluations = json_decode($response->getContent(), true)['data'] ?? [];

        // フロント期待の形式に変換: details配列 + overall
        $details = [];
        foreach ($plan->details as $detail) {
            $eval = $evaluations[$detail->id] ?? [];
            $details[] = [
                'id'                  => $detail->id,
                'category'            => $detail->category ?? $detail->domain ?? '',
                'sub_category'        => $detail->sub_category ?? '',
                'support_goal'        => $detail->support_goal ?? $detail->goal ?? '',
                'support_content'     => $detail->support_content ?? '',
                'achievement_status'  => $eval['achievement_status'] ?? '',
                'monitoring_comment'  => $eval['monitoring_comment'] ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'details'              => $details,
                'overall_assessment'   => '各目標の達成状況を総合すると、全体として着実な成長が見られます。',
                'next_plan_direction'  => '引き続き個別の課題に応じた支援を継続し、新たな目標設定を検討してください。',
            ],
        ]);
    }

    private function groupRecordsByDomain($records): array
    {
        $domainFields = [
            'health_life'            => '健康・生活',
            'motor_sensory'          => '運動・感覚',
            'cognitive_behavior'     => '認知・行動',
            'language_communication' => '言語・コミュニケーション',
            'social_relations'       => '人間関係・社会性',
        ];

        $grouped = [];

        foreach ($records as $record) {
            foreach ($domainFields as $field => $label) {
                $content = $record->{$field};
                if (empty($content)) {
                    continue;
                }

                $grouped[$label][] = [
                    'date'     => $record->dailyRecord->record_date ?? '',
                    'activity' => $record->dailyRecord->activity_name ?? '',
                    'content'  => $content,
                    'note'     => $record->notes ?? '',
                ];
            }
        }

        return $grouped;
    }

    private function getRelatedRecords(array $recordsByDomain, ?string $category, ?string $subCategory): array
    {
        $mapping = [
            '健康・生活' => '健康・生活', '生活習慣' => '健康・生活',
            '運動・感覚' => '運動・感覚', '運動' => '運動・感覚', '感覚' => '運動・感覚',
            '認知・行動' => '認知・行動', '学習' => '認知・行動', '認知' => '認知・行動',
            '言語・コミュニケーション' => '言語・コミュニケーション', 'コミュニケーション' => '言語・コミュニケーション',
            '人間関係・社会性' => '人間関係・社会性', '社会性' => '人間関係・社会性', '人間関係' => '人間関係・社会性',
        ];

        $matchedDomains = [];
        foreach ($mapping as $keyword => $domain) {
            if (mb_strpos($subCategory ?? '', $keyword) !== false) {
                $matchedDomains[] = $domain;
            }
        }
        $matchedDomains = array_unique($matchedDomains);

        $related = [];
        foreach ($matchedDomains as $domain) {
            if (isset($recordsByDomain[$domain])) {
                $related = array_merge($related, $recordsByDomain[$domain]);
            }
        }

        // 日付でソートして最新20件
        usort($related, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($related, 0, 20);
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
