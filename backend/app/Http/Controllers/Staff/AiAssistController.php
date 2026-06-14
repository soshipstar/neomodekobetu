<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\AbilityGrowthStage;
use App\Support\PiiMasker;
use App\Support\StudentCohort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 支援知蒸留エンジン D2: AI記録支援(問い返し・仮説提示)。
 *
 * 「AIが記録を書く」のではなく「AIが問いと因果仮説の候補を返す」。考えさせる支援。
 * 既存の下書き生成とは併存(追加機能)。外部AIへ送る前に PiiMasker でマスク(A005準拠)。
 *
 * 分類: api
 */
class AiAssistController extends Controller
{
    /** POST /api/staff/ai-assist/inquiry {text, student_id?} */
    public function inquiry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:2000',
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        // 児童指定があればマスカー作成 + 越境チェック + 文脈(対象/成長段階)
        $masker = new PiiMasker();
        $context = '';
        if (! empty($validated['student_id'])) {
            $student = Student::find($validated['student_id']);
            if ($student) {
                $this->authorizeStudent($request->user(), $student);
                $masker = PiiMasker::forStudent($student);
                $cohort = StudentCohort::forStudent($student);
                $stage = AbilityGrowthStage::forStudent($student);
                $context = "対象の参考: コホート={$cohort} 成長段階={$stage}\n";
            }
        }

        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'OpenAI APIキーが設定されていません。'], 422);
            }

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model' => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'あなたは児童発達支援の経験豊富な専門家です。職員の記録メモに対して、'
                            .'記録を書き換えるのではなく、観察を深める「問い」と、結果の原因に関する「因果仮説の候補」を返します。'
                            .'入力に無い事実は創作しないでください。問いは具体的な場面・支援・変化を引き出すものにします。'
                            .'必ず指定のJSON形式のみで出力してください。',
                    ],
                    [
                        'role' => 'user',
                        // 氏名マスク + 構造化PIIスクラブ(日付/電話/敬称付き他者名)で外部送信前に要配慮情報を落とす
                        'content' => PiiMasker::scrubStructuredPii($masker->mask(
                            "次の記録について、(1)観察を深める問いを3〜5個、(2)考えられる因果仮説の候補を3〜5個、提示してください。\n\n"
                            ."【記録】\n{$validated['text']}\n\n{$context}"
                            ."以下のJSON形式で出力:\n{\"questions\": [\"...\"], \"hypotheses\": [\"...\"]}"
                        )),
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.5,
                'max_completion_tokens' => 700,
            ]);

            $result = json_decode($response->choices[0]->message->content ?? '{}', true);
            if (is_array($result)) {
                $result = $masker->unmaskArray($result); // 仮名→実名(職員が読むため)
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'questions' => array_values(array_filter((array) ($result['questions'] ?? []), 'is_string')),
                    'hypotheses' => array_values(array_filter((array) ($result['hypotheses'] ?? []), 'is_string')),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AiAssist.inquiry failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'AIアシストでエラーが発生しました: '.$e->getMessage()], 500);
        }
    }

    private function authorizeStudent($user, Student $student): void
    {
        if ($user->classroom_id && ! in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この児童へのアクセス権限がありません。');
        }
    }
}
