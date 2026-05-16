<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\HiyariHattoRecord;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HiyariHattoController extends Controller
{
    /**
     * ヒヤリハット一覧
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleClassroomIds();

        $query = HiyariHattoRecord::with(['student:id,student_name', 'reporter:id,full_name'])
            ->whereIn('classroom_id', $accessibleIds);

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->to);
        }

        $records = $query->orderByDesc('occurred_at')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $records,
            'severities' => HiyariHattoRecord::SEVERITIES,
            'categories' => HiyariHattoRecord::CATEGORIES,
        ]);
    }

    /**
     * 新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate($this->rules(true));

        // 権限: アクセス可能な classroom のみ
        if (!in_array((int) $validated['classroom_id'], $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated['reporter_id'] = $user->id;
        $record = HiyariHattoRecord::create($validated);

        return response()->json([
            'success' => true,
            'data' => $record->load(['student:id,student_name', 'reporter:id,full_name']),
            'message' => 'ヒヤリハットを記録しました。',
        ], 201);
    }

    /**
     * 詳細
     */
    public function show(Request $request, HiyariHattoRecord $hiyariHatto): JsonResponse
    {
        $this->authorizeAccess($request, $hiyariHatto);
        $hiyariHatto->load(['student:id,student_name,grade_level', 'reporter:id,full_name', 'confirmedBy:id,full_name', 'classroom:id,classroom_name']);

        return response()->json([
            'success' => true,
            'data' => $hiyariHatto,
        ]);
    }

    /**
     * 更新
     */
    public function update(Request $request, HiyariHattoRecord $hiyariHatto): JsonResponse
    {
        $this->authorizeAccess($request, $hiyariHatto);

        $validated = $request->validate($this->rules(false));
        $hiyariHatto->update($validated);

        return response()->json([
            'success' => true,
            'data' => $hiyariHatto->fresh(['student:id,student_name', 'reporter:id,full_name']),
            'message' => 'ヒヤリハットを更新しました。',
        ]);
    }

    /**
     * 削除
     */
    public function destroy(Request $request, HiyariHattoRecord $hiyariHatto): JsonResponse
    {
        $this->authorizeAccess($request, $hiyariHatto);
        $hiyariHatto->delete();

        return response()->json([
            'success' => true,
            'message' => 'ヒヤリハットを削除しました。',
        ]);
    }

    /**
     * PDF 生成 (puppeteer で HTML を PDF 化)
     */
    public function pdf(Request $request, HiyariHattoRecord $hiyariHatto): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAccess($request, $hiyariHatto);
        $hiyariHatto->load(['student:id,student_name,grade_level', 'reporter:id,full_name', 'confirmedBy:id,full_name', 'classroom:id,classroom_name']);

        // 学年コード (elementary_2, junior_high_1 等) を日本語ラベルに変換するマスタ。
        // students.grade_level の値はすべて英語コードで保存されているため、PDF 表示時に
        // 必ずこのマップを通す必要がある (バグ報告 #58)。
        $gradeLabels = [
            'preschool'        => '未就学',
            'elementary'       => '小学生',
            'elementary_1'     => '小1',
            'elementary_2'     => '小2',
            'elementary_3'     => '小3',
            'elementary_4'     => '小4',
            'elementary_5'     => '小5',
            'elementary_6'     => '小6',
            'junior_high'      => '中学生',
            'junior_high_1'    => '中1',
            'junior_high_2'    => '中2',
            'junior_high_3'    => '中3',
            'high_school'      => '高校生',
            'high_school_1'    => '高1',
            'high_school_2'    => '高2',
            'high_school_3'    => '高3',
        ];

        $filename = 'hiyari-hatto-' . $hiyariHatto->id . '-' . $hiyariHatto->occurred_at->format('Ymd') . '.pdf';
        return PuppeteerPdfService::download('pdf.hiyari-hatto', [
            'record'      => $hiyariHatto,
            'severities'  => HiyariHattoRecord::SEVERITIES,
            'categories'  => HiyariHattoRecord::CATEGORIES,
            'gradeLabels' => $gradeLabels,
        ], $filename);
    }

    /**
     * 権限チェック: アクセス可能教室のレコードのみ操作可
     */
    private function authorizeAccess(Request $request, HiyariHattoRecord $record): void
    {
        $user = $request->user();
        if (!in_array($record->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'アクセス権限がありません。');
        }
    }

    /**
     * バリデーションルール
     */
    private function rules(bool $isCreate): array
    {
        $base = [
            'student_id' => 'nullable|exists:students,id',
            'occurred_at' => ($isCreate ? 'required' : 'sometimes') . '|date',
            'location' => 'nullable|string|max:255',
            'activity_before' => 'nullable|string',
            'student_condition' => 'nullable|string',
            'situation' => ($isCreate ? 'required' : 'sometimes') . '|string',
            'severity' => [$isCreate ? 'required' : 'sometimes', Rule::in(array_keys(HiyariHattoRecord::SEVERITIES))],
            'category' => ['nullable', Rule::in(array_keys(HiyariHattoRecord::CATEGORIES))],
            'cause_environmental' => 'nullable|string',
            'cause_human' => 'nullable|string',
            'cause_other' => 'nullable|string',
            'immediate_response' => 'nullable|string',
            'guardian_notified' => 'nullable|boolean',
            'guardian_notified_at' => 'nullable|date',
            'guardian_notification_content' => 'nullable|string',
            'medical_treatment' => 'nullable|boolean',
            'medical_detail' => 'nullable|string',
            'injury_description' => 'nullable|string',
            'prevention_measures' => 'nullable|string',
            'environment_improvements' => 'nullable|string',
            'staff_sharing_notes' => 'nullable|string',
            'confirmed_by_id' => 'nullable|exists:users,id',
            'source_daily_record_id' => 'nullable|exists:daily_records,id',
            'source_type' => 'nullable|string|in:manual,integrated_note_ai',
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'confirmed'])],
        ];

        if ($isCreate) {
            $base['classroom_id'] = 'required|exists:classrooms,id';
        }

        return $base;
    }
}
