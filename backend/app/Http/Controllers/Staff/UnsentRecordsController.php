<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\AbsenceResponseRecord;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnsentRecordsController extends Controller
{
    /**
     * 未送信日誌一覧を取得
     * 参加予定なのに日誌が作られていない生徒と、欠席者の一覧を返す
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)
            : $date->copy();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)
            : $date->copy();

        $results = [];

        // Iterate over each date in the range
        $current = $dateFrom->copy();
        while ($current->lte($dateTo)) {
            $dayOfWeek = strtolower($current->format('l'));
            $currentDate = $current->toDateString();

            // Get all active students scheduled for this day
            $scheduledStudents = Student::active()
                ->where('classroom_id', $classroomId)
                ->where("scheduled_{$dayOfWeek}", true)
                ->get();

            if ($scheduledStudents->isEmpty()) {
                $current->addDay();
                continue;
            }

            $studentIds = $scheduledStudents->pluck('id');

            // Get daily records for this date and classroom
            $dailyRecords = DailyRecord::where('classroom_id', $classroomId)
                ->where('record_date', $currentDate)
                ->pluck('id');

            // Get student IDs that have records
            $recordedStudentIds = collect();
            if ($dailyRecords->isNotEmpty()) {
                $recordedStudentIds = StudentRecord::whereIn('daily_record_id', $dailyRecords)
                    ->whereIn('student_id', $studentIds)
                    ->pluck('student_id')
                    ->unique();
            }

            // Get absences for this date
            $absences = AbsenceNotification::whereIn('student_id', $studentIds)
                ->where('absence_date', $currentDate)
                ->get()
                ->keyBy('student_id');

            // Get existing absence response records
            $absenceResponses = AbsenceResponseRecord::where('classroom_id', $classroomId)
                ->where('absence_date', $currentDate)
                ->get()
                ->keyBy('student_id');

            // Build missing records list
            foreach ($scheduledStudents as $student) {
                $hasRecord = $recordedStudentIds->contains($student->id);
                $absence = $absences->get($student->id);
                $absenceResponse = $absenceResponses->get($student->id);

                // Skip students who have records and are not absent
                if ($hasRecord && !$absence) {
                    continue;
                }

                $results[] = [
                    'date' => $currentDate,
                    'student_id' => $student->id,
                    'student_name' => $student->student_name,
                    'grade_level' => $student->grade_level,
                    'status' => $absence ? 'absent' : 'no_record',
                    'has_record' => $hasRecord,
                    'absence' => $absence ? [
                        'id' => $absence->id,
                        'reason' => $absence->reason,
                        'makeup_status' => $absence->makeup_status,
                    ] : null,
                    'absence_response' => $absenceResponse ? [
                        'id' => $absenceResponse->id,
                        'response_content' => $absenceResponse->response_content,
                        'contact_method' => $absenceResponse->contact_method,
                        'contact_content' => $absenceResponse->contact_content,
                        'is_sent' => $absenceResponse->is_sent,
                        'sent_at' => $absenceResponse->sent_at?->toISOString(),
                        'guardian_confirmed' => $absenceResponse->guardian_confirmed,
                        'staff_name' => $absenceResponse->staff?->full_name,
                    ] : null,
                ];
            }

            $current->addDay();
        }

        // Summary counts
        $noRecordCount = collect($results)->where('status', 'no_record')->count();
        $absentCount = collect($results)->where('status', 'absent')->count();
        $absenceResponsePending = collect($results)
            ->where('status', 'absent')
            ->filter(fn ($r) => $r['absence_response'] === null || !$r['absence_response']['is_sent'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => $results,
            'summary' => [
                'total' => count($results),
                'no_record' => $noRecordCount,
                'absent' => $absentCount,
                'absence_response_pending' => $absenceResponsePending,
            ],
        ]);
    }

    /**
     * 欠席時対応加算の記録を保存
     */
    public function storeAbsenceResponse(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'absence_date' => 'required|date',
            'absence_reason' => 'nullable|string|max:255',
            'response_content' => 'required|string',
            'contact_method' => 'nullable|string',
            'contact_content' => 'nullable|string',
        ]);

        $record = AbsenceResponseRecord::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'absence_date' => $validated['absence_date'],
            ],
            [
                'classroom_id' => $user->classroom_id,
                'absence_reason' => $validated['absence_reason'] ?? null,
                'response_content' => $validated['response_content'],
                'contact_method' => $validated['contact_method'] ?? null,
                'contact_content' => $validated['contact_content'] ?? null,
                'staff_id' => $user->id,
                'absence_notification_id' => AbsenceNotification::where('student_id', $validated['student_id'])
                    ->where('absence_date', $validated['absence_date'])
                    ->value('id'),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $record->load('student:id,student_name'),
            'message' => '欠席時対応記録を保存しました。',
        ]);
    }

    /**
     * 欠席時対応加算の記録を保護者に送信
     */
    public function sendAbsenceResponse(Request $request, AbsenceResponseRecord $record): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $record->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        if ($record->is_sent) {
            return response()->json(['success' => false, 'message' => 'この記録は既に送信済みです。'], 422);
        }

        $record->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);

        // Notify guardian
        try {
            $student = $record->student;
            if ($student && $student->guardian_id) {
                $guardian = User::find($student->guardian_id);
                if ($guardian && $guardian->is_active) {
                    $notificationService = app(NotificationService::class);
                    $dateStr = $record->absence_date->format('n月j日');
                    $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

                    $notificationService->notify(
                        $guardian,
                        'absence_response',
                        '欠席時対応の記録が届きました',
                        "{$student->student_name}さんの{$dateStr}の欠席時対応記録が送信されました。",
                        ['url' => "{$frontendUrl}/guardian/notes"]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Absence response notification error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $record->fresh(),
            'message' => '欠席時対応記録を保護者に送信しました。',
        ]);
    }

    /**
     * 欠席時対応加算の記録を一括送信
     */
    public function batchSendAbsenceResponse(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'record_ids' => 'required|array|min:1',
            'record_ids.*' => 'required|integer|exists:absence_response_records,id',
        ]);

        $sentCount = 0;

        DB::transaction(function () use ($validated, $user, &$sentCount) {
            $records = AbsenceResponseRecord::whereIn('id', $validated['record_ids'])
                ->where('is_sent', false)
                ->where('classroom_id', $user->classroom_id)
                ->get();

            foreach ($records as $record) {
                $record->update([
                    'is_sent' => true,
                    'sent_at' => now(),
                ]);
                $sentCount++;
            }

            // Send notifications
            try {
                $notificationService = app(NotificationService::class);
                $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

                $guardianIds = $records->map(fn ($r) => $r->student?->guardian_id)->filter()->unique();
                $guardians = User::whereIn('id', $guardianIds)->where('is_active', true)->get();

                foreach ($guardians as $guardian) {
                    $notificationService->notify(
                        $guardian,
                        'absence_response',
                        '欠席時対応の記録が届きました',
                        '欠席時対応記録が送信されました。',
                        ['url' => "{$frontendUrl}/guardian/notes"]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Batch absence response notification error: ' . $e->getMessage());
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$sentCount}件の欠席時対応記録を送信しました。",
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * 指定日の活動一覧を取得（日誌追加先の選択用）
     */
    public function activitiesForDate(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->validate(['date' => 'required|date'])['date'];

        $activities = DailyRecord::where('classroom_id', $user->classroom_id)
            ->where('record_date', $date)
            ->with('staff:id,full_name')
            ->withCount('studentRecords')
            ->get(['id', 'activity_name', 'record_date', 'staff_id', 'common_activity']);

        return response()->json(['success' => true, 'data' => $activities]);
    }

    /**
     * 生徒を既存の活動に追加、または新規活動を作成して追加
     */
    public function addStudentRecord(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'record_date' => 'required|date',
            'daily_record_id' => 'nullable|integer|exists:daily_records,id',
            'activity_name' => 'nullable|string|max:255',
            'common_activity' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $record = DB::transaction(function () use ($user, $validated) {
            $dailyRecordId = $validated['daily_record_id'] ?? null;

            if (!$dailyRecordId) {
                // 新規活動を作成
                $dailyRecord = DailyRecord::create([
                    'record_date' => $validated['record_date'],
                    'staff_id' => $user->id,
                    'classroom_id' => $user->classroom_id,
                    'activity_name' => $validated['activity_name'] ?? '日常活動',
                    'common_activity' => $validated['common_activity'] ?? '',
                ]);
                $dailyRecordId = $dailyRecord->id;
            }

            return StudentRecord::updateOrCreate(
                ['daily_record_id' => $dailyRecordId, 'student_id' => $validated['student_id']],
                ['notes' => $validated['notes'] ?? null]
            );
        });

        return response()->json([
            'success' => true,
            'data' => $record,
            'message' => '日誌を作成しました。',
        ]);
    }

    /**
     * 欠席扱いにして非表示にする（AbsenceNotificationを作成）
     */
    public function markAbsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'absence_date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        AbsenceNotification::firstOrCreate(
            ['student_id' => $validated['student_id'], 'absence_date' => $validated['absence_date']],
            ['reason' => $validated['reason'] ?? '欠席', 'makeup_status' => 'none']
        );

        return response()->json([
            'success' => true,
            'message' => '欠席扱いにしました。',
        ]);
    }

    /**
     * 欠席時対応加算一覧
     */
    public function absenceResponseList(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AbsenceResponseRecord::where('classroom_id', $user->classroom_id)
            ->with(['student:id,student_name,grade_level', 'staff:id,full_name']);

        if ($request->filled('date_from')) {
            $query->where('absence_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('absence_date', '<=', $request->date_to);
        }

        $records = $query->orderByDesc('absence_date')->paginate(50);

        return response()->json(['success' => true, 'data' => $records]);
    }

    /**
     * 欠席時対応加算一覧CSV
     */
    public function absenceResponseCsv(Request $request)
    {
        $user = $request->user();

        $query = AbsenceResponseRecord::where('classroom_id', $user->classroom_id)
            ->with(['student:id,student_name', 'staff:id,full_name']);

        if ($request->filled('date_from')) {
            $query->where('absence_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('absence_date', '<=', $request->date_to);
        }

        $records = $query->orderByDesc('absence_date')->get();

        $csv = "\xEF\xBB\xBF"; // BOM for Excel
        $csv .= "日付,生徒名,欠席理由,対応内容,連絡方法,連絡内容,担当者,送信済,送信日時\n";

        foreach ($records as $r) {
            $csv .= implode(',', [
                $r->absence_date->format('Y/m/d'),
                '"' . str_replace('"', '""', $r->student?->student_name ?? '') . '"',
                '"' . str_replace('"', '""', $r->absence_reason ?? '') . '"',
                '"' . str_replace('"', '""', $r->response_content ?? '') . '"',
                '"' . str_replace('"', '""', $r->contact_method ?? '') . '"',
                '"' . str_replace('"', '""', $r->contact_content ?? '') . '"',
                '"' . str_replace('"', '""', $r->staff?->full_name ?? '') . '"',
                $r->is_sent ? 'はい' : 'いいえ',
                $r->sent_at ? $r->sent_at->format('Y/m/d H:i') : '',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="absence_response_' . date('Ymd') . '.csv"',
        ]);
    }
}
