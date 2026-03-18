<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacilityEvaluationController extends Controller
{
    /**
     * 評価期間の一覧を取得（教室フィルタリング対応）
     */
    public function periods(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = DB::table('facility_evaluation_periods')
            ->orderByDesc('fiscal_year')
            ->orderByDesc('created_at');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $periods = $query->get();

        // 各期間の回答状況を集計（教室フィルタリング対応）
        foreach ($periods as $period) {
            if ($classroomId) {
                $period->guardian_submitted = DB::table('facility_guardian_evaluations as e')
                    ->join('users as u', 'e.guardian_id', '=', 'u.id')
                    ->where('e.period_id', $period->id)
                    ->where('e.is_submitted', true)
                    ->where('u.classroom_id', $classroomId)
                    ->count();
                $period->guardian_total = DB::table('users')
                    ->where('user_type', 'guardian')
                    ->where('is_active', true)
                    ->where('classroom_id', $classroomId)
                    ->count();
                $period->staff_submitted = DB::table('facility_staff_evaluations as e')
                    ->join('users as u', 'e.staff_id', '=', 'u.id')
                    ->where('e.period_id', $period->id)
                    ->where('e.is_submitted', true)
                    ->where('u.classroom_id', $classroomId)
                    ->count();
                $period->staff_total = DB::table('users')
                    ->whereIn('user_type', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->where('classroom_id', $classroomId)
                    ->count();
            } else {
                $period->guardian_submitted = DB::table('facility_guardian_evaluations')
                    ->where('period_id', $period->id)
                    ->where('is_submitted', true)
                    ->count();
                $period->guardian_total = DB::table('users')
                    ->where('user_type', 'guardian')
                    ->where('is_active', true)
                    ->count();
                $period->staff_submitted = DB::table('facility_staff_evaluations')
                    ->where('period_id', $period->id)
                    ->where('is_submitted', true)
                    ->count();
                $period->staff_total = DB::table('users')
                    ->whereIn('user_type', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->count();
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $periods,
        ]);
    }

    /**
     * 評価期間を新規作成（重複チェック + classroom_id付き）
     */
    public function createPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year'       => 'required|integer|min:2020|max:2099',
            'title'             => 'nullable|string|max:255',
            'guardian_deadline'  => 'nullable|date',
            'staff_deadline'    => 'nullable|date',
        ]);

        $user = $request->user();
        $classroomId = $user->classroom_id;

        // タイトル自動生成（レガシーと同じ）
        $title = $validated['title'] ?: "{$validated['fiscal_year']}年度 事業所評価";

        // 重複チェック（教室ごとに年度で一意）
        $exists = DB::table('facility_evaluation_periods')
            ->where('fiscal_year', $validated['fiscal_year'])
            ->where('classroom_id', $classroomId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "{$validated['fiscal_year']}年度の評価期間は既に作成されています。",
            ], 422);
        }

        $id = DB::table('facility_evaluation_periods')->insertGetId([
            'classroom_id'      => $classroomId,
            'fiscal_year'       => $validated['fiscal_year'],
            'title'             => $title,
            'guardian_deadline'  => $validated['guardian_deadline'] ?? null,
            'staff_deadline'    => $validated['staff_deadline'] ?? null,
            'status'            => 'draft',
            'created_by'        => $user->id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => DB::table('facility_evaluation_periods')->find($id),
            'message' => '評価期間を作成しました。',
        ], 201);
    }

    /**
     * 評価期間のステータスを更新
     */
    public function updatePeriod(Request $request, int $periodId): JsonResponse
    {
        $validated = $request->validate([
            'status'            => 'sometimes|in:draft,collecting,aggregating,published',
            'title'             => 'sometimes|string|max:255',
            'guardian_deadline'  => 'nullable|date',
            'staff_deadline'    => 'nullable|date',
        ]);

        DB::table('facility_evaluation_periods')
            ->where('id', $periodId)
            ->update(array_merge($validated, ['updated_at' => now()]));

        return response()->json([
            'success' => true,
            'data'    => DB::table('facility_evaluation_periods')->find($periodId),
            'message' => 'ステータスを更新しました。',
        ]);
    }

    /**
     * 回答状況（保護者・スタッフ）の詳細一覧
     * レガシーと同じく全アクティブユーザーを表示
     */
    public function responseStatus(Request $request, int $periodId): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // 保護者の回答状況（全アクティブ保護者を表示）
        $guardianQuery = DB::table('users as u')
            ->leftJoin('facility_guardian_evaluations as e', function ($join) use ($periodId) {
                $join->on('u.id', '=', 'e.guardian_id')
                     ->where('e.period_id', '=', $periodId);
            })
            ->where('u.user_type', 'guardian')
            ->where('u.is_active', true);

        if ($classroomId) {
            $guardianQuery->where('u.classroom_id', $classroomId);
        }

        $guardianResponses = $guardianQuery->select(
            'u.id',
            'u.full_name as guardian_name',
            DB::raw('COALESCE(e.is_submitted, false) as is_submitted'),
            'e.submitted_at',
            'e.created_at as started_at'
        )->orderByDesc('e.is_submitted')->orderBy('u.full_name')->get();

        // スタッフの回答状況（全アクティブスタッフを表示）
        $staffQuery = DB::table('users as u')
            ->leftJoin('facility_staff_evaluations as e', function ($join) use ($periodId) {
                $join->on('u.id', '=', 'e.staff_id')
                     ->where('e.period_id', '=', $periodId);
            })
            ->whereIn('u.user_type', ['staff', 'admin'])
            ->where('u.is_active', true);

        if ($classroomId) {
            $staffQuery->where('u.classroom_id', $classroomId);
        }

        $staffResponses = $staffQuery->select(
            'u.id',
            'u.full_name as staff_name',
            DB::raw('COALESCE(e.is_submitted, false) as is_submitted'),
            'e.submitted_at',
            'e.created_at as started_at'
        )->orderByDesc('e.is_submitted')->orderBy('u.full_name')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'guardian_responses' => $guardianResponses,
                'staff_responses'   => $staffResponses,
            ],
        ]);
    }

    /**
     * 施設評価の集計サマリーを取得（保護者評価）
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $periodId = $request->input('period_id');

        // 評価期間を取得
        $periodQuery = DB::table('facility_evaluation_periods');
        if ($periodId) {
            $periodQuery->where('id', $periodId);
        } else {
            $periodQuery->orderByDesc('fiscal_year')->orderByDesc('created_at');
        }
        $period = $periodQuery->first();

        if (! $period) {
            return response()->json([
                'success' => true,
                'data'    => ['period' => null, 'summary' => [], 'comments' => []],
            ]);
        }

        // 保護者評価の集計（教室フィルタリング）
        $summaryQuery = DB::table('facility_guardian_evaluation_answers as a')
            ->join('facility_guardian_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true);

        if ($classroomId) {
            $summaryQuery->join('users as u', 'e.guardian_id', '=', 'u.id')
                         ->where('u.classroom_id', $classroomId);
        }

        $guardianSummary = $summaryQuery->select(
            'q.id as question_id',
            'q.question_number',
            'q.question_text',
            'q.category',
            DB::raw("SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) as yes_count"),
            DB::raw("SUM(CASE WHEN a.answer = 'neutral' THEN 1 ELSE 0 END) as neutral_count"),
            DB::raw("SUM(CASE WHEN a.answer = 'no' THEN 1 ELSE 0 END) as no_count"),
            DB::raw("SUM(CASE WHEN a.answer = 'unknown' THEN 1 ELSE 0 END) as unknown_count"),
            DB::raw('COUNT(*) as total_count')
        )
            ->groupBy('q.id', 'q.question_number', 'q.question_text', 'q.category')
            ->orderBy('q.question_number')
            ->get();

        // 回答数の統計
        $respondentsQuery = DB::table('facility_guardian_evaluations')
            ->where('period_id', $period->id)
            ->where('is_submitted', true);
        if ($classroomId) {
            $respondentsQuery->join('users as u', 'facility_guardian_evaluations.guardian_id', '=', 'u.id')
                             ->where('u.classroom_id', $classroomId);
        }
        $totalRespondents = $respondentsQuery->count();

        // 保護者コメント一覧（質問ごとにグループ化）
        $commentsQuery = DB::table('facility_guardian_evaluation_answers as a')
            ->join('facility_guardian_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true)
            ->whereNotNull('a.comment')
            ->where('a.comment', '!=', '');

        if ($classroomId) {
            $commentsQuery->join('users as u', 'e.guardian_id', '=', 'u.id')
                          ->where('u.classroom_id', $classroomId);
        }

        $comments = $commentsQuery
            ->select('q.id as question_id', 'q.question_number', 'q.question_text', 'a.comment')
            ->orderBy('q.question_number')
            ->get();

        // 集計結果テーブルからfacility_commentとcomment_summaryを取得
        $savedSummaries = DB::table('facility_evaluation_summaries')
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('question_id');

        // summaryにfacility_commentとcomment_summaryを付加
        foreach ($guardianSummary as $item) {
            $saved = $savedSummaries->get($item->question_id);
            $item->facility_comment = $saved->facility_comment ?? null;
            $item->comment_summary = $saved->comment_summary ?? null;
            $item->yes_percentage = $item->total_count > 0
                ? round(($item->yes_count / ($item->yes_count + $item->neutral_count + $item->no_count)) * 100, 1)
                : 0;
        }

        // コメントを質問IDごとにグループ化
        $commentsByQuestion = [];
        foreach ($comments as $c) {
            $commentsByQuestion[$c->question_id][] = $c->comment;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'period'              => $period,
                'total_respondents'   => $totalRespondents,
                'summary'             => $guardianSummary,
                'comments'            => $comments,
                'comments_by_question' => $commentsByQuestion,
            ],
        ]);
    }

    /**
     * 集計実行（保護者・スタッフ両方の集計結果をfacility_evaluation_summariesに保存）
     */
    public function aggregate(Request $request): JsonResponse
    {
        $periodId = $request->input('period_id');
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $period = DB::table('facility_evaluation_periods')->find($periodId);
        if (! $period) {
            return response()->json(['success' => false, 'message' => '評価期間が見つかりません。'], 404);
        }

        try {
            // 保護者評価の集計
            $guardianQuery = DB::table('facility_evaluation_questions as q')
                ->leftJoin('facility_guardian_evaluation_answers as a', 'q.id', '=', 'a.question_id')
                ->leftJoin('facility_guardian_evaluations as e', function ($join) use ($periodId) {
                    $join->on('a.evaluation_id', '=', 'e.id')
                         ->where('e.period_id', '=', $periodId)
                         ->where('e.is_submitted', '=', true);
                })
                ->where('q.question_type', 'guardian');

            if ($classroomId) {
                $guardianQuery->leftJoin('users as u', 'e.guardian_id', '=', 'u.id')
                              ->where(function ($q) use ($classroomId) {
                                  $q->where('u.classroom_id', $classroomId)->orWhereNull('u.id');
                              });
            }

            $guardianStats = $guardianQuery->select(
                'q.id as question_id',
                'q.question_type',
                DB::raw("SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) as yes_count"),
                DB::raw("SUM(CASE WHEN a.answer = 'neutral' THEN 1 ELSE 0 END) as neutral_count"),
                DB::raw("SUM(CASE WHEN a.answer = 'no' THEN 1 ELSE 0 END) as no_count"),
                DB::raw("SUM(CASE WHEN a.answer = 'unknown' THEN 1 ELSE 0 END) as unknown_count"),
                DB::raw('COUNT(a.answer) as total_count')
            )->groupBy('q.id', 'q.question_type')->get();

            // スタッフ評価の集計
            $staffQuery = DB::table('facility_evaluation_questions as q')
                ->leftJoin('facility_staff_evaluation_answers as a', 'q.id', '=', 'a.question_id')
                ->leftJoin('facility_staff_evaluations as e', function ($join) use ($periodId) {
                    $join->on('a.evaluation_id', '=', 'e.id')
                         ->where('e.period_id', '=', $periodId)
                         ->where('e.is_submitted', '=', true);
                })
                ->where('q.question_type', 'staff');

            if ($classroomId) {
                $staffQuery->leftJoin('users as u', 'e.staff_id', '=', 'u.id')
                           ->where(function ($q) use ($classroomId) {
                               $q->where('u.classroom_id', $classroomId)->orWhereNull('u.id');
                           });
            }

            $staffStats = $staffQuery->select(
                'q.id as question_id',
                'q.question_type',
                DB::raw("SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) as yes_count"),
                DB::raw("SUM(CASE WHEN a.answer = 'neutral' THEN 1 ELSE 0 END) as neutral_count"),
                DB::raw("SUM(CASE WHEN a.answer = 'no' THEN 1 ELSE 0 END) as no_count"),
                DB::raw("SUM(CASE WHEN a.answer = 'unknown' THEN 1 ELSE 0 END) as unknown_count"),
                DB::raw('COUNT(a.answer) as total_count')
            )->groupBy('q.id', 'q.question_type')->get();

            // 集計結果を保存
            foreach ($guardianStats->merge($staffStats) as $stat) {
                $total = $stat->yes_count + $stat->neutral_count + $stat->no_count;
                $yesPercentage = $total > 0 ? round(($stat->yes_count / $total) * 100, 1) : 0;

                DB::table('facility_evaluation_summaries')->updateOrInsert(
                    [
                        'period_id'   => $periodId,
                        'question_id' => $stat->question_id,
                    ],
                    [
                        'yes_count'      => $stat->yes_count,
                        'neutral_count'  => $stat->neutral_count,
                        'no_count'       => $stat->no_count,
                        'unknown_count'  => $stat->unknown_count,
                        'yes_percentage' => $yesPercentage,
                        'updated_at'     => now(),
                    ]
                );
            }

            // ステータスをaggregatingに更新
            DB::table('facility_evaluation_periods')
                ->where('id', $periodId)
                ->update(['status' => 'aggregating', 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => '集計が完了しました。',
            ]);
        } catch (\Exception $e) {
            Log::error('Facility evaluation aggregate error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '集計エラー: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 事業所コメントを保存（質問ごと）
     */
    public function saveFacilityComment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id'        => 'required|integer',
            'question_id'     => 'required|integer',
            'facility_comment' => 'nullable|string',
        ]);

        DB::table('facility_evaluation_summaries')->updateOrInsert(
            [
                'period_id'   => $validated['period_id'],
                'question_id' => $validated['question_id'],
            ],
            [
                'facility_comment' => $validated['facility_comment'] ?? '',
                'updated_at'       => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '事業所コメントを保存しました。',
        ]);
    }

    /**
     * 施設評価の個別回答一覧を取得
     */
    public function responses(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $periodId = $request->input('period_id');

        if (! $periodId) {
            $period = DB::table('facility_evaluation_periods')
                ->orderByDesc('fiscal_year')
                ->orderByDesc('created_at')
                ->first();
            $periodId = $period?->id;
        }

        if (! $periodId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('facility_guardian_evaluations as e')
            ->join('users as u', 'e.guardian_id', '=', 'u.id')
            ->where('e.period_id', $periodId)
            ->where('e.is_submitted', true);

        if ($classroomId) {
            $query->where('u.classroom_id', $classroomId);
        }

        $evaluations = $query->select(
            'e.id',
            'e.guardian_id',
            'u.full_name as guardian_name',
            'e.is_submitted',
            'e.submitted_at'
        )
            ->orderBy('e.submitted_at')
            ->get();

        // 各評価の回答詳細を付加
        foreach ($evaluations as $eval) {
            $eval->answers = DB::table('facility_guardian_evaluation_answers as a')
                ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
                ->where('a.evaluation_id', $eval->id)
                ->select('q.question_number', 'q.question_text', 'q.category', 'a.answer', 'a.comment')
                ->orderBy('q.question_number')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $evaluations,
        ]);
    }

    /**
     * 個別回答のPDFデータを取得
     */
    public function responsePdf(Request $request, int $evaluation): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = DB::table('facility_guardian_evaluations as e')
            ->join('users as u', 'e.guardian_id', '=', 'u.id')
            ->where('e.id', $evaluation)
            ->where('e.is_submitted', true);

        if ($classroomId) {
            $query->where('u.classroom_id', $classroomId);
        }

        $eval = $query->select('e.id', 'e.guardian_id', 'u.full_name as guardian_name', 'e.submitted_at')
            ->first();

        if (! $eval) {
            return response()->json(['success' => false, 'message' => '評価が見つかりません。'], 404);
        }

        $answers = DB::table('facility_guardian_evaluation_answers as a')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('a.evaluation_id', $eval->id)
            ->select('q.question_number', 'q.question_text', 'q.category', 'a.answer', 'a.comment')
            ->orderBy('q.question_number')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'evaluation' => $eval,
                'answers'    => $answers,
            ],
        ]);
    }

    /**
     * スタッフ自己評価フォームの取得
     */
    public function staffEvaluation(Request $request): JsonResponse
    {
        $user = $request->user();
        $periodId = $request->input('period_id');

        if (! $periodId) {
            $period = DB::table('facility_evaluation_periods')
                ->whereIn('status', ['collecting', 'aggregating'])
                ->orderByDesc('fiscal_year')
                ->first();
            $periodId = $period?->id;
        }

        if (! $periodId) {
            return response()->json([
                'success' => true,
                'data'    => ['period' => null, 'questions' => [], 'evaluation' => null],
            ]);
        }

        $period = DB::table('facility_evaluation_periods')->find($periodId);

        // スタッフ用の質問を取得
        $questions = DB::table('facility_evaluation_questions')
            ->where('question_type', 'staff')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('question_number')
            ->get();

        // 現在のユーザーの回答を取得
        $evaluation = DB::table('facility_staff_evaluations')
            ->where('period_id', $periodId)
            ->where('staff_id', $user->id)
            ->first();

        $answers = [];
        if ($evaluation) {
            $answerRows = DB::table('facility_staff_evaluation_answers')
                ->where('evaluation_id', $evaluation->id)
                ->get();
            foreach ($answerRows as $a) {
                $answers[$a->question_id] = [
                    'answer'           => $a->answer,
                    'comment'          => $a->comment,
                    'improvement_plan' => $a->improvement_plan ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'period'     => $period,
                'questions'  => $questions,
                'evaluation' => $evaluation,
                'answers'    => $answers,
            ],
        ]);
    }

    /**
     * スタッフ自己評価を保存
     */
    public function saveStaffEvaluation(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:facility_evaluation_periods,id',
            'answers'   => 'required|array',
            'answers.*.question_id'       => 'required|integer',
            'answers.*.answer'            => 'required|in:yes,neutral,no',
            'answers.*.comment'           => 'nullable|string',
            'answers.*.improvement_plan'  => 'nullable|string',
            'submit'    => 'nullable|boolean',
        ]);

        $periodId = $validated['period_id'];
        $isSubmit = $validated['submit'] ?? false;

        // 提出時のバリデーション
        if ($isSubmit) {
            $totalQuestions = DB::table('facility_evaluation_questions')
                ->where('question_type', 'staff')
                ->where('is_active', true)
                ->count();

            if (count($validated['answers']) < $totalQuestions) {
                return response()->json([
                    'success' => false,
                    'message' => 'すべての質問に回答してください。',
                ], 422);
            }

            // 「いいえ」回答に改善計画が必要
            foreach ($validated['answers'] as $answer) {
                if ($answer['answer'] === 'no' && empty($answer['improvement_plan'])) {
                    return response()->json([
                        'success' => false,
                        'message' => '「いいえ」と回答した質問には改善計画を入力してください。',
                    ], 422);
                }
            }
        }

        // 既存の評価を取得または作成
        $evaluation = DB::table('facility_staff_evaluations')
            ->where('period_id', $periodId)
            ->where('staff_id', $user->id)
            ->first();

        if (! $evaluation) {
            $evalId = DB::table('facility_staff_evaluations')->insertGetId([
                'period_id'    => $periodId,
                'staff_id'     => $user->id,
                'is_submitted' => $isSubmit,
                'submitted_at' => $isSubmit ? now() : null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } else {
            $evalId = $evaluation->id;

            if ($evaluation->is_submitted) {
                return response()->json([
                    'success' => false,
                    'message' => 'この自己評価は既に提出済みです。',
                ], 422);
            }

            DB::table('facility_staff_evaluations')
                ->where('id', $evalId)
                ->update([
                    'is_submitted' => $isSubmit || $evaluation->is_submitted,
                    'submitted_at' => $isSubmit ? now() : $evaluation->submitted_at,
                    'updated_at'   => now(),
                ]);
        }

        // 回答を保存
        foreach ($validated['answers'] as $answer) {
            DB::table('facility_staff_evaluation_answers')->updateOrInsert(
                [
                    'evaluation_id' => $evalId,
                    'question_id'   => $answer['question_id'],
                ],
                [
                    'answer'           => $answer['answer'],
                    'comment'          => $answer['comment'] ?? null,
                    'improvement_plan' => $answer['improvement_plan'] ?? null,
                    'updated_at'       => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $isSubmit ? '自己評価を提出しました。' : '下書きを保存しました。',
        ]);
    }

    /**
     * 事業所自己評価のサマリーを取得（スタッフ評価集計）
     */
    public function selfSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $periodId = $request->input('period_id');

        $periodQuery = DB::table('facility_evaluation_periods');
        if ($periodId) {
            $periodQuery->where('id', $periodId);
        } else {
            $periodQuery->orderByDesc('fiscal_year')->orderByDesc('created_at');
        }
        $period = $periodQuery->first();

        if (! $period) {
            return response()->json([
                'success' => true,
                'data'    => ['period' => null, 'summary' => [], 'self_summary_items' => []],
            ]);
        }

        // スタッフ自己評価の集計（教室フィルタリング）
        $query = DB::table('facility_staff_evaluation_answers as a')
            ->join('facility_staff_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true);

        if ($classroomId) {
            $query->join('users as u', 'e.staff_id', '=', 'u.id')
                  ->where('u.classroom_id', $classroomId);
        }

        $selfSummary = $query->select(
            'q.id as question_id',
            'q.question_number',
            'q.question_text',
            'q.category',
            DB::raw("SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) as yes_count"),
            DB::raw("SUM(CASE WHEN a.answer = 'neutral' THEN 1 ELSE 0 END) as neutral_count"),
            DB::raw("SUM(CASE WHEN a.answer = 'no' THEN 1 ELSE 0 END) as no_count"),
            DB::raw("SUM(CASE WHEN a.answer = 'unknown' THEN 1 ELSE 0 END) as unknown_count"),
            DB::raw('COUNT(*) as total_count')
        )
            ->groupBy('q.id', 'q.question_number', 'q.question_text', 'q.category')
            ->orderBy('q.question_number')
            ->get();

        // スタッフ回答数
        $staffRespondentsQuery = DB::table('facility_staff_evaluations')
            ->where('period_id', $period->id)
            ->where('is_submitted', true);
        if ($classroomId) {
            $staffRespondentsQuery->join('users as u', 'facility_staff_evaluations.staff_id', '=', 'u.id')
                                  ->where('u.classroom_id', $classroomId);
        }
        $staffRespondents = $staffRespondentsQuery->count();

        // スタッフコメント（質問ごと）
        $staffCommentsQuery = DB::table('facility_staff_evaluation_answers as a')
            ->join('facility_staff_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('a.comment')->where('a.comment', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('a.improvement_plan')->where('a.improvement_plan', '!=', '');
                });
            });

        if ($classroomId) {
            $staffCommentsQuery->join('users as u', 'e.staff_id', '=', 'u.id')
                               ->where('u.classroom_id', $classroomId);
        }

        $staffComments = $staffCommentsQuery
            ->select('a.question_id', 'a.comment', 'a.improvement_plan')
            ->orderBy('a.question_id')
            ->get();

        $staffCommentsByQuestion = [];
        foreach ($staffComments as $c) {
            $staffCommentsByQuestion[$c->question_id][] = [
                'comment'          => $c->comment,
                'improvement_plan' => $c->improvement_plan,
            ];
        }

        // 集計結果のfacility_commentを取得
        $savedSummaries = DB::table('facility_evaluation_summaries')
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('question_id');

        foreach ($selfSummary as $item) {
            $saved = $savedSummaries->get($item->question_id);
            $item->facility_comment = $saved->facility_comment ?? null;
        }

        // 別紙3: 自己評価総括表データ（strength/weakness）
        $selfSummaryItems = DB::table('facility_self_evaluation_summary')
            ->where('period_id', $period->id)
            ->orderBy('item_type')
            ->orderBy('item_number')
            ->get();

        // カテゴリ別の集計データ（別紙3用 - self_summary_categoryテーブルから）
        $categorySummaries = DB::table('facility_self_evaluation_summary')
            ->where('period_id', $period->id)
            ->whereNotNull('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'period'                   => $period,
                'summary'                  => $selfSummary,
                'staff_respondents'        => $staffRespondents,
                'staff_comments_by_question' => $staffCommentsByQuestion,
                'self_summary_items'       => $selfSummaryItems,
                'category_summaries'       => $categorySummaries,
            ],
        ]);
    }

    /**
     * 別紙3: 自己評価総括表（strength/weakness）を保存
     */
    public function saveSelfSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|integer',
            'items'     => 'required|array',
            'items.*.item_type'        => 'required|in:strength,weakness',
            'items.*.item_number'      => 'required|integer|min:1|max:3',
            'items.*.description'      => 'nullable|string',
            'items.*.current_efforts'  => 'nullable|string',
            'items.*.improvement_plan' => 'nullable|string',
        ]);

        try {
            foreach ($validated['items'] as $item) {
                DB::table('facility_self_evaluation_summary')->updateOrInsert(
                    [
                        'period_id'   => $validated['period_id'],
                        'item_type'   => $item['item_type'],
                        'item_number' => $item['item_number'],
                    ],
                    [
                        'description'      => $item['description'] ?? '',
                        'current_efforts'  => $item['current_efforts'] ?? '',
                        'improvement_plan' => $item['improvement_plan'] ?? '',
                        'updated_at'       => now(),
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => '自己評価総括表を保存しました。',
            ]);
        } catch (\Exception $e) {
            Log::error('Save self summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '保存中にエラーが発生しました。',
            ], 500);
        }
    }
}
