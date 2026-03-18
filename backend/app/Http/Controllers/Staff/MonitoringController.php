<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Services\PuppeteerPdfService;
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

        $query = $student->monitoringRecords()
            ->with(['details', 'plan.details'])
            ->orderByDesc('monitoring_date');

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        $records = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * モニタリング記録詳細を取得
     */
    public function show(Request $request, MonitoringRecord $monitoring): JsonResponse
    {
        $monitoring->load(['details', 'plan.details', 'student', 'creator']);

        if ($monitoring->student) {
            $this->authorizeClassroom($request->user(), $monitoring->student);
        }

        return response()->json([
            'success' => true,
            'data'    => $monitoring,
        ]);
    }

    /**
     * モニタリング記録を新規作成
     * Legacy互換: plan_detail_id, achievement_level, comment, is_draft, student_name,
     *             short_term_goal_comment, long_term_goal_comment, signature fields
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $validated = $request->validate([
            'plan_id'                        => 'required|exists:individual_support_plans,id',
            'monitoring_date'                => 'required|date',
            'overall_comment'                => 'nullable|string',
            'short_term_goal_achievement'    => 'nullable|string',
            'short_term_goal_comment'        => 'nullable|string',
            'long_term_goal_achievement'     => 'nullable|string',
            'long_term_goal_comment'         => 'nullable|string',
            'is_draft'                       => 'nullable|boolean',
            'staff_signature'                => 'nullable|string',
            'staff_signature_date'           => 'nullable|date',
            'staff_signer_name'              => 'nullable|string',
            'details'                        => 'nullable|array',
            'details.*.plan_detail_id'       => 'nullable|integer',
            'details.*.achievement_level'    => 'nullable|string',
            'details.*.comment'              => 'nullable|string',
            'details.*.domain'               => 'nullable|string',
            'details.*.next_action'          => 'nullable|string',
            'details.*.sort_order'           => 'nullable|integer',
        ]);

        // 計画書から生徒名を取得
        $plan = IndividualSupportPlan::where('id', $validated['plan_id'])
            ->where('student_id', $student->id)
            ->firstOrFail();

        // 退所日チェック
        if ($student->withdrawal_date) {
            $withdrawalDate = new \DateTime($student->withdrawal_date);
            $recordDate = new \DateTime($validated['monitoring_date']);
            if ($recordDate >= $withdrawalDate) {
                return response()->json([
                    'success' => false,
                    'message' => "退所日（{$student->withdrawal_date}）以降のモニタリング表は作成できません。",
                ], 422);
            }
        }

        $record = DB::transaction(function () use ($request, $student, $plan, $validated) {
            $isDraft = $validated['is_draft'] ?? true;

            // 署名データのバリデーション（data:imageで始まらない場合は無視）
            $staffSignature = $validated['staff_signature'] ?? null;
            if ($staffSignature && strpos($staffSignature, 'data:image') !== 0) {
                $staffSignature = null;
            }

            $record = MonitoringRecord::create([
                'plan_id'                     => $validated['plan_id'],
                'student_id'                  => $student->id,
                'student_name'                => $plan->student_name ?? $student->student_name,
                'classroom_id'                => $student->classroom_id,
                'monitoring_date'             => $validated['monitoring_date'],
                'overall_comment'             => $validated['overall_comment'] ?? null,
                'short_term_goal_achievement' => $validated['short_term_goal_achievement'] ?? null,
                'short_term_goal_comment'     => $validated['short_term_goal_comment'] ?? null,
                'long_term_goal_achievement'  => $validated['long_term_goal_achievement'] ?? null,
                'long_term_goal_comment'      => $validated['long_term_goal_comment'] ?? null,
                'is_draft'                    => $isDraft,
                'is_official'                 => ! $isDraft,
                'staff_signature'             => $staffSignature,
                'staff_signature_date'        => $validated['staff_signature_date'] ?? null,
                'staff_signer_name'           => $validated['staff_signer_name'] ?? null,
                'created_by'                  => $request->user()->id,
            ]);

            if (! empty($validated['details'])) {
                foreach ($validated['details'] as $index => $detail) {
                    // 達成状況もコメントも空の場合はスキップ（legacy互換）
                    if (empty($detail['achievement_level']) && empty($detail['comment'])) {
                        continue;
                    }

                    MonitoringDetail::create([
                        'monitoring_id'      => $record->id,
                        'plan_detail_id'     => $detail['plan_detail_id'] ?? null,
                        'achievement_level'  => $detail['achievement_level'] ?? '',
                        'comment'            => $detail['comment'] ?? '',
                        'domain'             => $detail['domain'] ?? null,
                        'next_action'        => $detail['next_action'] ?? null,
                        'sort_order'         => $detail['sort_order'] ?? $index,
                    ]);
                }
            }

            return $record;
        });

        $message = $record->is_draft
            ? 'モニタリング表を下書き保存しました。（保護者には非公開）'
            : 'モニタリング表を提出しました。（保護者にも公開）';

        return response()->json([
            'success' => true,
            'data'    => $record->load('details'),
            'message' => $message,
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
            'short_term_goal_comment'        => 'nullable|string',
            'long_term_goal_achievement'     => 'nullable|string',
            'long_term_goal_comment'         => 'nullable|string',
            'is_draft'                       => 'nullable|boolean',
            'staff_signature'                => 'nullable|string',
            'staff_signature_date'           => 'nullable|date',
            'staff_signer_name'              => 'nullable|string',
            'details'                        => 'nullable|array',
            'details.*.plan_detail_id'       => 'nullable|integer',
            'details.*.achievement_level'    => 'nullable|string',
            'details.*.comment'              => 'nullable|string',
            'details.*.domain'               => 'nullable|string',
            'details.*.next_action'          => 'nullable|string',
            'details.*.sort_order'           => 'nullable|integer',
        ]);

        DB::transaction(function () use ($monitoring, $validated) {
            $updateData = collect($validated)->except('details')->toArray();

            // 署名データのバリデーション
            if (isset($updateData['staff_signature'])) {
                if (strpos($updateData['staff_signature'], 'data:image') !== 0) {
                    unset($updateData['staff_signature']);
                }
            }

            // is_draft -> is_official同期
            if (isset($updateData['is_draft'])) {
                $updateData['is_official'] = ! $updateData['is_draft'];
            }

            $monitoring->update($updateData);

            if (isset($validated['details'])) {
                // 既存の明細を削除して再作成（legacy互換）
                $monitoring->details()->delete();

                foreach ($validated['details'] as $index => $detail) {
                    // 達成状況もコメントも空の場合はスキップ
                    if (empty($detail['achievement_level']) && empty($detail['comment'])) {
                        continue;
                    }

                    MonitoringDetail::create([
                        'monitoring_id'      => $monitoring->id,
                        'plan_detail_id'     => $detail['plan_detail_id'] ?? null,
                        'achievement_level'  => $detail['achievement_level'] ?? '',
                        'comment'            => $detail['comment'] ?? '',
                        'domain'             => $detail['domain'] ?? null,
                        'next_action'        => $detail['next_action'] ?? null,
                        'sort_order'         => $detail['sort_order'] ?? $index,
                    ]);
                }
            }
        });

        $message = $monitoring->is_draft
            ? 'モニタリング表を下書き保存しました。（保護者には非公開）'
            : 'モニタリング表を提出しました。（保護者にも公開）';

        return response()->json([
            'success' => true,
            'data'    => $monitoring->fresh('details'),
            'message' => $message,
        ]);
    }

    /**
     * モニタリング記録を削除
     */
    public function destroy(Request $request, MonitoringRecord $monitoring): JsonResponse
    {
        if ($monitoring->student) {
            $this->authorizeClassroom($request->user(), $monitoring->student);
        }

        $monitoring->details()->delete();
        $monitoring->delete();

        return response()->json([
            'success' => true,
            'message' => 'モニタリング表を削除しました。',
        ]);
    }

    /**
     * 電子署名を保存
     */
    public function sign(Request $request, MonitoringRecord $monitoring): JsonResponse
    {
        if ($monitoring->student) {
            $this->authorizeClassroom($request->user(), $monitoring->student);
        }

        $validated = $request->validate([
            'staff_signature'       => 'nullable|string',
            'staff_signer_name'     => 'nullable|string',
            'staff_signature_date'  => 'nullable|date',
        ]);

        $updateData = [];

        if (! empty($validated['staff_signature']) && strpos($validated['staff_signature'], 'data:image') === 0) {
            $updateData['staff_signature'] = $validated['staff_signature'];
            $updateData['staff_signer_name'] = $validated['staff_signer_name'] ?? null;
            $updateData['staff_signature_date'] = $validated['staff_signature_date'] ?? now()->toDateString();
        }

        if (! empty($updateData)) {
            $monitoring->update($updateData);
        }

        return response()->json([
            'success' => true,
            'data'    => $monitoring->fresh(),
            'message' => '署名を保存しました。',
        ]);
    }

    /**
     * モニタリング記録 PDF をダウンロード
     */
    public function pdf(Request $request, MonitoringRecord $monitoring)
    {
        $monitoring->load(['student.classroom', 'details', 'plan.details', 'creator']);

        if ($monitoring->student) {
            $this->authorizeClassroom($request->user(), $monitoring->student);
        }

        $filename = 'monitoring_' . ($monitoring->student->student_name ?? $monitoring->id) . '_' . $monitoring->monitoring_date->format('Y-m-d') . '.pdf';

        return PuppeteerPdfService::download('pdf.monitoring', [
            'record'    => $monitoring,
            'student'   => $monitoring->student,
            'classroom' => $monitoring->student->classroom ?? null,
            'plan'      => $monitoring->plan,
            'details'   => $monitoring->details,
        ], $filename);
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

        // 特定の目標のみ生成する場合
        $detailId = $request->input('detail_id');

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

        // 特定の目標のみの場合はフィルタリング
        $targetDetails = $detailId
            ? $planDetails->where('id', $detailId)
            : $planDetails;

        foreach ($targetDetails as $detail) {
            $supportGoal = $detail->support_goal ?? $detail->goal ?? '';

            if (empty($supportGoal)) {
                $generatedEvaluations[$detail->id] = [
                    'achievement_status' => '',
                    'monitoring_comment' => '※ 支援目標が設定されていないため、評価を生成できません。',
                ];
                continue;
            }

            $category = $detail->category ?? $detail->domain ?? '';
            $subCategory = $detail->sub_category ?? '';
            $relatedRecords = $this->getRelatedRecords($recordsByDomain, $category, $subCategory);

            if (empty($relatedRecords)) {
                $generatedEvaluations[$detail->id] = [
                    'achievement_status' => '',
                    'monitoring_comment' => '※ 過去6ヶ月間にこの分野に関連する記録がありませんでした。手動で評価を入力してください。',
                ];
                continue;
            }

            // AI で評価生成
            $recordsText = '';
            foreach ($relatedRecords as $index => $r) {
                $date = date('Y/m/d', strtotime($r['date']));
                $recordsText .= ($index + 1) . ". [{$date}] 活動: {$r['activity']}\n";
                if (! empty($r['content'])) {
                    $recordsText .= "   この領域での記録: {$r['content']}\n";
                }
                if (! empty($r['common_activity'])) {
                    $commonShort = mb_substr($r['common_activity'], 0, 150);
                    $recordsText .= "   活動の様子: {$commonShort}\n";
                }
                if (! empty($r['note'])) {
                    $noteShort = mb_substr($r['note'], 0, 100);
                    $recordsText .= "   個別メモ: {$noteShort}\n";
                }
                $recordsText .= "\n";
            }

            $supportContent = $detail->support_content ?? '';
            $recordCount = count($relatedRecords);

            try {
                $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
                if (empty($apiKey)) {
                    $generatedEvaluations[$detail->id] = [
                        'achievement_status' => '',
                        'monitoring_comment' => '※ ChatGPT APIキーが設定されていないため、自動生成できません。手動で入力してください。',
                    ];
                    continue;
                }

                $client = \OpenAI::client($apiKey);

                $prompt = "あなたは児童発達支援施設の児童発達支援管理責任者です。\n"
                    . "以下の支援目標に対して、過去6ヶ月間の連絡帳記録（{$recordCount}件）を分析し、モニタリング評価を行ってください。\n\n"
                    . "【児童氏名】\n{$student->student_name}\n\n"
                    . "【支援目標の分野】\n{$category} > {$subCategory}\n\n"
                    . "【支援目標】\n{$supportGoal}\n\n"
                    . "【支援内容（施設での取り組み）】\n{$supportContent}\n\n"
                    . "【過去6ヶ月間の連絡帳記録（この分野に関する記録）】\n{$recordsText}\n\n"
                    . "【評価の観点】\n"
                    . "1. 上記の連絡帳記録から、支援目標に対する子どもの取り組みや変化を読み取ってください\n"
                    . "2. 具体的なエピソードや行動を踏まえて評価してください\n"
                    . "3. 支援内容が適切に実施されているか、効果が出ているかを判断してください\n\n"
                    . "【出力形式】\n"
                    . "以下の形式でJSONのみを出力してください。他の文字は一切出力しないでください。\n\n"
                    . "{\"achievement_status\": \"達成状況（「達成」「進行中」「未着手」「継続中」「見直し必要」のいずれか）\", "
                    . "\"monitoring_comment\": \"評価コメント（150〜200字程度。連絡帳の記録を踏まえた具体的な評価と、今後の支援の方向性を含める）\"}";

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
                            'content' => $prompt,
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
                    'achievement_status'  => $result['achievement_status'] ?? '',
                    'monitoring_comment'  => $result['monitoring_comment'] ?? $content,
                ];
            } catch (\Exception $e) {
                $generatedEvaluations[$detail->id] = [
                    'achievement_status'  => '',
                    'monitoring_comment'  => '※ AI生成中にエラーが発生しました: ' . $e->getMessage(),
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
     * Legacy互換: detail_id指定で個別生成にも対応
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'plan_id'    => 'required|exists:individual_support_plans,id',
            'detail_id'  => 'nullable|integer',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->authorizeClassroom($request->user(), $student);

        $plan = IndividualSupportPlan::with('details')->findOrFail($validated['plan_id']);

        // MonitoringRecord があれば取得、なければダミーで生成
        $monitoring = MonitoringRecord::where('student_id', $student->id)
            ->where('plan_id', $plan->id)
            ->first();

        if (! $monitoring) {
            $monitoring = new MonitoringRecord();
            $monitoring->student_id = $student->id;
            $monitoring->plan_id = $plan->id;
            $monitoring->setRelation('plan', $plan);
            $monitoring->setRelation('student', $student);
        }

        // detail_idを渡してgenerateAiを呼び出し
        $fakeRequest = Request::create('', 'POST');
        $fakeRequest->setUserResolver(fn () => $request->user());
        if (isset($validated['detail_id'])) {
            $fakeRequest->merge(['detail_id' => $validated['detail_id']]);
        }

        $response = $this->generateAi($fakeRequest, $monitoring);
        $responseData = json_decode($response->getContent(), true);

        return response()->json($responseData);
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
            // domain1/domain2ベースのグループ化（legacy互換）
            if (! empty($record->domain1)) {
                $domainKey = trim($record->domain1);
                $domain = $domainFields[$domainKey] ?? $domainKey;
                $grouped[$domain][] = [
                    'date'            => $record->dailyRecord->record_date ?? '',
                    'activity'        => $record->dailyRecord->activity_name ?? '',
                    'content'         => $record->domain1_content ?? '',
                    'note'            => $record->notes ?? '',
                    'common_activity' => $record->dailyRecord->common_activity ?? '',
                ];
            }

            if (! empty($record->domain2)) {
                $domainKey = trim($record->domain2);
                $domain = $domainFields[$domainKey] ?? $domainKey;
                $grouped[$domain][] = [
                    'date'            => $record->dailyRecord->record_date ?? '',
                    'activity'        => $record->dailyRecord->activity_name ?? '',
                    'content'         => $record->domain2_content ?? '',
                    'note'            => $record->notes ?? '',
                    'common_activity' => $record->dailyRecord->common_activity ?? '',
                ];
            }

            // 5領域カラムベースのグループ化（新システム互換）
            foreach ($domainFields as $field => $label) {
                $content = $record->{$field} ?? null;
                if (empty($content)) {
                    continue;
                }

                $grouped[$label][] = [
                    'date'            => $record->dailyRecord->record_date ?? '',
                    'activity'        => $record->dailyRecord->activity_name ?? '',
                    'content'         => $content,
                    'note'            => $record->notes ?? '',
                    'common_activity' => $record->dailyRecord->common_activity ?? '',
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
            '言語・コミュニケーション' => '言語・コミュニケーション', 'コミュニケーション' => '言語・コミュニケーション', '言語' => '言語・コミュニケーション',
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

        // 重複を除去
        $unique = [];
        $seen = [];
        foreach ($related as $record) {
            $key = $record['date'] . '|' . ($record['content'] ?? '');
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $record;
            }
        }

        // 日付でソートして最新20件
        usort($unique, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($unique, 0, 20);
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
