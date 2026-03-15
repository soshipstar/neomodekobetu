<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\KakehashiGuardian;
use App\Models\KakehashiPeriod;
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
            ->with(['student:id,student_name', 'guardianEntry'])
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

        $period->load(['student:id,student_name', 'staffEntry', 'guardianEntry']);

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
            'home_health_life'            => 'nullable|string',
            'home_motor_sensory'          => 'nullable|string',
            'home_cognitive_behavior'     => 'nullable|string',
            'home_language_communication' => 'nullable|string',
            'home_social_relations'       => 'nullable|string',
            'home_overall_comment'        => 'nullable|string',
            'is_submitted'                => 'boolean',
        ]);

        $isSubmitted = $validated['is_submitted'] ?? false;
        unset($validated['is_submitted']);

        $entry = KakehashiGuardian::updateOrCreate(
            [
                'period_id'   => $period->id,
                'guardian_id' => $user->id,
            ],
            array_merge($validated, [
                'student_id'   => $period->student_id,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ])
        );

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
            'period_id' => 'required|exists:kakehashi_periods,id',
        ]);

        $period = KakehashiPeriod::findOrFail($validated['period_id']);

        if (! in_array($period->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $period->update([
            'staff_guardian_confirmed'    => true,
            'staff_guardian_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $period->fresh(),
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
