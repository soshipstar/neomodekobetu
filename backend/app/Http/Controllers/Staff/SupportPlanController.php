<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\SupportPlanDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class SupportPlanController extends Controller
{
    /**
     * 生徒の支援計画一覧を取得
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $plans = $student->supportPlans()
            ->with('details')
            ->orderByDesc('created_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 支援計画を新規作成
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $validated = $request->validate([
            'created_date'       => 'required|date',
            'life_intention'     => 'nullable|string',
            'overall_policy'     => 'nullable|string',
            'long_term_goal'     => 'nullable|string',
            'short_term_goal'    => 'nullable|string',
            'consent_date'       => 'nullable|date',
            'status'             => 'nullable|string|in:draft,submitted,official',
            'details'            => 'nullable|array',
            'details.*.domain'           => 'nullable|string',
            'details.*.current_status'   => 'nullable|string',
            'details.*.goal'             => 'nullable|string',
            'details.*.support_content'  => 'nullable|string',
            'details.*.achievement_status' => 'nullable|string',
        ]);

        $plan = DB::transaction(function () use ($request, $student, $validated) {
            $plan = IndividualSupportPlan::create([
                'student_id'     => $student->id,
                'classroom_id'   => $student->classroom_id,
                'student_name'   => $student->student_name,
                'created_date'   => $validated['created_date'],
                'life_intention' => $validated['life_intention'] ?? null,
                'overall_policy' => $validated['overall_policy'] ?? null,
                'long_term_goal' => $validated['long_term_goal'] ?? null,
                'short_term_goal' => $validated['short_term_goal'] ?? null,
                'consent_date'   => $validated['consent_date'] ?? null,
                'status'         => $validated['status'] ?? 'draft',
                'is_official'    => ($validated['status'] ?? 'draft') === 'official',
                'created_by'     => $request->user()->id,
            ]);

            // 明細を保存
            if (! empty($validated['details'])) {
                foreach ($validated['details'] as $index => $detail) {
                    if (empty($detail['domain']) && empty($detail['goal'])) {
                        continue;
                    }

                    SupportPlanDetail::create([
                        'plan_id'            => $plan->id,
                        'sort_order'         => $index,
                        'domain'             => $detail['domain'] ?? '',
                        'current_status'     => $detail['current_status'] ?? '',
                        'goal'               => $detail['goal'] ?? '',
                        'support_content'    => $detail['support_content'] ?? '',
                        'achievement_status' => $detail['achievement_status'] ?? null,
                    ]);
                }
            }

            return $plan;
        });

        return response()->json([
            'success' => true,
            'data'    => $plan->load('details'),
            'message' => '個別支援計画書を作成しました。',
        ], 201);
    }

    /**
     * 支援計画を更新
     */
    public function update(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $plan->student);

        $validated = $request->validate([
            'created_date'       => 'sometimes|date',
            'life_intention'     => 'nullable|string',
            'overall_policy'     => 'nullable|string',
            'long_term_goal'     => 'nullable|string',
            'short_term_goal'    => 'nullable|string',
            'consent_date'       => 'nullable|date',
            'status'             => 'nullable|string|in:draft,submitted,official',
            'details'            => 'nullable|array',
            'details.*.domain'           => 'nullable|string',
            'details.*.current_status'   => 'nullable|string',
            'details.*.goal'             => 'nullable|string',
            'details.*.support_content'  => 'nullable|string',
            'details.*.achievement_status' => 'nullable|string',
        ]);

        DB::transaction(function () use ($plan, $validated) {
            $updateData = collect($validated)->except('details')->toArray();

            if (isset($updateData['status'])) {
                $updateData['is_official'] = $updateData['status'] === 'official';
            }

            $plan->update($updateData);

            // 明細を再構築
            if (isset($validated['details'])) {
                $plan->details()->delete();

                foreach ($validated['details'] as $index => $detail) {
                    if (empty($detail['domain']) && empty($detail['goal'])) {
                        continue;
                    }

                    SupportPlanDetail::create([
                        'plan_id'            => $plan->id,
                        'sort_order'         => $index,
                        'domain'             => $detail['domain'] ?? '',
                        'current_status'     => $detail['current_status'] ?? '',
                        'goal'               => $detail['goal'] ?? '',
                        'support_content'    => $detail['support_content'] ?? '',
                        'achievement_status' => $detail['achievement_status'] ?? null,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh('details'),
            'message' => '個別支援計画書を更新しました。',
        ]);
    }

    /**
     * AI で支援計画の内容を生成
     */
    public function generateAi(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $plan->student);

        $plan->load(['student.classroom', 'details']);
        $student = $plan->student;

        // ===================================================================
        // 1. かけはしデータ（保護者・職員）を取得 ← 旧システム準拠
        // ===================================================================
        $latestPeriod = \App\Models\KakehashiPeriod::where('student_id', $student->id)
            ->with(['staffEntries', 'guardianEntries'])
            ->orderByDesc('start_date')
            ->first();

        $guardianText = '';
        $staffText = '';

        if ($latestPeriod) {
            $ge = $latestPeriod->guardianEntries->first();
            if ($ge && $ge->is_submitted) {
                $guardianText = "【保護者かけはし】\n"
                    . "・本人の願い: {$ge->student_wish}\n"
                    . "・家庭での願い: {$ge->home_challenges}\n"
                    . "・【重要】短期目標: {$ge->short_term_goal}\n"
                    . "・【重要】長期目標: {$ge->long_term_goal}\n"
                    . "・健康・生活: {$ge->domain_health_life}\n"
                    . "・運動・感覚: {$ge->domain_motor_sensory}\n"
                    . "・認知・行動: {$ge->domain_cognitive_behavior}\n"
                    . "・言語・コミュニケーション: {$ge->domain_language_communication}\n"
                    . "・人間関係・社会性: {$ge->domain_social_relations}\n\n";
            }

            $se = $latestPeriod->staffEntries->first();
            if ($se && $se->is_submitted) {
                $staffText = "【スタッフかけはし】\n"
                    . "・本人の願い: {$se->student_wish}\n"
                    . "・【重要】短期目標: {$se->short_term_goal}\n"
                    . "・【重要】長期目標: {$se->long_term_goal}\n"
                    . "・健康・生活: {$se->health_life}\n"
                    . "・運動・感覚: {$se->motor_sensory}\n"
                    . "・認知・行動: {$se->cognitive_behavior}\n"
                    . "・言語・コミュニケーション: {$se->language_communication}\n"
                    . "・人間関係・社会性: {$se->social_relations}\n\n";
            }
        }

        // ===================================================================
        // 2. 最新モニタリングデータ
        // ===================================================================
        $monitoringText = '';
        $latestMonitoring = \App\Models\MonitoringRecord::where('student_id', $student->id)
            ->with('details')
            ->orderByDesc('monitoring_date')
            ->first();

        if ($latestMonitoring) {
            $monitoringText = "【最新モニタリング（{$latestMonitoring->monitoring_date}）】\n"
                . "総合所見: {$latestMonitoring->overall_comment}\n";
            foreach ($latestMonitoring->details ?? [] as $md) {
                $monitoringText .= "・{$md->category}: 達成度={$md->achievement_status} {$md->monitoring_comment}\n";
            }
            $monitoringText .= "\n";
        }

        // ===================================================================
        // 3. 連絡帳データ（直近30件）
        // ===================================================================
        $records = \App\Models\StudentRecord::where('student_id', $student->id)
            ->with('dailyRecord:id,record_date,activity_name')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $recordsText = $records->map(function ($r) {
            $date = $r->dailyRecord->record_date ?? '';
            $parts = ["[{$date}]"];
            if ($r->domain1) $parts[] = "{$r->domain1}: {$r->domain1_content}";
            if ($r->domain2) $parts[] = "{$r->domain2}: {$r->domain2_content}";
            if ($r->daily_note) $parts[] = "メモ: {$r->daily_note}";
            return implode(' / ', $parts);
        })->implode("\n");

        // ===================================================================
        // 4. 前回の支援計画
        // ===================================================================
        $prevPlan = $student->supportPlans()
            ->with('details')
            ->where('id', '!=', $plan->id)
            ->orderByDesc('created_date')
            ->first();

        $prevPlanText = '';
        if ($prevPlan) {
            $prevPlanText = "【前回の支援計画】\n"
                . "・本人の願い: {$prevPlan->life_intention}\n"
                . "・支援方針: {$prevPlan->overall_policy}\n"
                . "・長期目標: {$prevPlan->long_term_goal}\n"
                . "・短期目標: {$prevPlan->short_term_goal}\n\n";
        }

        // ===================================================================
        // 5. GPTプロンプト構築（旧システム準拠）
        // ===================================================================
        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'OpenAI APIキーが設定されていません。'], 422);
            }

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは放課後等デイサービスの児童発達支援管理責任者です。'
                            . '個別支援計画書の作成を支援します。JSON形式のみで回答してください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "以下の情報をもとに個別支援計画書を作成してください。\n\n"
                            . "【児童名】{$student->student_name}\n"
                            . "【教室】" . ($student->classroom->classroom_name ?? '') . "\n\n"
                            . $guardianText
                            . $staffText
                            . $monitoringText
                            . $prevPlanText
                            . "【連絡帳記録（直近{$records->count()}件）】\n"
                            . ($recordsText ?: '（記録なし）') . "\n\n"
                            . "【重要なルール】\n"
                            . "1. 短期目標・長期目標は、保護者かけはしとスタッフかけはしの目標の文言を最大限考慮し、整合性・連続性を保つこと\n"
                            . "2. 目標文に「1年後には」「半年後に」「○ヶ月後」等の時間表現を含めないこと（日付は別管理）\n"
                            . "3. 支援内容は200-300文字で、具体的な手順・頻度・教材・段階的アプローチを含めること\n"
                            . "4. 支援目標は100-120文字で、施設での具体的な到達目標（行動レベル）を記述すること\n\n"
                            . "以下のJSON形式で出力してください:\n"
                            . "{\n"
                            . "  \"life_intention\": \"利用児及び家族の生活に対する意向（100-200文字）\",\n"
                            . "  \"overall_policy\": \"総合的な援助の方針（150-250文字）\",\n"
                            . "  \"long_term_goal\": \"長期目標（100-150文字、時間表現なし）\",\n"
                            . "  \"short_term_goal\": \"短期目標（100-150文字、時間表現なし）\",\n"
                            . "  \"details\": [\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"健康・生活\", \"support_goal\": \"...\", \"support_content\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"運動・感覚\", \"support_goal\": \"...\", \"support_content\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"認知・行動\", \"support_goal\": \"...\", \"support_content\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"言語・コミュニケーション\", \"support_goal\": \"...\", \"support_content\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"人間関係・社会性\", \"support_goal\": \"...\", \"support_content\": \"...\"},\n"
                            . "    {\"category\": \"家族支援\", \"sub_category\": \"家族支援\", \"support_goal\": \"...\", \"support_content\": \"...\"},\n"
                            . "    {\"category\": \"地域支援\", \"sub_category\": \"地域連携\", \"support_goal\": \"...\", \"support_content\": \"...\"}\n"
                            . "  ]\n"
                            . "}",
                    ],
                ],
                'response_format'       => ['type' => 'json_object'],
                'temperature'           => 0.7,
                'max_completion_tokens' => 4000,
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            // ログ保存
            try {
                \App\Models\AiGenerationLog::create([
                    'user_id'       => $request->user()->id,
                    'model'         => 'gpt-4o',
                    'prompt_type'   => 'support_plan',
                    'input_tokens'  => $response->usage->promptTokens ?? null,
                    'output_tokens' => $response->usage->completionTokens ?? null,
                    'student_id'    => $student->id,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI log failed: ' . $e->getMessage());
            }

            return response()->json([
                'success'      => true,
                'data'         => $result ?? [],
                'sources'      => [
                    'kakehashi'  => !empty($guardianText) || !empty($staffText),
                    'monitoring' => !empty($monitoringText),
                    'records'    => $records->count(),
                    'prev_plan'  => !empty($prevPlanText),
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
     * 電子署名を保存
     */
    public function sign(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $plan->student);

        $request->validate([
            'staff_signature'      => 'required|string', // base64 image
            'staff_signer_name'    => 'required|string|max:255',
            'guardian_signature'   => 'nullable|string', // base64 image
        ]);

        // 職員署名のバリデーション
        if (! str_starts_with($request->staff_signature, 'data:image')) {
            return response()->json([
                'success' => false,
                'message' => '職員の署名が無効です。',
            ], 422);
        }

        $updateData = [
            'staff_signature'      => $request->staff_signature,
            'staff_signature_date' => now()->toDateString(),
            'is_official'          => true,
            'status'               => 'official',
        ];

        // 保護者署名がある場合
        if ($request->guardian_signature && str_starts_with($request->guardian_signature, 'data:image')) {
            $updateData['guardian_signature']      = $request->guardian_signature;
            $updateData['guardian_signature_date']  = now()->toDateString();
            $updateData['guardian_reviewed_at']     = now();
        }

        $plan->update($updateData);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh(),
            'message' => '署名を保存し、正式版として確定しました。',
        ]);
    }

    /**
     * PDF を生成して返す
     */
    public function pdf(Request $request, IndividualSupportPlan $plan)
    {
        $this->authorizeClassroom($request->user(), $plan->student);

        $plan->load(['student.classroom', 'details', 'creator']);

        $pdf = Pdf::loadView('pdf.support-plan', [
            'plan'      => $plan,
            'student'   => $plan->student,
            'classroom' => $plan->student->classroom ?? null,
            'details'   => $plan->details->sortBy('sort_order'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'ipag');

        $filename = 'support_plan_' . ($plan->student->student_name ?? $plan->id) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * 生徒IDとプランIDを指定して支援計画を更新（ネストルート用）
     */
    public function updateNested(Request $request, Student $student, IndividualSupportPlan $plan): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        if ($plan->student_id !== $student->id) {
            return response()->json(['success' => false, 'message' => '指定された生徒の支援計画ではありません。'], 404);
        }

        return $this->update($request, $plan);
    }

    /**
     * 生徒IDを指定してAIで支援計画を生成
     */
    public function generateAiForStudent(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $student->load(['interviews', 'dailyRecords.dailyRecord']);

        $interviewText = $student->interviews()
            ->orderByDesc('interview_date')
            ->limit(5)
            ->get()
            ->map(fn ($i) => "[{$i->interview_date}] {$i->interview_content}")
            ->implode("\n");

        $recordsText = $student->dailyRecords()
            ->with('dailyRecord')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                $date = $r->dailyRecord->record_date ?? '';
                $parts = [];
                if ($r->health_life) $parts[] = "健康・生活: {$r->health_life}";
                if ($r->motor_sensory) $parts[] = "運動・感覚: {$r->motor_sensory}";
                if ($r->cognitive_behavior) $parts[] = "認知・行動: {$r->cognitive_behavior}";
                if ($r->language_communication) $parts[] = "言語・コミュニケーション: {$r->language_communication}";
                if ($r->social_relations) $parts[] = "人間関係・社会性: {$r->social_relations}";
                return "[{$date}] " . implode(' / ', $parts);
            })->implode("\n");

        try {
            $response = OpenAI::chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは児童発達支援施設の児童発達支援管理責任者です。個別支援計画書の作成を支援します。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "以下の情報をもとに、個別支援計画書の支援目標と支援内容を提案してください。\n\n"
                            . "【児童名】{$student->student_name}\n"
                            . "【面接記録】\n{$interviewText}\n\n"
                            . "【連絡帳記録（5領域）】\n{$recordsText}\n\n"
                            . "5領域（健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性）ごとに"
                            . "支援目標と支援内容を提案してください。\n"
                            . "JSON形式で出力してください: [{\"domain\": \"...\", \"current_status\": \"...\", \"goal\": \"...\", \"support_content\": \"...\"}]",
                    ],
                ],
                'temperature'           => 0.5,
                'max_completion_tokens' => 2000,
            ]);

            $content = $response->choices[0]->message->content;

            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $content = trim($matches[1]);
            }

            $suggestions = json_decode($content, true);

            return response()->json([
                'success' => true,
                'data'    => $suggestions ?? $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 個別支援計画を削除
     */
    public function destroy(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        if ($plan->student) {
            $this->authorizeClassroom($request->user(), $plan->student);
        }

        $plan->details()->delete();
        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => '個別支援計画を削除しました。',
        ]);
    }

    /**
     * 下書き → 確認依頼（proposal）に変更
     */
    public function publish(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        $this->authorizeClassroom($request->user(), $plan->student);

        if ($plan->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '下書き状態の計画のみ確認依頼に変更できます。',
            ], 422);
        }

        $plan->update([
            'status'   => 'submitted',
            'is_draft' => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh('details'),
            'message' => '確認依頼として公開しました。',
        ]);
    }

    /**
     * 正式版に変更
     */
    public function makeOfficial(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        $this->authorizeClassroom($request->user(), $plan->student);

        if ($plan->status === 'official') {
            return response()->json([
                'success' => false,
                'message' => 'すでに正式版です。',
            ], 422);
        }

        $plan->update([
            'status'      => 'official',
            'is_official' => true,
            'is_draft'    => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh('details'),
            'message' => '正式版として確定しました。',
        ]);
    }

    /**
     * 計画の根拠データ（かけはし・モニタリング・目標比較）を返す
     */
    public function basis(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        $this->authorizeClassroom($request->user(), $plan->student);

        $studentId = $plan->student_id;
        $planDate = $plan->created_date;

        // -----------------------------------------------------------------
        // 1. かけはし期間（submission_deadline <= plan.created_date で最も近いもの）
        // -----------------------------------------------------------------
        $kakehashiPeriod = \App\Models\KakehashiPeriod::where('student_id', $studentId)
            ->when($planDate, function ($q) use ($planDate) {
                $q->where('submission_deadline', '<=', $planDate);
            })
            ->with(['guardianEntries', 'staffEntries'])
            ->orderByDesc('submission_deadline')
            ->first();

        $guardianKakehashi = null;
        $staffKakehashi = null;

        if ($kakehashiPeriod) {
            $ge = $kakehashiPeriod->guardianEntries->first();
            if ($ge) {
                $guardianKakehashi = [
                    'student_wish'                => $ge->student_wish,
                    'home_challenges'             => $ge->home_challenges,
                    'short_term_goal'             => $ge->short_term_goal,
                    'long_term_goal'              => $ge->long_term_goal,
                    'domain_health_life'          => $ge->domain_health_life,
                    'domain_motor_sensory'        => $ge->domain_motor_sensory,
                    'domain_cognitive_behavior'   => $ge->domain_cognitive_behavior,
                    'domain_language_communication' => $ge->domain_language_communication,
                    'domain_social_relations'     => $ge->domain_social_relations,
                    'is_submitted'                => $ge->is_submitted,
                ];
            }

            $se = $kakehashiPeriod->staffEntries->first();
            if ($se) {
                $staffKakehashi = [
                    'student_wish'             => $se->student_wish,
                    'short_term_goal'          => $se->short_term_goal,
                    'long_term_goal'           => $se->long_term_goal,
                    'health_life'              => $se->health_life,
                    'motor_sensory'            => $se->motor_sensory,
                    'cognitive_behavior'       => $se->cognitive_behavior,
                    'language_communication'   => $se->language_communication,
                    'social_relations'         => $se->social_relations,
                    'is_submitted'             => $se->is_submitted,
                ];
            }
        }

        // -----------------------------------------------------------------
        // 2. 最新モニタリング
        // -----------------------------------------------------------------
        $latestMonitoring = \App\Models\MonitoringRecord::where('student_id', $studentId)
            ->with('details')
            ->orderByDesc('monitoring_date')
            ->first();

        // -----------------------------------------------------------------
        // 3. 目標比較（保護者/スタッフ/計画の目標を並べる）
        // -----------------------------------------------------------------
        $goalComparison = [
            'guardian' => [
                'short_term_goal' => $guardianKakehashi['short_term_goal'] ?? null,
                'long_term_goal'  => $guardianKakehashi['long_term_goal'] ?? null,
            ],
            'staff' => [
                'short_term_goal' => $staffKakehashi['short_term_goal'] ?? null,
                'long_term_goal'  => $staffKakehashi['long_term_goal'] ?? null,
            ],
            'plan' => [
                'short_term_goal' => $plan->short_term_goal,
                'long_term_goal'  => $plan->long_term_goal,
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'kakehashi_period'    => $kakehashiPeriod ? [
                    'id'                  => $kakehashiPeriod->id,
                    'period_name'         => $kakehashiPeriod->period_name,
                    'start_date'          => $kakehashiPeriod->start_date?->toDateString(),
                    'end_date'            => $kakehashiPeriod->end_date?->toDateString(),
                    'submission_deadline' => $kakehashiPeriod->submission_deadline?->toDateString(),
                ] : null,
                'guardian_kakehashi'  => $guardianKakehashi,
                'staff_kakehashi'     => $staffKakehashi,
                'latest_monitoring'   => $latestMonitoring,
                'goal_comparison'     => $goalComparison,
            ],
        ]);
    }

    /**
     * 教室アクセス権限チェック
     */
    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
