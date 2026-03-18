<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\KakehashiGuardian;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * かけはし履歴一覧（historyエイリアス - indexと同内容）
     */
    public function history(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * かけはし履歴詳細（期間IDで取得）
     */
    public function historyDetail(Request $request, KakehashiPeriod $period): JsonResponse
    {
        return $this->show($request, $period);
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
