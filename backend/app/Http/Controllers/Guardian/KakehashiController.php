<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\KakehashiGuardian;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KakehashiController extends Controller
{
    /**
     * 保護者の子どもに関するかけはし期間一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        $periods = KakehashiPeriod::whereIn('student_id', $studentIds)
            ->with(['student:id,student_name', 'guardianEntries'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $periods,
        ]);
    }

    /**
     * かけはしの内容を表示（スタッフ記入分 + 保護者記入分）
     */
    public function show(Request $request, KakehashiPeriod $period): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();

        if (! in_array($period->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $period->load(['student:id,student_name', 'staffEntries', 'guardianEntries']);

        return response()->json([
            'success' => true,
            'data'    => $period,
        ]);
    }

    /**
     * 保護者のかけはし記入を保存
     */
    public function store(Request $request, KakehashiPeriod $period): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();

        if (! in_array($period->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'student_wish'               => 'nullable|string',
            'home_challenges'            => 'nullable|string',
            'short_term_goal'            => 'nullable|string',
            'long_term_goal'             => 'nullable|string',
            'domain_health_life'         => 'nullable|string',
            'domain_motor_sensory'       => 'nullable|string',
            'domain_cognitive_behavior'  => 'nullable|string',
            'domain_language_communication' => 'nullable|string',
            'domain_social_relations'    => 'nullable|string',
            'other_challenges'           => 'nullable|string',
            'action'                     => 'nullable|string|in:save,submit',
        ]);

        $action = $validated['action'] ?? 'save';
        unset($validated['action']);

        // Check for existing entry
        $existing = KakehashiGuardian::where('period_id', $period->id)
            ->where('student_id', $period->student_id)
            ->first();

        // Block editing if hidden
        if ($existing && $existing->is_hidden) {
            return response()->json([
                'success' => false,
                'message' => 'この期間は入力できません。',
            ], 422);
        }

        // Block editing if already submitted (guardians cannot edit after submission)
        if ($existing && $existing->is_submitted) {
            return response()->json([
                'success' => false,
                'message' => '既に提出済みのため、変更できません。',
            ], 422);
        }

        $isSubmitted = ($action === 'submit');

        if ($existing) {
            $existing->update(array_merge($validated, [
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ]));
            $entry = $existing;
        } else {
            $entry = KakehashiGuardian::create(array_merge($validated, [
                'period_id'    => $period->id,
                'student_id'   => $period->student_id,
                'guardian_id'  => $user->id,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
                'is_hidden'    => false,
            ]));
        }

        $message = $isSubmitted ? 'かけはしを提出しました。' : '下書きを保存しました。';

        return response()->json([
            'success' => true,
            'data'    => $entry,
            'message' => $message,
        ]);
    }

    /**
     * かけはし履歴一覧 (legacy kakehashi_history.php と同等)
     * 提出済みのスタッフ・保護者かけはしのみ返す
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $request->input('student_id');

        // student_id が指定されていない場合は最初の生徒を使う
        $students = $user->students()->active()->get(['id', 'student_name']);
        if (!$studentId && $students->isNotEmpty()) {
            $studentId = $students->first()->id;
        }

        $studentIds = $students->pluck('id')->toArray();

        if (!$studentId || !in_array((int) $studentId, $studentIds)) {
            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }

        $history = DB::table('kakehashi_periods as kp')
            ->leftJoin('kakehashi_staff as ks', function ($join) {
                $join->on('kp.id', '=', 'ks.period_id')
                     ->on('ks.student_id', '=', 'kp.student_id');
            })
            ->leftJoin('kakehashi_guardian as kg', function ($join) {
                $join->on('kp.id', '=', 'kg.period_id')
                     ->on('kg.student_id', '=', 'kp.student_id');
            })
            ->where('kp.student_id', $studentId)
            ->where('kp.is_active', true)
            ->where(function ($q) {
                $q->where('ks.is_submitted', true)
                  ->orWhere('kg.is_submitted', true);
            })
            ->select([
                'kp.id as period_id',
                'kp.period_name',
                'kp.start_date',
                'kp.end_date',
                'kp.submission_deadline',
                'ks.id as staff_kakehashi_id',
                'ks.is_submitted as staff_submitted',
                'ks.submitted_at as staff_submitted_at',
                'ks.guardian_confirmed as staff_guardian_confirmed',
                'ks.guardian_confirmed_at as staff_guardian_confirmed_at',
                'kg.id as guardian_kakehashi_id',
                'kg.is_submitted as guardian_submitted',
                'kg.submitted_at as guardian_submitted_at',
            ])
            ->orderByDesc('kp.submission_deadline')
            ->get()
            ->map(function ($item) {
                $item->staff_submitted = (bool) $item->staff_submitted;
                $item->guardian_submitted = (bool) $item->guardian_submitted;
                $item->staff_guardian_confirmed = (bool) $item->staff_guardian_confirmed;
                return $item;
            });

        return response()->json([
            'success' => true,
            'data'    => $history,
        ]);
    }

    /**
     * かけはし履歴詳細（期間IDで取得）
     * type=guardian or type=staff で保護者/スタッフの記入内容を返す
     */
    public function historyDetail(Request $request, KakehashiPeriod $period): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();
        $studentId = $request->input('student_id', $period->student_id);

        if (!in_array((int) $studentId, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $type = $request->input('type', 'guardian');

        $data = [
            'period_id' => $period->id,
            'period_name' => $period->period_name,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'submission_deadline' => $period->submission_deadline,
        ];

        if ($type === 'guardian') {
            $entry = KakehashiGuardian::where('period_id', $period->id)
                ->where('student_id', $studentId)
                ->first();

            if ($entry) {
                $data['guardian_student_wish'] = $entry->student_wish;
                $data['guardian_home_challenges'] = $entry->home_challenges;
                $data['guardian_short_term_goal'] = $entry->short_term_goal;
                $data['guardian_long_term_goal'] = $entry->long_term_goal;
                $data['guardian_domain_health_life'] = $entry->domain_health_life;
                $data['guardian_domain_motor_sensory'] = $entry->domain_motor_sensory;
                $data['guardian_domain_cognitive_behavior'] = $entry->domain_cognitive_behavior;
                $data['guardian_domain_language_communication'] = $entry->domain_language_communication;
                $data['guardian_domain_social_relations'] = $entry->domain_social_relations;
                $data['guardian_other_challenges'] = $entry->other_challenges;
            }
        } else {
            $entry = KakehashiStaff::where('period_id', $period->id)
                ->where('student_id', $studentId)
                ->first();

            if ($entry) {
                $data['staff_student_wish'] = $entry->student_wish;
                $data['staff_short_term_goal'] = $entry->short_term_goal;
                $data['staff_long_term_goal'] = $entry->long_term_goal;
                $data['staff_health_life'] = $entry->health_life;
                $data['staff_motor_sensory'] = $entry->motor_sensory;
                $data['staff_cognitive_behavior'] = $entry->cognitive_behavior;
                $data['staff_language_communication'] = $entry->language_communication;
                $data['staff_social_relations'] = $entry->social_relations;
                $data['staff_other_challenges'] = $entry->other_challenges;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * スタッフ記入分を保護者が確認する
     */
    public function confirmStaff(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'period_id'  => 'required|exists:kakehashi_periods,id',
        ]);

        if (! in_array((int) $validated['student_id'], $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // Update kakehashi_staff record (matching legacy behavior)
        $staffKakehashi = KakehashiStaff::where('student_id', $validated['student_id'])
            ->where('period_id', $validated['period_id'])
            ->where('is_submitted', true)
            ->first();

        if (! $staffKakehashi) {
            return response()->json(['success' => false, 'message' => 'スタッフかけはしが見つかりません。'], 404);
        }

        if ($staffKakehashi->guardian_confirmed) {
            return response()->json([
                'success' => true,
                'message' => '既に確認済みです。',
                'already_confirmed' => true,
            ]);
        }

        $staffKakehashi->update([
            'guardian_confirmed'    => true,
            'guardian_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $staffKakehashi->fresh(),
            'message' => '確認しました。',
        ]);
    }

    /**
     * かけはしエントリーを保存（entryエイリアス - storeと同じ処理）
     */
    public function entry(Request $request, KakehashiPeriod $period): JsonResponse
    {
        return $this->store($request, $period);
    }
}
