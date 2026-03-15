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

        $plan->load(['student', 'details']);
        $student = $plan->student;

        // 過去の面接記録や連絡帳から情報を取得
        $interviews = $student->interviews()
            ->orderByDesc('interview_date')
            ->limit(5)
            ->get();

        $records = $student->dailyRecords()
            ->with('dailyRecord')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $interviewText = $interviews->map(fn ($i) => "[{$i->interview_date}] {$i->interview_content}")->implode("\n");
        $recordsText = $records->map(function ($r) {
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

            // Markdownコードブロックを除去
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
            ->setOption('defaultFont', 'DejaVu Sans');

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
     * 教室アクセス権限チェック
     */
    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
