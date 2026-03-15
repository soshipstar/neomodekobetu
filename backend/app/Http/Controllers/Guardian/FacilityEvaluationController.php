<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacilityEvaluationController extends Controller
{
    /**
     * 保護者の評価回答状況を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // 収集中の評価期間を取得
        $periodQuery = DB::table('facility_evaluation_periods')
            ->where('status', 'collecting');

        if ($classroomId) {
            $periodQuery->where('classroom_id', $classroomId);
        }

        $period = $periodQuery->orderByDesc('fiscal_year')->first();

        if (! $period) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'period'     => null,
                    'evaluation' => null,
                    'questions'  => [],
                    'message'    => '現在、回答を受け付けている評価はありません。',
                ],
            ]);
        }

        // 質問一覧を取得
        $questions = DB::table('facility_evaluation_questions')
            ->where('question_type', 'guardian')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('question_number')
            ->get();

        // 既存の回答を取得
        $evaluation = DB::table('facility_guardian_evaluations')
            ->where('period_id', $period->id)
            ->where('guardian_id', $user->id)
            ->first();

        $answers = [];
        if ($evaluation) {
            $answers = DB::table('facility_guardian_evaluation_answers')
                ->where('evaluation_id', $evaluation->id)
                ->get()
                ->keyBy('question_id');
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'period'     => $period,
                'evaluation' => $evaluation,
                'questions'  => $questions,
                'answers'    => $answers,
            ],
        ]);
    }

    /**
     * 施設評価回答を保存・提出
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $validated = $request->validate([
            'period_id'              => 'required|exists:facility_evaluation_periods,id',
            'answers'                => 'required|array',
            'answers.*.question_id'  => 'required|exists:facility_evaluation_questions,id',
            'answers.*.answer'       => 'nullable|string|in:yes,neutral,no,unknown',
            'answers.*.comment'      => 'nullable|string|max:1000',
            'is_submit'              => 'boolean',
        ]);

        $isSubmit = $validated['is_submit'] ?? false;

        // 提出時は全質問回答チェック
        if ($isSubmit) {
            $totalQuestions = DB::table('facility_evaluation_questions')
                ->where('question_type', 'guardian')
                ->where('is_active', true)
                ->count();

            $answeredCount = collect($validated['answers'])
                ->filter(fn ($a) => ! empty($a['answer']))
                ->count();

            if ($answeredCount < $totalQuestions) {
                return response()->json([
                    'success' => false,
                    'message' => '未回答の質問があります。すべての質問に回答してください。',
                ], 422);
            }
        }

        DB::transaction(function () use ($user, $validated, $isSubmit) {
            // 評価レコードを取得または作成
            $evaluation = DB::table('facility_guardian_evaluations')
                ->where('period_id', $validated['period_id'])
                ->where('guardian_id', $user->id)
                ->first();

            if (! $evaluation) {
                $evaluationId = DB::table('facility_guardian_evaluations')->insertGetId([
                    'period_id'    => $validated['period_id'],
                    'guardian_id'  => $user->id,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } else {
                $evaluationId = $evaluation->id;

                // 提出済みの場合は変更不可
                if ($evaluation->is_submitted) {
                    abort(422, 'この評価は既に提出済みです。');
                }
            }

            // 回答を保存
            foreach ($validated['answers'] as $answerData) {
                DB::table('facility_guardian_evaluation_answers')->updateOrInsert(
                    [
                        'evaluation_id' => $evaluationId,
                        'question_id'   => $answerData['question_id'],
                    ],
                    [
                        'answer'  => $answerData['answer'] ?? null,
                        'comment' => $answerData['comment'] ?? '',
                    ]
                );
            }

            // 提出
            if ($isSubmit) {
                DB::table('facility_guardian_evaluations')
                    ->where('id', $evaluationId)
                    ->update([
                        'is_submitted'  => true,
                        'submitted_at'  => now(),
                        'updated_at'    => now(),
                    ]);
            }
        });

        $message = $isSubmit
            ? 'ご回答いただきありがとうございました。'
            : '下書きを保存しました。';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
