<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\SupportPlanDetail;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * 支援計画の詳細を取得
     */
    public function show(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load(['student', 'details' => fn ($q) => $q->orderBy('sort_order'), 'creator:id,full_name']);

        if ($plan->student) {
            $this->authorizeClassroom($request->user(), $plan->student);
        }

        return response()->json([
            'success' => true,
            'data'    => $plan,
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
            'long_term_goal_date'  => 'nullable|date',
            'short_term_goal_date' => 'nullable|date',
            'consent_date'       => 'nullable|date',
            'consent_name'       => 'nullable|string|max:255',
            'manager_name'       => 'nullable|string|max:255',
            'status'             => 'nullable|string|in:draft,submitted,official',
            'details'            => 'nullable|array',
            'details.*.domain'             => 'nullable|string',
            'details.*.current_status'     => 'nullable|string',
            'details.*.goal'               => 'nullable|string',
            'details.*.support_goal'       => 'nullable|string',
            'details.*.support_content'    => 'nullable|string',
            'details.*.achievement_status' => 'nullable|string',
            'details.*.category'           => 'nullable|string',
            'details.*.sub_category'       => 'nullable|string',
            'details.*.achievement_date'   => 'nullable|date',
            'details.*.staff_organization' => 'nullable|string',
            'details.*.notes'              => 'nullable|string',
            'details.*.priority'           => 'nullable|integer',
        ]);

        // 同じ生徒・同じ作成日の計画が既にある場合はエラー
        $existingPlan = IndividualSupportPlan::where('student_id', $student->id)
            ->where('created_date', $validated['created_date'])
            ->first();
        if ($existingPlan) {
            return response()->json([
                'success' => false,
                'message' => 'この生徒には同じ日付の個別支援計画が既に存在します（ID: ' . $existingPlan->id . '）。既存の計画を編集してください。',
            ], 422);
        }

        // 提出時は達成時期を必須チェック
        $status = $validated['status'] ?? 'draft';
        if ($status !== 'draft') {
            $errors = $this->validateDatesForSubmission($validated);
            if (!empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' ', $errors)], 422);
            }
        }

        // 達成時期のデフォルト値を自動設定
        $validated = $this->fillDefaultDates($validated);

        $plan = DB::transaction(function () use ($request, $student, $validated) {
            $managerName = $validated['manager_name'] ?? $validated['consent_name'] ?? null;
            $plan = IndividualSupportPlan::create([
                'student_id'          => $student->id,
                'classroom_id'        => $student->classroom_id,
                'student_name'        => $student->student_name,
                'created_date'        => $validated['created_date'],
                'life_intention'      => $validated['life_intention'] ?? null,
                'overall_policy'      => $validated['overall_policy'] ?? null,
                'long_term_goal'      => $validated['long_term_goal'] ?? null,
                'short_term_goal'     => $validated['short_term_goal'] ?? null,
                'long_term_goal_date' => $validated['long_term_goal_date'] ?? null,
                'short_term_goal_date' => $validated['short_term_goal_date'] ?? null,
                'consent_date'        => $validated['consent_date'] ?? null,
                'consent_name'        => $managerName,
                'manager_name'        => $managerName,
                'status'              => $validated['status'] ?? 'draft',
                'is_official'         => ($validated['status'] ?? 'draft') === 'official',
                'created_by'          => $request->user()->id,
            ]);

            // 明細を保存
            if (! empty($validated['details'])) {
                foreach ($validated['details'] as $index => $detail) {
                    // category or domain must have content, or support_goal/goal must have content
                    if (empty($detail['category']) && empty($detail['domain'])
                        && empty($detail['goal']) && empty($detail['support_goal'])) {
                        continue;
                    }

                    SupportPlanDetail::create([
                        'plan_id'            => $plan->id,
                        'sort_order'         => $index,
                        'domain'             => $detail['domain'] ?? $detail['category'] ?? '',
                        'current_status'     => $detail['current_status'] ?? '',
                        'goal'               => $detail['support_goal'] ?? $detail['goal'] ?? '',
                        'support_content'    => $detail['support_content'] ?? '',
                        'achievement_status' => $detail['achievement_status'] ?? null,
                        'category'           => $detail['category'] ?? $detail['domain'] ?? null,
                        'sub_category'       => $detail['sub_category'] ?? null,
                        'achievement_date'   => $detail['achievement_date'] ?? null,
                        'staff_organization' => $detail['staff_organization'] ?? null,
                        'notes'              => $detail['notes'] ?? null,
                        'priority'           => $detail['priority'] ?? 0,
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
            'life_intention'     => 'sometimes|nullable|string',
            'overall_policy'     => 'sometimes|nullable|string',
            'long_term_goal'     => 'sometimes|nullable|string',
            'short_term_goal'    => 'sometimes|nullable|string',
            'long_term_goal_date'  => 'sometimes|nullable|date',
            'short_term_goal_date' => 'sometimes|nullable|date',
            'consent_date'       => 'sometimes|nullable|date',
            'consent_name'       => 'sometimes|nullable|string|max:255',
            'manager_name'       => 'sometimes|nullable|string|max:255',
            'status'             => 'sometimes|nullable|string|in:draft,submitted,official',
            'details'            => 'sometimes|nullable|array',
            'details.*.domain'             => 'nullable|string',
            'details.*.current_status'     => 'nullable|string',
            'details.*.goal'               => 'nullable|string',
            'details.*.support_goal'       => 'nullable|string',
            'details.*.support_content'    => 'nullable|string',
            'details.*.achievement_status' => 'nullable|string',
            'details.*.category'           => 'nullable|string',
            'details.*.sub_category'       => 'nullable|string',
            'details.*.achievement_date'   => 'nullable|date',
            'details.*.staff_organization' => 'nullable|string',
            'details.*.notes'              => 'nullable|string',
            'details.*.priority'           => 'nullable|integer',
        ]);

        // 達成時期のデフォルト値を自動設定
        $validated['created_date'] = $validated['created_date'] ?? $plan->created_date?->format('Y-m-d');
        $validated = $this->fillDefaultDates($validated);

        // 提出時は達成時期を必須チェック
        $status = $validated['status'] ?? $plan->status;
        if ($status !== 'draft') {
            $checkData = array_merge($plan->toArray(), $validated);
            $errors = $this->validateDatesForSubmission($checkData);
            if (!empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' ', $errors)], 422);
            }
        }

        DB::transaction(function () use ($plan, $validated) {
            $updateData = collect($validated)->except('details')->toArray();

            if (isset($updateData['status'])) {
                $updateData['is_official'] = $updateData['status'] === 'official';
            }

            // Map consent_name / manager_name (frontend sends consent_name)
            if (isset($updateData['consent_name']) || isset($updateData['manager_name'])) {
                $name = $updateData['manager_name'] ?? $updateData['consent_name'] ?? null;
                $updateData['consent_name'] = $name;
                $updateData['manager_name'] = $name;
            }

            $plan->update($updateData);

            // 明細を再構築
            if (isset($validated['details'])) {
                $plan->details()->delete();

                foreach ($validated['details'] as $index => $detail) {
                    if (empty($detail['category']) && empty($detail['domain'])
                        && empty($detail['goal']) && empty($detail['support_goal'])) {
                        continue;
                    }

                    SupportPlanDetail::create([
                        'plan_id'            => $plan->id,
                        'sort_order'         => $index,
                        'domain'             => $detail['domain'] ?? $detail['category'] ?? '',
                        'current_status'     => $detail['current_status'] ?? '',
                        'goal'               => $detail['support_goal'] ?? $detail['goal'] ?? '',
                        'support_content'    => $detail['support_content'] ?? '',
                        'achievement_status' => $detail['achievement_status'] ?? null,
                        'category'           => $detail['category'] ?? $detail['domain'] ?? null,
                        'sub_category'       => $detail['sub_category'] ?? null,
                        'achievement_date'   => $detail['achievement_date'] ?? null,
                        'staff_organization' => $detail['staff_organization'] ?? null,
                        'notes'              => $detail['notes'] ?? null,
                        'priority'           => $detail['priority'] ?? 0,
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
                    . "・人間関係・社会性: {$ge->domain_social_relations}\n"
                    . "・その他: {$ge->other_challenges}\n\n";
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
                    . "・人間関係・社会性: {$se->social_relations}\n"
                    . "・その他: " . ($se->other_challenges ?? '') . "\n\n";
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

        // 新スキーマ（5 領域カラム）を使用。legacy の domain1/domain2 は存在しない。
        $domainLabels = [
            'health_life' => '健康・生活',
            'motor_sensory' => '運動・感覚',
            'cognitive_behavior' => '認知・行動',
            'language_communication' => '言語・コミュニケーション',
            'social_relations' => '人間関係・社会性',
        ];
        $recordsText = $records->map(function ($r) use ($domainLabels) {
            $date = $r->dailyRecord->record_date ?? '';
            $parts = ["[{$date}]"];
            foreach ($domainLabels as $col => $label) {
                if (!empty($r->{$col})) {
                    $parts[] = "{$label}: {$r->{$col}}";
                }
            }
            if (!empty($r->notes)) {
                $parts[] = "メモ: {$r->notes}";
            }
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
                'model'    => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは児童発達支援の専門家です。個別支援計画書を作成する際は、具体的で実践可能な支援内容を詳細に記述してください。抽象的な表現は避け、現場のスタッフが実際に使用できる具体的な手順、頻度、環境設定、段階的なアプローチを含めてください。',
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
                            . "【重要な指示】\n"
                            . "- 【最重要】個別支援計画の短期目標・長期目標は、保護者かけはしとスタッフかけはしの短期目標・長期目標の文言を最大限考慮し、それらとの整合性・連続性を保ちながら作成してください\n"
                            . "- かけはしで設定された目標を土台として、施設での具体的な支援場面に落とし込んだ目標を記述してください\n"
                            . "- 各項目について、具体的かつ詳細に記述してください\n"
                            . "- 支援目標は、観察可能で測定可能な行動として明確に記述してください\n"
                            . "- 支援内容は、具体的な支援方法、頻度、場面、使用する教材・環境設定などを詳しく記載してください\n"
                            . "- 各領域の支援内容は最低150文字以上、できれば200-300文字程度で詳しく記述してください\n"
                            . "- 留意事項には、配慮すべき点、成功のためのポイント、予想される困難と対処法などを記載してください\n\n"
                            . "以下のJSON形式で出力してください:\n"
                            . "{\n"
                            . "  \"life_intention\": \"利用児及び家族の生活に対する意向（保護者と本人の願いを踏まえた詳細な記述。100-200文字程度）\",\n"
                            . "  \"overall_policy\": \"総合的な支援の方針（本人・家族の意向を受けて、どのような方針で支援するか。強み・課題・環境を踏まえた総合的な方針を150-250文字程度で記述）\",\n"
                            . "  \"long_term_goal\": \"【最重要】長期目標の内容（上記の保護者かけはしとスタッフかけはしの長期目標の文言を最大限考慮し、それらの目標と整合性を保ちながら、施設での支援を通じて到達してほしい具体的な姿を記述。観察可能な行動として100-150文字程度で記述。期間を含めた表現は使用しないこと）\",\n"
                            . "  \"short_term_goal\": \"【最重要】短期目標の内容（上記の保護者かけはしとスタッフかけはしの短期目標の文言を最大限考慮し、それらの目標と整合性を保ちながら、施設での支援を通じて到達してほしい具体的な姿を記述。観察可能な行動として100-150文字程度で記述。期間を含めた表現は使用しないこと）\",\n"
                            . "  \"details\": [\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"生活習慣（健康・生活）\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"保育士\\n児童指導員\", \"notes\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"コミュニケーション（言語・コミュニケーション）\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"保育士\\n児童指導員\", \"notes\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"社会性（人間関係・社会性）\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"保育士\\n児童指導員\", \"notes\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"運動・感覚（運動・感覚）\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"保育士\\n児童指導員\", \"notes\": \"...\"},\n"
                            . "    {\"category\": \"本人支援\", \"sub_category\": \"学習（認知・行動）\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"保育士\\n児童指導員\", \"notes\": \"...\"},\n"
                            . "    {\"category\": \"家族支援\", \"sub_category\": \"保護者支援\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"児童発達支援管理責任者\\n保育士\", \"notes\": \"...\"},\n"
                            . "    {\"category\": \"地域支援\", \"sub_category\": \"関係機関連携\", \"support_goal\": \"...\", \"support_content\": \"...\", \"staff_organization\": \"児童発達支援管理責任者\", \"notes\": \"...\"}\n"
                            . "  ]\n"
                            . "}\n\n"
                            . "【注意事項】\n"
                            . "- 必ず有効なJSON形式で出力してください\n"
                            . "- 【最重要】個別支援計画の長期目標・短期目標は、保護者かけはしとスタッフかけはしで既に設定された長期目標・短期目標の内容を最大限尊重し、それらの表現や意図を引き継ぎながら、施設での具体的な支援場面に適した形で記述してください\n"
                            . "- かけはしの目標で使われているキーワードや表現をできるだけ活かしてください\n"
                            . "- 【重要】五領域については、必ず施設内で実施できる支援内容を記述してください。家庭での取り組みは含めないでください\n"
                            . "- 支援内容は具体的な手順、頻度、使用する道具・環境、段階的なアプローチを含めてください\n"
                            . "- 抽象的な表現ではなく、実際に現場で実践できる具体的な内容を記述してください\n"
                            . "- 【重要】長期目標・短期目標・支援目標には「1年後には」「半年後には」「○ヶ月後」「いつまでに」などの期間を含めた表現は絶対に使用しないでください",
                    ],
                ],
                'response_format'       => ['type' => 'json_object'],
                'temperature'           => 0.8,
                'max_completion_tokens' => 4000,
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            // ログ保存
            try {
                \App\Models\AiGenerationLog::create([
                    'user_id'       => $request->user()->id,
                    'model'         => 'gpt-5.4-mini-2026-03-17',
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
            'staff_signer_name'    => $request->staff_signer_name,
            'is_official'          => true,
            'is_draft'             => false,
            'status'               => 'official',
        ];

        // 保護者署名がある場合
        if ($request->guardian_signature && str_starts_with($request->guardian_signature, 'data:image')) {
            $updateData['guardian_signature']      = $request->guardian_signature;
            $updateData['guardian_signature_date']  = now()->toDateString();
            $updateData['guardian_reviewed_at']     = now();
            $updateData['guardian_confirmed']       = true;
            $updateData['guardian_confirmed_at']    = now();
        }

        // Set consent_date if not already set (legacy: COALESCE(consent_date, ?))
        if (! $plan->consent_date) {
            $updateData['consent_date'] = now()->toDateString();
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

        $filename = 'support_plan_' . ($plan->student->student_name ?? $plan->id) . '.pdf';

        return PuppeteerPdfService::download('pdf.support-plan', [
            'plan'      => $plan,
            'student'   => $plan->student,
            'classroom' => $plan->student->classroom ?? null,
            'details'   => $plan->details->sortBy('sort_order'),
        ], $filename, 'A4', true);
    }

    /**
     * CSV エクスポート（旧 kobetsu_plan_export.php 互換）
     *
     * UTF-8 BOM 付き CSV で出力。ヘッダ行 + 計画メタ情報 + 7行明細テーブル。
     */
    public function export(Request $request, IndividualSupportPlan $plan)
    {
        $plan->load(['student', 'details' => fn ($q) => $q->orderBy('sort_order')]);

        if ($plan->student) {
            $this->authorizeClassroom($request->user(), $plan->student);
        }

        $studentName = $plan->student->student_name ?? $plan->student_name ?? 'unknown';
        $createdDate = $plan->created_date ? date('Ymd', strtotime($plan->created_date)) : date('Ymd');
        $filename = "個別支援計画書_{$studentName}_{$createdDate}.csv";

        $callback = function () use ($plan, $studentName) {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");

            // メタ情報
            fputcsv($output, ['種別', '項目名', '値']);
            fputcsv($output, ['タイトル', '個別支援計画書', '']);
            fputcsv($output, ['対象者名', $studentName, '']);
            fputcsv($output, ['作成年月日', $plan->created_date ? date('Y年m月d日', strtotime($plan->created_date)) : '', '']);
            fputcsv($output, ['']);

            fputcsv($output, ['利用児及び家族の生活に対する意向', $plan->life_intention ?? '', '']);
            fputcsv($output, ['']);

            fputcsv($output, ['総合的な支援の方針', $plan->overall_policy ?? '', '']);
            fputcsv($output, ['']);

            // 長期目標
            $longTermDate = $plan->long_term_goal_date
                ? date('Y年m月d日', strtotime($plan->long_term_goal_date))
                : '';
            fputcsv($output, ['長期目標', $longTermDate, '']);
            fputcsv($output, ['長期目標内容', $plan->long_term_goal ?? '', '']);
            fputcsv($output, ['']);

            // 短期目標
            $shortTermDate = $plan->short_term_goal_date
                ? date('Y年m月d日', strtotime($plan->short_term_goal_date))
                : '';
            fputcsv($output, ['短期目標', $shortTermDate, '']);
            fputcsv($output, ['短期目標内容', $plan->short_term_goal ?? '', '']);
            fputcsv($output, ['']);

            // 明細テーブルヘッダ
            fputcsv($output, [
                '項目',
                '支援目標（具体的な到達目標）',
                '支援内容（内容・支援の提供上のポイント・5領域との関連性等）',
                '達成時期',
                '担当者／提供機関',
                '留意事項',
                '優先順位',
            ]);

            // 明細データ
            foreach ($plan->details as $detail) {
                $category = $detail->category ?? $detail->domain ?? '';
                if ($detail->sub_category) {
                    $category .= "\n" . $detail->sub_category;
                }

                $achievementDate = '';
                if ($detail->achievement_date) {
                    $achievementDate = date('Y/m/d', strtotime($detail->achievement_date));
                }

                fputcsv($output, [
                    $category,
                    $detail->goal ?? $detail->support_goal ?? '',
                    $detail->support_content ?? '',
                    $achievementDate,
                    $detail->staff_organization ?? '',
                    $detail->notes ?? '',
                    $detail->priority ?? '',
                ]);
            }

            fputcsv($output, ['']);

            // 注記
            fputcsv($output, [
                'Note',
                '※5領域の視点：「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」',
                '',
            ]);
            fputcsv($output, ['']);

            // 同意欄
            fputcsv($output, ['ラベル', '値']);
            fputcsv($output, ['管理責任者氏名', $plan->manager_name ?? '']);

            $consentDateStr = '';
            if ($plan->consent_date) {
                $consentDateStr = date('Y年m月d日', strtotime($plan->consent_date));
            }
            fputcsv($output, ['同意日', $consentDateStr]);
            fputcsv($output, ['保護者署名', $plan->guardian_signature ? '（署名済み）' : '']);

            fclose($output);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = \OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-5.4-mini-2026-03-17',
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
     * 下書き or 提出済み → 確認依頼（proposal）を保護者に送信
     * - draft の場合: status を submitted に更新
     * - 既に submitted の場合: 再送扱い（status は維持）
     * - official (正式版) は再送不可
     */
    public function publish(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        $this->authorizeClassroom($request->user(), $plan->student);

        if ($plan->status === 'official') {
            return response()->json([
                'success' => false,
                'message' => '正式版の計画は確認依頼を再送できません。',
            ], 422);
        }

        $plan->update([
            'status'   => 'submitted',
            'is_draft' => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh('details'),
            'message' => '確認依頼を送信しました。',
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
            'status'               => 'official',
            'is_official'          => true,
            'is_draft'             => false,
            'guardian_confirmed'    => true,
            'guardian_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh('details'),
            'message' => '紙面サイン済みとして確定しました。',
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
                    'other_challenges'            => $ge->other_challenges,
                    'is_submitted'                => $ge->is_submitted,
                    'submitted_at'                => $ge->submitted_at,
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
                    'other_challenges'         => $se->other_challenges,
                    'is_submitted'             => $se->is_submitted,
                    'submitted_at'             => $se->submitted_at,
                ];
            }
        }

        // -----------------------------------------------------------------
        // 2. 最新モニタリング
        // -----------------------------------------------------------------
        $latestMonitoring = \App\Models\MonitoringRecord::where('student_id', $studentId)
            ->when($planDate, fn ($q) => $q->where('monitoring_date', '<=', $planDate))
            ->with('details.planDetail')
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
                'plan' => [
                    'id'             => $plan->id,
                    'created_date'   => $plan->created_date?->toDateString(),
                    'short_term_goal' => $plan->short_term_goal,
                    'long_term_goal'  => $plan->long_term_goal,
                    'life_intention'  => $plan->life_intention,
                    'overall_policy'  => $plan->overall_policy,
                    'basis_content'   => $plan->basis_content,
                    'basis_generated_at' => $plan->basis_generated_at,
                    'student' => $plan->student ? [
                        'id'           => $plan->student->id,
                        'student_name' => $plan->student->student_name,
                    ] : null,
                ],
                'kakehashi_period'    => $kakehashiPeriod ? [
                    'id'                  => $kakehashiPeriod->id,
                    'period_name'         => $kakehashiPeriod->period_name,
                    'start_date'          => $kakehashiPeriod->start_date?->toDateString(),
                    'end_date'            => $kakehashiPeriod->end_date?->toDateString(),
                    'submission_deadline' => $kakehashiPeriod->submission_deadline?->toDateString(),
                ] : null,
                'guardian_kakehashi'  => $guardianKakehashi,
                'staff_kakehashi'     => $staffKakehashi,
                'latest_monitoring'   => $latestMonitoring ? [
                    'monitoring_date'  => $latestMonitoring->monitoring_date,
                    'overall_comment'  => $latestMonitoring->overall_comment,
                    'details'          => $latestMonitoring->details->map(fn ($md) => [
                        'category'           => $md->planDetail->category ?? $md->domain ?? '',
                        'sub_category'       => $md->planDetail->sub_category ?? '',
                        'achievement_status' => $md->achievement_level ?? '',
                        'monitoring_comment' => $md->comment ?? '',
                    ])->values()->all(),
                ] : null,
                'goal_comparison'     => $goalComparison,
            ],
        ]);
    }

    /**
     * 個別支援計画の根拠文書（全体所感）をAI生成（レガシー準拠）
     */
    public function generateBasis(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        $this->authorizeClassroom($request->user(), $plan->student);

        $studentId = $plan->student_id;
        $studentName = $plan->student_name ?: $plan->student->student_name;
        $planDate = $plan->created_date;

        // かけはし期間（submission_deadline <= plan.created_date で最も近いもの）
        $kakehashiPeriod = \App\Models\KakehashiPeriod::where('student_id', $studentId)
            ->when($planDate, fn ($q) => $q->where('submission_deadline', '<=', $planDate))
            ->orderByDesc('submission_deadline')
            ->first();

        // 保護者かけはしデータ
        $guardianKakehashi = null;
        if ($kakehashiPeriod) {
            $guardianKakehashi = \App\Models\KakehashiGuardian::where('student_id', $studentId)
                ->where('period_id', $kakehashiPeriod->id)
                ->orderByDesc('submitted_at')
                ->first();
        }

        // スタッフかけはしデータ
        $staffKakehashi = null;
        if ($kakehashiPeriod) {
            $staffKakehashi = \App\Models\KakehashiStaff::where('student_id', $studentId)
                ->where('period_id', $kakehashiPeriod->id)
                ->orderByDesc('submitted_at')
                ->first();
        }

        // 直近のモニタリング
        $latestMonitoring = \App\Models\MonitoringRecord::where('student_id', $studentId)
            ->when($planDate, fn ($q) => $q->where('monitoring_date', '<=', $planDate))
            ->with('details.planDetail')
            ->orderByDesc('monitoring_date')
            ->first();

        // プロンプト構築（レガシー準拠）
        $prompt = "あなたは児童発達支援・放課後等デイサービスの専門家です。\n";
        $prompt .= "以下のデータに基づいて、この個別支援計画に対する「全体所感」を作成してください。\n";
        $prompt .= "保護者に向けて、計画がどのような考えに基づいて作成されたかを説明する文書です。\n\n";

        $prompt .= "【重要な指示】\n";
        $prompt .= "- 保護者に分かりやすい丁寧な言葉で説明してください\n";
        $prompt .= "- 保護者・スタッフからのかけはしの内容を踏まえた説明をしてください\n";
        $prompt .= "- 計画の目標がどのように本人・家族の願いを反映しているか説明してください\n";
        $prompt .= "- お子様の強みや成長の可能性についても触れてください\n";
        $prompt .= "- 600〜1000文字程度でまとめてください\n\n";

        $prompt .= "【生徒名】\n" . $studentName . "\n\n";

        $prompt .= "【個別支援計画の内容】\n";
        $prompt .= "作成日: " . ($plan->created_date ?? '') . "\n";
        $prompt .= "利用児及び家族の意向: " . ($plan->life_intention ?? '（未記入）') . "\n";
        $prompt .= "総合的な支援の方針: " . ($plan->overall_policy ?? '（未記入）') . "\n";
        $prompt .= "長期目標: " . ($plan->long_term_goal ?? '（未記入）') . "\n";
        $prompt .= "短期目標: " . ($plan->short_term_goal ?? '（未記入）') . "\n\n";

        if ($guardianKakehashi) {
            $prompt .= "【保護者からのかけはし（提出日: " . ($guardianKakehashi->submitted_at ?? '不明') . "）】\n";
            $prompt .= "本人の願い: " . ($guardianKakehashi->student_wish ?? '') . "\n";
            $prompt .= "家庭での願い: " . ($guardianKakehashi->home_challenges ?? '') . "\n";
            $prompt .= "短期目標: " . ($guardianKakehashi->short_term_goal ?? '') . "\n";
            $prompt .= "長期目標: " . ($guardianKakehashi->long_term_goal ?? '') . "\n";
            $prompt .= "健康・生活: " . ($guardianKakehashi->domain_health_life ?? '') . "\n";
            $prompt .= "運動・感覚: " . ($guardianKakehashi->domain_motor_sensory ?? '') . "\n";
            $prompt .= "認知・行動: " . ($guardianKakehashi->domain_cognitive_behavior ?? '') . "\n";
            $prompt .= "言語・コミュニケーション: " . ($guardianKakehashi->domain_language_communication ?? '') . "\n";
            $prompt .= "人間関係・社会性: " . ($guardianKakehashi->domain_social_relations ?? '') . "\n\n";
        }

        if ($staffKakehashi) {
            $prompt .= "【スタッフからのかけはし（提出日: " . ($staffKakehashi->submitted_at ?? '不明') . "）】\n";
            $prompt .= "本人の願い: " . ($staffKakehashi->student_wish ?? '') . "\n";
            $prompt .= "短期目標: " . ($staffKakehashi->short_term_goal ?? '') . "\n";
            $prompt .= "長期目標: " . ($staffKakehashi->long_term_goal ?? '') . "\n";
            $prompt .= "健康・生活: " . ($staffKakehashi->health_life ?? '') . "\n";
            $prompt .= "運動・感覚: " . ($staffKakehashi->motor_sensory ?? '') . "\n";
            $prompt .= "認知・行動: " . ($staffKakehashi->cognitive_behavior ?? '') . "\n";
            $prompt .= "言語・コミュニケーション: " . ($staffKakehashi->language_communication ?? '') . "\n";
            $prompt .= "人間関係・社会性: " . ($staffKakehashi->social_relations ?? '') . "\n\n";
        }

        if ($latestMonitoring) {
            $prompt .= "【直近のモニタリング（実施日: " . $latestMonitoring->monitoring_date . "）】\n";
            $prompt .= "総合所見: " . ($latestMonitoring->overall_comment ?? '') . "\n";
            foreach ($latestMonitoring->details ?? [] as $md) {
                $category = $md->planDetail->category ?? '';
                $subCategory = $md->planDetail->sub_category ?? '';
                if ($category || $subCategory) {
                    $prompt .= $category . " - " . $subCategory . ": " . ($md->achievement_status ?? '') . " / " . ($md->monitoring_comment ?? '') . "\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "上記のデータを踏まえて、この個別支援計画がどのような根拠に基づいて作成されたかを説明する文書を作成してください。\n";
        $prompt .= "見出しをつけず、自然な文章で記述してください。";

        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'OpenAI APIキーが設定されていません。'], 422);
            }

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは児童発達支援・放課後等デイサービスの専門家です。保護者に対して丁寧で分かりやすい説明を行います。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 2000,
            ]);

            $basisContent = $response->choices[0]->message->content;

            // データベースに保存
            $plan->update([
                'basis_content'      => $basisContent,
                'basis_generated_at' => now(),
            ]);

            // ログ保存
            try {
                \App\Models\AiGenerationLog::create([
                    'user_id'       => $request->user()->id,
                    'model'         => 'gpt-5.4-mini-2026-03-17',
                    'prompt_type'   => 'basis',
                    'input_tokens'  => $response->usage->promptTokens ?? null,
                    'output_tokens' => $response->usage->completionTokens ?? null,
                    'student_id'    => $studentId,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI log failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'basis_content'      => $basisContent,
                    'basis_generated_at' => $plan->basis_generated_at,
                ],
                'message' => '全体所感を生成しました。',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '全体所感の生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 面談記録から「本人の願い」をAI生成（レガシー準拠）
     */
    public function generateWishFromInterview(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        // 6か月以内の面談記録を取得（児童の願いがあるもの）
        $sixMonthsAgo = now()->subMonths(6)->toDateString();
        $interviews = \App\Models\StudentInterview::where('student_id', $student->id)
            ->where('interview_date', '>=', $sixMonthsAgo)
            ->whereNotNull('child_wish')
            ->where('child_wish', '!=', '')
            ->orderByDesc('interview_date')
            ->limit(5)
            ->get();

        if ($interviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '6か月以内に「児童の願い」が記録された面談記録がありません。',
            ], 422);
        }

        // 面談記録の「児童の願い」を集約
        $wishesText = '';
        foreach ($interviews as $interview) {
            $date = date('Y年m月d日', strtotime($interview->interview_date));
            $wishesText .= "【{$date}の面談】\n";
            $wishesText .= $interview->child_wish . "\n\n";
        }

        $prompt = "あなたは発達支援・特別支援教育の専門スタッフです。以下は生徒との面談記録から抜粋した「児童の願い」です。これらの内容を整理・統合して、個別支援計画に記載する「本人の願い」として200〜300文字程度でまとめてください。\n\n";
        $prompt .= "【生徒名】\n" . $student->student_name . "\n\n";
        $prompt .= "【面談記録からの「児童の願い」】\n" . $wishesText . "\n";
        $prompt .= "【作成の指針】\n";
        $prompt .= "- 複数の面談記録がある場合は、共通するテーマや一貫した願いを中心にまとめる\n";
        $prompt .= "- 児童の言葉や表現をできるだけ活かしながら、読みやすく整理する\n";
        $prompt .= "- 抽象的すぎる表現は避け、具体的な願いや目標として記述する\n";
        $prompt .= "- 肯定的で前向きな表現を心がける\n";
        $prompt .= "- 「〜したい」「〜になりたい」「〜ができるようになりたい」などの表現を使う\n";
        $prompt .= "- 本人の気持ちや希望を尊重した内容にする\n\n";
        $prompt .= "「本人の願い」を200〜300文字程度の文章で記述してください（JSON不要、テキストのみ）：";

        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'OpenAI APIキーが設定されていません。'], 422);
            }

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは発達支援・特別支援教育の専門スタッフです。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 600,
            ]);

            $generatedWish = trim($response->choices[0]->message->content);

            // ログ保存
            try {
                \App\Models\AiGenerationLog::create([
                    'user_id'       => $request->user()->id,
                    'model'         => 'gpt-5.4-mini-2026-03-17',
                    'prompt_type'   => 'wish_from_interview',
                    'input_tokens'  => $response->usage->promptTokens ?? null,
                    'output_tokens' => $response->usage->completionTokens ?? null,
                    'student_id'    => $student->id,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI log failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'wish' => $generatedWish,
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
     * 署名を要求する（保護者に通知を送信）
     */
    public function requestSignature(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $plan->load('student');
        $this->authorizeClassroom($request->user(), $plan->student);

        $student = $plan->student;
        if (!$student || !$student->guardian_id) {
            return response()->json([
                'success' => false,
                'message' => '保護者が設定されていません。',
            ], 422);
        }

        $guardian = User::where('id', $student->guardian_id)->where('is_active', true)->first();
        if (!$guardian) {
            return response()->json([
                'success' => false,
                'message' => '有効な保護者アカウントが見つかりません。',
            ], 422);
        }

        try {
            $notificationService = app(NotificationService::class);
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

            $notificationService->notify(
                $guardian,
                'signature_request',
                '個別支援計画書の署名をお願いします',
                "{$student->student_name}さんの個別支援計画書への署名をお願いいたします。アプリからご確認ください。",
                ['url' => "{$frontendUrl}/guardian/support-plan"]
            );

            return response()->json([
                'success' => true,
                'message' => '保護者に署名要求を送信しました。',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Signature request notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '通知の送信に失敗しました。',
            ], 500);
        }
    }

    /**
     * 教室アクセス権限チェック
     */
    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && !in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }

    /**
     * 達成時期が未設定の場合にデフォルト値を自動設定
     * - short_term_goal_date: 作成日 + 6ヶ月
     * - long_term_goal_date: 作成日 + 1年
     * - details.*.achievement_date: 作成日 + 6ヶ月
     */
    private function fillDefaultDates(array $data): array
    {
        $createdDate = $data['created_date'] ?? null;
        if (!$createdDate) {
            return $data;
        }

        $base = \Carbon\Carbon::parse($createdDate);
        $shortTermDefault = $base->copy()->addMonths(6)->format('Y-m-d');
        $longTermDefault = $base->copy()->addYear()->format('Y-m-d');

        if (empty($data['short_term_goal_date'])) {
            $data['short_term_goal_date'] = $shortTermDefault;
        }
        if (empty($data['long_term_goal_date'])) {
            $data['long_term_goal_date'] = $longTermDefault;
        }

        if (!empty($data['details'])) {
            foreach ($data['details'] as &$detail) {
                if (empty($detail['achievement_date'])) {
                    $detail['achievement_date'] = $shortTermDefault;
                }
            }
            unset($detail);
        }

        return $data;
    }

    /**
     * 提出時の達成時期バリデーション
     */
    private function validateDatesForSubmission(array $data): array
    {
        $errors = [];
        $createdDate = $data['created_date'] ?? null;

        if (empty($data['long_term_goal_date'])) {
            $errors[] = '長期目標の達成時期を設定してください。';
        }
        if (empty($data['short_term_goal_date'])) {
            $errors[] = '短期目標の達成時期を設定してください。';
        }

        // 達成時期は作成日より後であること
        if ($createdDate && !empty($data['short_term_goal_date'])) {
            if ($data['short_term_goal_date'] <= $createdDate) {
                $errors[] = '短期目標の達成時期は作成日より後の日付にしてください。';
            }
        }
        if ($createdDate && !empty($data['long_term_goal_date'])) {
            if ($data['long_term_goal_date'] <= $createdDate) {
                $errors[] = '長期目標の達成時期は作成日より後の日付にしてください。';
            }
        }

        // 長期目標は短期目標以降であること
        if (!empty($data['short_term_goal_date']) && !empty($data['long_term_goal_date'])) {
            if ($data['long_term_goal_date'] < $data['short_term_goal_date']) {
                $errors[] = '長期目標の達成時期は短期目標の達成時期以降にしてください。';
            }
        }

        // 明細の達成時期チェック
        if (!empty($data['details'])) {
            foreach ($data['details'] as $i => $detail) {
                $hasContent = !empty($detail['goal'] ?? $detail['support_goal'] ?? null);
                if ($hasContent && empty($detail['achievement_date'])) {
                    $errors[] = '支援目標の達成時期が未設定の項目があります。';
                    break;
                }
            }
        }

        return $errors;
    }
}
