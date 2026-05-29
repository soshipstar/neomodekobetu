<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DailyRecord;
use App\Models\FreeSchoolReport;
use App\Models\FreeSchoolUser;
use App\Models\Student;
use App\Services\FreeSchoolReportAiService;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * フリースクール用報告書 (学校提出用) の CRUD + AI 生成 + PDF。
 *
 * 設計:
 *  - 児童を「フリースクール利用者」として登録すると、その classroom_id 内で
 *    日次活動レコードがあった日 (= 出席日) から自由に選んで報告書を作れる。
 *  - 1 つの報告書は 4 セクション (活動概要・支援と配慮・本人の様子・評価)。
 *  - AI 生成 → 編集 → DB 保存 → PDF (単一: A4 2 ページ / 一括: 表紙 + 各日分)。
 */
class FreeSchoolReportController extends Controller
{
    /**
     * アクセス可能な教室の id (現在のアクティブ教室のみ。Staff の慣例どおり)。
     * @return array<int>
     */
    private function accessibleClassroomIds(Request $request): array
    {
        return $request->user()->accessibleClassroomIds();
    }

    // =========================================================================
    // 1) フリースクール利用者 (free_school_users) CRUD
    // =========================================================================

    public function indexUsers(Request $request): JsonResponse
    {
        $classroomIds = $this->accessibleClassroomIds($request);
        $users = FreeSchoolUser::with([
                'student:id,student_name,grade_level,classroom_id,status',
                'registeredBy:id,full_name',
            ])
            ->whereIn('classroom_id', $classroomIds)
            ->orderByDesc('is_active')
            ->orderBy('student_id')
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'student_id'    => 'required|exists:students,id',
            'registered_at' => 'nullable|date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        // この staff の現アクティブ教室に在籍する児童であることを担保
        $student = Student::find($validated['student_id']);
        if (! in_array((int) $student->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'この児童にアクセスする権限がありません。'], 403);
        }

        $exists = FreeSchoolUser::where('classroom_id', $student->classroom_id)
            ->where('student_id', $student->id)
            ->first();
        if ($exists) {
            // 旧 inactive を再有効化
            $exists->update([
                'is_active'    => true,
                'registered_at' => $validated['registered_at'] ?? $exists->registered_at,
                'notes'        => $validated['notes'] ?? $exists->notes,
            ]);
            return response()->json([
                'success' => true,
                'data'    => $exists->fresh(['student:id,student_name,grade_level']),
                'message' => 'フリースクール利用者として再登録しました。',
            ]);
        }

