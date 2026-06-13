<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI学習基盤 同意UI: 児童(student)単位の学習同意(model_learning)。
 *
 * 学習同意は保護者・本人のものだが、紙/口頭で得た同意をスタッフがきづり側で代理記録する
 * (granted_by_role=staff_proxy, acquisition_method, note を残す)。学習の可否は施設の集計同意との
 * AND(canUseForLearning)で決まるため、施設側の状態も併せて返す。
 *
 * 分類: api
 */
class AiConsentController extends Controller
{
    public function __construct(private ConsentService $consent) {}

    /** GET /api/staff/students/{student}/ai-consent */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request->user(), $student);

        return response()->json(['success' => true, 'data' => $this->payload($student)]);
    }

    /** PUT /api/staff/students/{student}/ai-consent {granted, acquisition_method?, note?} */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request->user(), $student);

        $validated = $request->validate([
            'granted' => 'required|boolean',
            'acquisition_method' => 'nullable|string|in:paper,verbal,online,other',
            'note' => 'nullable|string|max:1000',
        ]);

        $this->consent->recordStudentConsent(
            $student,
            (bool) $validated['granted'],
            $request->user()->id,
            role: 'staff_proxy',
            method: $validated['acquisition_method'] ?? 'paper',
            note: $validated['note'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $this->payload($student->fresh()),
            'message' => $validated['granted'] ? '学習同意を記録しました。' : '学習同意を撤回しました。',
        ]);
    }

    private function authorizeStudent($user, Student $student): void
    {
        if ($user->classroom_id && ! in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この児童へのアクセス権限がありません。');
        }
    }

    private function payload(Student $student): array
    {
        $student->loadMissing('classroom.company');
        $company = $student->classroom?->company;

        return [
            'student_id' => $student->id,
            'ai_consent_learning' => (bool) $student->ai_consent_learning,
            'ai_consent_learning_at' => $student->ai_consent_learning_at,
            // 施設の集計同意(AND条件の片側)。OFFだと児童が同意しても学習に使われない。
            'company_aggregate' => (bool) ($company?->ai_consent_aggregate),
            'can_use_for_learning' => $this->consent->canUseForLearning($student),
        ];
    }
}
