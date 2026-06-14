<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AiEditReason;
use App\Models\AiEditReasonCategory;
use App\Models\AiRevisionEvent;
use App\Models\Student;
use App\Services\EditReasonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI学習基盤 §11: 修正理由の記録(職員)。1クリックchips + 自由記述。
 *
 * 分類: api
 */
class EditReasonController extends Controller
{
    public function __construct(private EditReasonService $service) {}

    /** GET /api/staff/edit-reason-categories : 選択肢(全社共通+自社、使用頻度順) */
    public function categories(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $cats = AiEditReasonCategory::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->orderByDesc('usage_count')->orderBy('sort_order')
            ->get(['id', 'code', 'label_ja', 'description', 'company_id']);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    /** GET /api/staff/students/{student}/edit-reasons : タグ付け対象の最近の修正(当該児童) */
    public function revisions(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request->user(), $student);

        $revs = AiRevisionEvent::where('student_id', $student->id)
            ->with('reasons')
            ->orderByDesc('id')->limit(30)->get();

        $data = $revs->map(fn (AiRevisionEvent $r) => [
            'id' => $r->id,
            'document_type' => $r->document_type,
            'section_key' => $r->section_key,
            'edit_kind' => $r->edit_kind,
            'change_ratio' => $r->change_ratio,
            'created_at' => $r->created_at,
            'after_preview' => mb_substr((string) $r->after_text, 0, 60),
            'category_ids' => $r->reasons->where('reason_source', 'human_manual')->pluck('category_id')->filter()->values(),
            'free_text' => optional($r->reasons->where('reason_source', 'human_manual')->firstWhere('free_text', '!=', null))->free_text,
            'tagged' => $r->reasons->where('reason_source', 'human_manual')->isNotEmpty(),
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /api/staff/edit-reasons/{revision}/attach {category_ids[], free_text?} */
    public function attach(Request $request, AiRevisionEvent $revision): JsonResponse
    {
        $revision->loadMissing('student');
        if (! $revision->student) {
            return response()->json(['success' => false, 'message' => '対象の児童が見つかりません。'], 404);
        }
        $this->authorizeStudent($request->user(), $revision->student);

        $validated = $request->validate([
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:ai_edit_reason_categories,id',
            'free_text' => 'nullable|string|max:1000',
        ]);

        $this->service->attach(
            $revision,
            $validated['category_ids'] ?? [],
            $validated['free_text'] ?? null,
            $request->user()->id,
        );

        return response()->json(['success' => true, 'message' => '修正理由を記録しました。']);
    }

    private function authorizeStudent($user, Student $student): void
    {
        if ($user->classroom_id && ! in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この児童へのアクセス権限がありません。');
        }
    }
}
