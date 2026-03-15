<?php

namespace App\Http\Controllers\Tablet;

use App\Http\Controllers\Controller;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TabletController extends Controller
{
    /**
     * 教室に所属する生徒一覧を取得
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Student::where('status', 'active');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $students = $query->orderBy('student_name')
            ->get(['id', 'student_name', 'classroom_id', 'grade_level']);

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 生徒のチェックイン（出席登録）
     */
    public function checkIn(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $today = now()->toDateString();

        // 既にチェックイン済みか確認
        $existing = DB::table('attendance_records')
            ->where('student_id', $student->id)
            ->where('record_date', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '既に出席登録されています。',
            ], 422);
        }

        DB::table('attendance_records')->insert([
            'student_id'    => $student->id,
            'classroom_id'  => $student->classroom_id,
            'record_date'   => $today,
            'check_in_time' => now(),
            'status'        => 'present',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $student->student_name . 'さんの出席を登録しました。',
        ], 201);
    }

    /**
     * 生徒のチェックアウト（退出登録）
     */
    public function checkOut(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $today = now()->toDateString();

        $record = DB::table('attendance_records')
            ->where('student_id', $student->id)
            ->where('record_date', $today)
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => '出席記録がありません。先にチェックインしてください。',
            ], 422);
        }

        DB::table('attendance_records')
            ->where('id', $record->id)
            ->update([
                'check_out_time' => now(),
                'updated_at'     => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => $student->student_name . 'さんの退出を登録しました。',
        ]);
    }

    /**
     * 本日出席中の生徒一覧を取得
     */
    public function presentStudents(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $today = now()->toDateString();

        $query = DB::table('attendance_records')
            ->join('students', 'attendance_records.student_id', '=', 'students.id')
            ->where('attendance_records.record_date', $today)
            ->whereNotNull('attendance_records.check_in_time')
            ->whereNull('attendance_records.check_out_time')
            ->select(
                'students.id',
                'students.student_name',
                'students.classroom_id',
                'students.grade_level',
                'attendance_records.check_in_time'
            );

        if ($classroomId) {
            $query->where('students.classroom_id', $classroomId);
        }

        $students = $query->orderBy('students.student_name')->get();

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 活動オプション一覧を取得（アクティビティ種別）
     */
    public function activityOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // ActivityType モデルが存在する場合はそこから取得
        $options = DB::table('activity_types')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId)->orWhereNull('classroom_id'))
            ->when(! $classroomId, fn ($q) => $q)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'type_name', 'description', 'sort_order']);

        return response()->json([
            'success' => true,
            'data'    => $options,
        ]);
    }

    /**
     * 活動記録一覧を取得（日付をクエリパラメータから取得）
     */
    public function activityRecords(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $date = $request->input('date', now()->toDateString());

        $query = DailyRecord::where('record_date', $date)
            ->with(['studentRecords.student:id,student_name', 'staff:id,full_name']);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $records = $query->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * 指定日の活動記録一覧を取得
     */
    public function activities(Request $request, string $date): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = DailyRecord::where('record_date', $date)
            ->with(['studentRecords.student:id,student_name', 'staff:id,full_name']);

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $records = $query->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * 活動記録を作成
     */
    public function storeActivity(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'record_date'     => 'required|date',
            'activity_name'   => 'required|string|max:255',
            'common_activity' => 'nullable|string|max:2000',
            'student_records' => 'nullable|array',
            'student_records.*.student_id' => 'required_with:student_records|exists:students,id',
            'student_records.*.content'    => 'nullable|string|max:2000',
            'student_records.*.attendance' => 'nullable|string|in:present,absent,late',
        ]);

        $record = DB::transaction(function () use ($validated, $user) {
            $record = DailyRecord::create([
                'classroom_id'    => $user->classroom_id,
                'record_date'     => $validated['record_date'],
                'activity_name'   => $validated['activity_name'],
                'common_activity' => $validated['common_activity'] ?? null,
                'staff_id'        => $user->id,
            ]);

            if (! empty($validated['student_records'])) {
                foreach ($validated['student_records'] as $sr) {
                    StudentRecord::create([
                        'daily_record_id' => $record->id,
                        'student_id'      => $sr['student_id'],
                        'content'         => $sr['content'] ?? null,
                        'attendance'      => $sr['attendance'] ?? 'present',
                    ]);
                }
            }

            return $record;
        });

        return response()->json([
            'success' => true,
            'data'    => $record->load('studentRecords'),
            'message' => '活動記録を登録しました。',
        ], 201);
    }

    /**
     * 活動記録を更新
     */
    public function updateActivity(Request $request, DailyRecord $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $activity->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'activity_name'   => 'sometimes|string|max:255',
            'common_activity' => 'nullable|string|max:2000',
            'student_records' => 'nullable|array',
            'student_records.*.student_id' => 'required_with:student_records|exists:students,id',
            'student_records.*.content'    => 'nullable|string|max:2000',
            'student_records.*.attendance' => 'nullable|string|in:present,absent,late',
        ]);

        DB::transaction(function () use ($activity, $validated) {
            $activity->update([
                'activity_name'   => $validated['activity_name'] ?? $activity->activity_name,
                'common_activity' => $validated['common_activity'] ?? $activity->common_activity,
            ]);

            if (isset($validated['student_records'])) {
                // 既存の生徒記録を更新または作成
                foreach ($validated['student_records'] as $sr) {
                    StudentRecord::updateOrCreate(
                        [
                            'daily_record_id' => $activity->id,
                            'student_id'      => $sr['student_id'],
                        ],
                        [
                            'content'    => $sr['content'] ?? null,
                            'attendance' => $sr['attendance'] ?? 'present',
                        ]
                    );
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $activity->fresh()->load('studentRecords'),
            'message' => '更新しました。',
        ]);
    }

    /**
     * 活動記録を削除
     */
    public function deleteActivity(Request $request, DailyRecord $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $activity->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        DB::transaction(function () use ($activity) {
            $activity->studentRecords()->delete();
            $activity->integratedNotes()->delete();
            $activity->delete();
        });

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * 生徒記録を統合して連絡帳（統合ノート）を作成
     */
    public function integrateActivities(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'record_date' => 'required|date',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $classroomId = $user->classroom_id;
        $recordDate = $validated['record_date'];
        $studentIds = $validated['student_ids'];

        // 指定日の活動記録を取得
        $records = DailyRecord::where('record_date', $recordDate)
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->with(['studentRecords' => function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds);
            }])
            ->get();

        $createdCount = 0;

        DB::transaction(function () use ($records, $studentIds, &$createdCount) {
            foreach ($studentIds as $studentId) {
                // この生徒の全記録を統合
                $contents = [];
                foreach ($records as $record) {
                    $studentRecord = $record->studentRecords->firstWhere('student_id', $studentId);
                    if ($studentRecord && $studentRecord->content) {
                        $contents[] = "【{$record->activity_name}】{$studentRecord->content}";
                    }
                }

                if (empty($contents)) {
                    continue;
                }

                $integratedContent = implode("\n", $contents);

                IntegratedNote::updateOrCreate(
                    [
                        'daily_record_id' => $records->first()->id,
                        'student_id'      => $studentId,
                    ],
                    [
                        'integrated_content' => $integratedContent,
                    ]
                );

                $createdCount++;
            }
        });

        return response()->json([
            'success' => true,
            'count'   => $createdCount,
            'message' => "{$createdCount}件の連絡帳を作成しました。",
        ]);
    }

    /**
     * 連絡帳（統合ノート）を直接保存
     */
    public function storeRenrakucho(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id'         => 'required|exists:students,id',
            'daily_record_id'    => 'required|exists:daily_records,id',
            'integrated_content' => 'required|string|max:5000',
        ]);

        $note = IntegratedNote::updateOrCreate(
            [
                'daily_record_id' => $validated['daily_record_id'],
                'student_id'      => $validated['student_id'],
            ],
            [
                'integrated_content' => $validated['integrated_content'],
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $note,
            'message' => '連絡帳を保存しました。',
        ]);
    }
}