        $fsu = FreeSchoolUser::create([
            'classroom_id'  => $student->classroom_id,
            'student_id'    => $student->id,
            'registered_at' => $validated['registered_at'] ?? now()->toDateString(),
            'notes'         => $validated['notes'] ?? null,
            'registered_by' => $user->id,
            'is_active'     => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $fsu->load('student:id,student_name,grade_level'),
            'message' => 'フリースクール利用者として登録しました。',
        ], 201);
    }

    public function destroyUser(Request $request, FreeSchoolUser $freeSchoolUser): JsonResponse
    {
        if (! in_array((int) $freeSchoolUser->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }
        // 既存の報告書を残すため論理削除 (is_active=false)
        $freeSchoolUser->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '利用者登録を解除しました (報告書は残ります)。']);
    }

    /**
     * このフリースクール利用者の「出席日」(= daily_records + student_records で
     * 児童が記録された日) を一覧化。FE で「どの日について報告書を作るか」
     * 選ばせるための材料。
     */
    public function attendance(Request $request, FreeSchoolUser $freeSchoolUser): JsonResponse
    {
        if (! in_array((int) $freeSchoolUser->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $q = DailyRecord::query()
            ->whereHas('studentRecords', function ($q) use ($freeSchoolUser) {
                $q->where('student_id', $freeSchoolUser->student_id);
            })
            ->where('classroom_id', $freeSchoolUser->classroom_id);

        if (!empty($validated['from'])) $q->whereDate('record_date', '>=', $validated['from']);
        if (!empty($validated['to']))   $q->whereDate('record_date', '<=', $validated['to']);

        $records = $q->select(['id', 'record_date', 'activity_name', 'staff_id'])
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // 既存の報告書 (この user_id × record_date) を引いておく
        $existing = FreeSchoolReport::where('free_school_user_id', $freeSchoolUser->id)
            ->whereIn('report_date', $records->pluck('record_date')->map(fn ($d) => $d?->format('Y-m-d'))->filter()->unique())
            ->get(['id', 'report_date', 'status'])
            ->keyBy(fn ($r) => $r->report_date?->format('Y-m-d'));

        $data = $records->map(function ($r) use ($existing) {
            $key = $r->record_date?->format('Y-m-d');
            $rep = $existing->get($key);
            return [
                'daily_record_id' => $r->id,
                'record_date'     => $key,
                'activity_name'   => $r->activity_name,
                'staff_id'        => $r->staff_id,
                'has_report'      => $rep !== null,
                'report_id'       => $rep?->id,
                'report_status'   => $rep?->status,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // =========================================================================
    // 2) 報告書 CRUD + AI 生成
    // =========================================================================

    public function indexReports(Request $request): JsonResponse
    {
        $classroomIds = $this->accessibleClassroomIds($request);
        $validated = $request->validate([
            'free_school_user_id' => 'nullable|integer|exists:free_school_users,id',
            'student_id'          => 'nullable|integer|exists:students,id',
            'from'                => 'nullable|date',
            'to'                  => 'nullable|date',
        ]);

        $q = FreeSchoolReport::with(['student:id,student_name,grade_level', 'classroom:id,classroom_name'])
            ->whereIn('classroom_id', $classroomIds);

        if (!empty($validated['free_school_user_id'])) $q->where('free_school_user_id', $validated['free_school_user_id']);
        if (!empty($validated['student_id']))          $q->where('student_id', $validated['student_id']);
        if (!empty($validated['from']))                $q->whereDate('report_date', '>=', $validated['from']);
        if (!empty($validated['to']))                  $q->whereDate('report_date', '<=', $validated['to']);

        $reports = $q->orderByDesc('report_date')->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function showReport(Request $request, FreeSchoolReport $freeSchoolReport): JsonResponse
    {
        if (! in_array((int) $freeSchoolReport->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }
        $freeSchoolReport->load(['student:id,student_name,grade_level', 'classroom:id,classroom_name']);
        return response()->json(['success' => true, 'data' => $freeSchoolReport]);
    }

    /**
     * 報告書を AI 生成 (新規作成 or 既存上書き)。
     * 入力: free_school_user_id + daily_record_id
     */
    public function generateReport(Request $request, FreeSchoolReportAiService $ai): JsonResponse
    {
        $validated = $request->validate([
            'free_school_user_id' => 'required|integer|exists:free_school_users,id',
            'daily_record_id'     => 'required|integer|exists:daily_records,id',
            'overwrite'           => 'nullable|boolean',
        ]);

        $fsu = FreeSchoolUser::findOrFail($validated['free_school_user_id']);
        if (! in_array((int) $fsu->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $record = DailyRecord::findOrFail($validated['daily_record_id']);
        if ((int) $record->classroom_id !== (int) $fsu->classroom_id) {
            return response()->json(['success' => false, 'message' => 'この活動レコードは別の事業所です。'], 422);
        }

        $student = Student::findOrFail($fsu->student_id);
        $dateStr = $record->record_date?->format('Y-m-d');

        // 既存報告書を確認
        $existing = FreeSchoolReport::where('free_school_user_id', $fsu->id)
            ->whereDate('report_date', $dateStr)
            ->first();

        if ($existing && empty($validated['overwrite'])) {
            return response()->json([
                'success' => false,
                'message' => 'この日の報告書は既に存在します。overwrite=true で上書きしてください。',
                'data'    => $existing,
            ], 409);
        }

        try {
            $generated = $ai->generate($student, $record);
        } catch (\Throwable $e) {
            Log::error('FreeSchoolReport AI generate failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'AI 生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }

        $title = "{$student->student_name} さん フリースクール活動報告書";
        $payload = [
            'classroom_id'          => $fsu->classroom_id,
            'free_school_user_id'   => $fsu->id,
            'student_id'            => $student->id,
            'daily_record_id'       => $record->id,
            'report_date'           => $dateStr,
            'title'                 => $title,
            'activity_summary'      => $generated['activity_summary'],
            'support_consideration' => $generated['support_consideration'],
            'child_observation'     => $generated['child_observation'],
            'evaluation_and_next'   => $generated['evaluation_and_next'],
            'generated_at'          => now(),
            'generated_by_ai'       => true,
            'status'                => 'draft',
        ];

        $report = $existing
            ? tap($existing)->update($payload)
            : FreeSchoolReport::create($payload);

        $report->refresh()->load(['student:id,student_name,grade_level', 'classroom:id,classroom_name']);
        return response()->json([
            'success' => true,
            'data'    => $report,
            'message' => $existing ? '既存の報告書を上書きしました。' : '報告書を AI で生成しました。',
        ], $existing ? 200 : 201);
    }

    public function updateReport(Request $request, FreeSchoolReport $freeSchoolReport): JsonResponse
    {
        if (! in_array((int) $freeSchoolReport->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }
        $validated = $request->validate([
            'title'                 => 'nullable|string|max:255',
            'activity_summary'      => 'nullable|string',
            'support_consideration' => 'nullable|string',
            'child_observation'     => 'nullable|string',
            'evaluation_and_next'   => 'nullable|string',
            'status'                => 'nullable|string|in:draft,finalized',
        ]);

        $freeSchoolReport->update(array_merge($validated, [
            'edited_at' => now(),
            'edited_by' => $request->user()->id,
        ]));
        return response()->json([
            'success' => true,
            'data'    => $freeSchoolReport->fresh(),
            'message' => '報告書を更新しました。',
        ]);
    }

    public function destroyReport(Request $request, FreeSchoolReport $freeSchoolReport): JsonResponse
    {
        if (! in_array((int) $freeSchoolReport->classroom_id, $this->accessibleClassroomIds($request), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }
        $freeSchoolReport->delete();
        return response()->json(['success' => true, 'message' => '報告書を削除しました。']);
    }

    // =========================================================================
    // 3) PDF (単一 / 一括)
    // =========================================================================

    public function pdfReport(Request $request, FreeSchoolReport $freeSchoolReport, PuppeteerPdfService $pdf)
    {
        if (! in_array((int) $freeSchoolReport->classroom_id, $this->accessibleClassroomIds($request), true)) {
            abort(403);
        }
        $freeSchoolReport->load(['student:id,student_name,grade_level', 'classroom:id,classroom_name']);

        return $pdf->download(
            view: 'pdf.free-school-report',
            data: [
                'report'    => $freeSchoolReport,
                'student'   => $freeSchoolReport->student,
                'classroom' => $freeSchoolReport->classroom,
            ],
            filename: 'free-school-report-' . $freeSchoolReport->id . '.pdf',
        );
    }

    /**
     * 期間指定での一括 PDF (児童 1 名のみ対象、表紙 + 各日報告書)。
     */
    public function batchPdf(Request $request, PuppeteerPdfService $pdf)
    {
        $validated = $request->validate([
            'free_school_user_id' => 'required|integer|exists:free_school_users,id',
            'from'                => 'required|date',
            'to'                  => 'required|date|after_or_equal:from',
        ]);

        $fsu = FreeSchoolUser::with(['student:id,student_name,grade_level', 'classroom:id,classroom_name'])
            ->findOrFail($validated['free_school_user_id']);

        if (! in_array((int) $fsu->classroom_id, $this->accessibleClassroomIds($request), true)) {
            abort(403);
        }

        $reports = FreeSchoolReport::where('free_school_user_id', $fsu->id)
            ->whereDate('report_date', '>=', $validated['from'])
            ->whereDate('report_date', '<=', $validated['to'])
            ->orderBy('report_date')
            ->get();

        if ($reports->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '指定期間に報告書が 1 件もありません。',
            ], 422);
        }

        return $pdf->download(
            view: 'pdf.free-school-report-batch',
            data: [
                'student'   => $fsu->student,
                'classroom' => $fsu->classroom,
                'from'      => $validated['from'],
                'to'        => $validated['to'],
                'reports'   => $reports,
            ],
            filename: 'free-school-reports-' . $fsu->student->student_name . '-' . $validated['from'] . '_' . $validated['to'] . '.pdf',
        );
    }
}
