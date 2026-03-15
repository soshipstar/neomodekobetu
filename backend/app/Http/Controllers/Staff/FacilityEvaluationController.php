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
     * 評価期間の一覧を取得
     */
    public function periods(Request $request): JsonResponse
    {
        $periods = DB::table('facility_evaluation_periods')
            ->orderByDesc('fiscal_year')
            ->orderByDesc('created_at')
            ->get();

        // 各期間の回答状況を集計
        foreach ($periods as $period) {
            $period->guardian_submitted = DB::table('facility_guardian_evaluations')
                ->where('period_id', $period->id)
                ->where('is_submitted', true)
                ->count();
            $period->guardian_total = DB::table('facility_guardian_evaluations')
                ->where('period_id', $period->id)
                ->count();
            $period->staff_submitted = DB::table('facility_staff_evaluations')
                ->where('period_id', $period->id)
                ->where('is_submitted', true)
                ->count();
            $period->staff_total = DB::table('facility_staff_evaluations')
                ->where('period_id', $period->id)
                ->count();
        }

        return response()->json([
            'success' => true,
            'data'    => $periods,
        ]);
    }

    /**
     * 評価期間を新規作成
     */
    public function createPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year'       => 'required|integer|min:2020|max:2099',
            'title'             => 'required|string|max:255',
            'guardian_deadline'  => 'nullable|date',
            'staff_deadline'    => 'nullable|date',
        ]);

        $id = DB::table('facility_evaluation_periods')->insertGetId(array_merge($validated, [
            'status'     => 'draft',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

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
     */
    public function responseStatus(Request $request, int $periodId): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // 保護者の回答状況
        $guardianQuery = DB::table('facility_guardian_evaluations as e')
            ->join('users as u', 'e.guardian_id', '=', 'u.id')
            ->where('e.period_id', $periodId);

        if ($classroomId) {
            $guardianQuery->where('u.classroom_id', $classroomId);
        }

        $guardianResponses = $guardianQuery->select(
            'e.id',
            'u.full_name as guardian_name',
            'e.is_submitted',
            'e.submitted_at'
        )->orderBy('u.full_name')->get();

        // スタッフの回答状況
        $staffQuery = DB::table('facility_staff_evaluations as e')
            ->join('users as u', 'e.staff_id', '=', 'u.id')
            ->where('e.period_id', $periodId);

        if ($classroomId) {
            $staffQuery->where('u.classroom_id', $classroomId);
        }

        $staffResponses = $staffQuery->select(
            'e.id',
            'u.full_name as staff_name',
            'e.is_submitted',
            'e.submitted_at'
        )->orderBy('u.full_name')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'guardian_responses' => $guardianResponses,
                'staff_responses'   => $staffResponses,
            ],
        ]);
    }

    /**
     * 施設評価の集計サマリーを取得
     */
    public function summary(Request $request): JsonResponse
    {
        $periodId = $request->input('period_id');

        // 最新の評価期間を取得
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
                'data'    => [
                    'period'  => null,
                    'summary' => [],
                    'message' => '評価期間が見つかりません。',
                ],
            ]);
        }

        // 保護者評価の集計
        $guardianSummary = DB::table('facility_guardian_evaluation_answers as a')
            ->join('facility_guardian_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true)
            ->select(
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
        $totalRespondents = DB::table('facility_guardian_evaluations')
            ->where('period_id', $period->id)
            ->where('is_submitted', true)
            ->count();

        // コメント一覧
        $comments = DB::table('facility_guardian_evaluation_answers as a')
            ->join('facility_guardian_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true)
            ->whereNotNull('a.comment')
            ->where('a.comment', '!=', '')
            ->select('q.question_number', 'q.question_text', 'a.comment')
            ->orderBy('q.question_number')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'period'            => $period,
                'total_respondents' => $totalRespondents,
                'summary'           => $guardianSummary,
                'comments'          => $comments,
            ],
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
            // 最新期間を取得
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
            'answers.*.answer'            => 'required|in:yes,neutral,no,unknown',
            'answers.*.comment'           => 'nullable|string',
            'answers.*.improvement_plan'  => 'nullable|string',
            'submit'    => 'nullable|boolean',
        ]);

        $periodId = $validated['period_id'];
        $isSubmit = $validated['submit'] ?? false;

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
            'message' => $isSubmit ? '自己評価を提出しました。' : '途中保存しました。',
        ]);
    }

    /**
     * 事業所自己評価のサマリーを取得
     */
    public function selfSummary(Request $request): JsonResponse
    {
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
                'data'    => ['period' => null, 'summary' => []],
            ]);
        }

        // スタッフ自己評価の集計
        $selfSummary = DB::table('facility_staff_evaluation_answers as a')
            ->join('facility_staff_evaluations as e', 'a.evaluation_id', '=', 'e.id')
            ->join('facility_evaluation_questions as q', 'a.question_id', '=', 'q.id')
            ->where('e.period_id', $period->id)
            ->where('e.is_submitted', true)
            ->select(
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

        return response()->json([
            'success' => true,
            'data'    => [
                'period'  => $period,
                'summary' => $selfSummary,
            ],
        ]);
    }
}
