<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationLog;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use App\Models\Student;
use App\Models\StudentRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI;

class KakehashiController extends Controller
{
    /**
     * 生徒のかけはし一覧を取得（期間ごと）
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $periods = KakehashiPeriod::where('student_id', $student->id)
            ->with(['staffEntries', 'guardianEntries'])
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

        $pdf = Pdf::loadView('pdf.kakehashi', [
            'period'          => $period,
            'student'         => $period->student,
            'classroom'       => $period->student->classroom ?? null,
            'staffEntries'    => $period->staffEntries,
            'guardianEntries' => $period->guardianEntries,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = 'kakehashi_' . ($period->student->student_name ?? $period->id) . '_' . $period->start_date . '.pdf';

        return $pdf->download($filename);
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

        // 期間内の連絡帳データを取得
        $records = StudentRecord::where('student_id', $student->id)
            ->whereHas('dailyRecord', function ($q) use ($period) {
                $q->whereBetween('record_date', [$period->start_date, $period->end_date]);
            })
            ->with('dailyRecord:id,record_date,activity_name')
            ->orderBy('id')
            ->get();

        $recordsText = $records->map(function ($r) {
            $date = $r->dailyRecord->record_date ?? '';
            $parts = ["[{$date}]"];
            if ($r->domain1) $parts[] = "{$r->domain1}: {$r->domain1_content}";
            if ($r->domain2) $parts[] = "{$r->domain2}: {$r->domain2_content}";
            if ($r->daily_note) $parts[] = "メモ: {$r->daily_note}";
            return implode(' / ', $parts);
        })->implode("\n");

        // 前回のかけはしを取得
        $previousEntry = KakehashiStaff::where('student_id', $student->id)
            ->where('period_id', '!=', $period->id)
            ->whereHas('period', function ($q) use ($period) {
                $q->where('end_date', '<', $period->start_date);
            })
            ->orderByDesc('created_at')
            ->first();

        $previousText = '';
        if ($previousEntry) {
            $previousText = "【前回のかけはし】\n"
                . "・本人の願い: {$previousEntry->student_wish}\n"
                . "・短期目標: {$previousEntry->short_term_goal}\n"
                . "・長期目標: {$previousEntry->long_term_goal}\n\n";
        }

        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI APIキーが設定されていません。管理者に連絡してください。',
                ], 422);
            }

            $client = OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'あなたは放課後等デイサービスの児童発達支援管理責任者です。'
                            . 'かけはし（個別支援計画の架け橋書類）の職員記入欄を作成します。'
                            . '児童の連絡帳データに基づき、5領域ごとの支援内容を具体的に記述してください。'
                            . 'JSON形式のみで回答してください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "【児童名】{$student->student_name}\n"
                            . "【教室】" . ($student->classroom->classroom_name ?? '') . "\n"
                            . "【かけはし期間】{$period->start_date} ～ {$period->end_date}\n\n"
                            . $previousText
                            . "【期間内の連絡帳記録（{$records->count()}件）】\n"
                            . ($recordsText ?: '（記録なし）') . "\n\n"
                            . "以下のJSON形式で出力してください:\n"
                            . "{\n"
                            . "  \"student_wish\": \"本人の願い（児童本人が望んでいること）\",\n"
                            . "  \"short_term_goal\": \"短期目標（今期6ヶ月の具体的目標）\",\n"
                            . "  \"long_term_goal\": \"長期目標（1年以上の長期的な目標）\",\n"
                            . "  \"health_life\": \"健康・生活 領域の支援内容\",\n"
                            . "  \"motor_sensory\": \"運動・感覚 領域の支援内容\",\n"
                            . "  \"cognitive_behavior\": \"認知・行動 領域の支援内容\",\n"
                            . "  \"language_communication\": \"言語・コミュニケーション 領域の支援内容\",\n"
                            . "  \"social_relations\": \"人間関係・社会性 領域の支援内容\"\n"
                            . "}",
                    ],
                ],
                'response_format'       => ['type' => 'json_object'],
                'temperature'           => 0.6,
                'max_completion_tokens' => 3000,
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            // ログ保存
            try {
                AiGenerationLog::create([
                    'user_id'       => $request->user()->id,
                    'model'         => 'gpt-4o',
                    'prompt_type'   => 'kakehashi',
                    'input_tokens'  => $response->usage->promptTokens ?? null,
                    'output_tokens' => $response->usage->completionTokens ?? null,
                    'student_id'    => $student->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to log AI generation', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success'      => true,
                'data'         => $result ?? [],
                'record_count' => $records->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
