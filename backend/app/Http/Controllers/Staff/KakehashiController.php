<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * かけはし PDF データを返す
     */
    public function pdf(Request $request, KakehashiPeriod $period): JsonResponse
    {
        $period->load(['student.classroom', 'staffEntries', 'guardianEntries']);

        if ($period->student) {
            $this->authorizeClassroom($request->user(), $period->student);
        }

        return response()->json([
            'success' => true,
            'data'    => $period,
        ]);
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
