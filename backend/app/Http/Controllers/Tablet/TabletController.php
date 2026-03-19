<?php

namespace App\Http\Controllers\Tablet;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
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

        $records = $query->orderByDesc('created_at')->get();

        // 旧アプリと同じ形式: 参加者数を付与
        $records->each(function ($record) {
            $record->participant_count = $record->studentRecords->count();
        });

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * 活動の詳細を取得（ID指定）
     */
    public function activityDetail(Request $request, DailyRecord $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $activity->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $activity->load(['studentRecords.student:id,student_name', 'staff:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $activity,
        ]);
    }

    /**
     * 指定月の活動がある日付一覧を取得
     */
    public function activeDates(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $query = DailyRecord::whereYear('record_date', $year)
            ->whereMonth('record_date', $month)
            ->select(DB::raw('DISTINCT record_date'));

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $dates = $query->pluck('record_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $dates,
        ]);
    }

    /**
     * 指定日の支援案一覧を取得
     */
    public function supportPlans(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $date = $request->input('date', now()->toDateString());

        $query = ActivitySupportPlan::where('activity_date', $date)
            ->with('staff:id,full_name');

        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        $plans = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 活動記録を作成（旧アプリ互換: activity_name + student_ids で作成）
     */
    public function storeActivity(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'record_date'     => 'required|date',
            'activity_name'   => 'required|string|max:255',
            'common_activity' => 'nullable|string|max:2000',
            'student_ids'     => 'required|array|min:1',
            'student_ids.*'   => 'exists:students,id',
        ]);

        $record = DB::transaction(function () use ($validated, $user) {
            $record = DailyRecord::create([
                'classroom_id'    => $user->classroom_id,
                'record_date'     => $validated['record_date'],
                'activity_name'   => $validated['activity_name'],
                'common_activity' => $validated['common_activity'] ?? $validated['activity_name'],
                'staff_id'        => $user->id,
            ]);

            // 参加者の student_records を空で作成（旧アプリと同じ）
            foreach ($validated['student_ids'] as $studentId) {
                StudentRecord::create([
                    'daily_record_id' => $record->id,
                    'student_id'      => $studentId,
                ]);
            }

            return $record;
        });

        return response()->json([
            'success' => true,
            'data'    => $record->load('studentRecords.student:id,student_name'),
            'message' => '活動記録を登録しました。',
        ], 201);
    }

    /**
     * 活動記録を更新（旧アプリ互換: activity_name + student_ids）
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
            'student_ids'     => 'nullable|array',
            'student_ids.*'   => 'exists:students,id',
        ]);

        DB::transaction(function () use ($activity, $validated) {
            $activity->update([
                'activity_name'   => $validated['activity_name'] ?? $activity->activity_name,
                'common_activity' => $validated['common_activity'] ?? $activity->common_activity,
            ]);

            if (isset($validated['student_ids'])) {
                // 既存の生徒記録を削除して再作成（旧アプリと同じ動作）
                $activity->studentRecords()->delete();
                foreach ($validated['student_ids'] as $studentId) {
                    StudentRecord::create([
                        'daily_record_id' => $activity->id,
                        'student_id'      => $studentId,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $activity->fresh()->load('studentRecords.student:id,student_name'),
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
     * 連絡帳入力: 個別生徒の記録を保存（旧 renrakucho_save.php の save_student アクション）
     */
    public function saveStudentRecord(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'daily_record_id'       => 'required|exists:daily_records,id',
            'student_id'            => 'required|exists:students,id',
            'notes'                 => 'nullable|string|max:5000',
            'health_life'           => 'nullable|string|max:5000',
            'motor_sensory'         => 'nullable|string|max:5000',
            'cognitive_behavior'    => 'nullable|string|max:5000',
            'language_communication' => 'nullable|string|max:5000',
            'social_relations'      => 'nullable|string|max:5000',
        ]);

        // 権限チェック
        $record = DailyRecord::findOrFail($validated['daily_record_id']);
        if ($user->classroom_id && $record->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $studentRecord = StudentRecord::updateOrCreate(
            [
                'daily_record_id' => $validated['daily_record_id'],
                'student_id'      => $validated['student_id'],
            ],
            [
                'notes'                  => $validated['notes'] ?? null,
                'health_life'            => $validated['health_life'] ?? null,
                'motor_sensory'          => $validated['motor_sensory'] ?? null,
                'cognitive_behavior'     => $validated['cognitive_behavior'] ?? null,
                'language_communication' => $validated['language_communication'] ?? null,
                'social_relations'       => $validated['social_relations'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $studentRecord,
            'message' => '生徒記録を保存しました。',
        ]);
    }

    /**
     * 連絡帳一括保存（旧 renrakucho_save.php の通常保存）
     */
    public function saveRenrakucho(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'daily_record_id'       => 'required|exists:daily_records,id',
            'common_activity'       => 'required|string|max:2000',
            'students'              => 'required|array|min:1',
            'students.*.student_id' => 'required|exists:students,id',
            'students.*.notes'      => 'nullable|string|max:5000',
            'students.*.health_life' => 'nullable|string|max:5000',
            'students.*.motor_sensory' => 'nullable|string|max:5000',
            'students.*.cognitive_behavior' => 'nullable|string|max:5000',
            'students.*.language_communication' => 'nullable|string|max:5000',
            'students.*.social_relations' => 'nullable|string|max:5000',
        ]);

        $record = DailyRecord::findOrFail($validated['daily_record_id']);
        if ($user->classroom_id && $record->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        DB::transaction(function () use ($record, $validated) {
            $record->update([
                'common_activity' => $validated['common_activity'],
            ]);

            foreach ($validated['students'] as $s) {
                StudentRecord::updateOrCreate(
                    [
                        'daily_record_id' => $record->id,
                        'student_id'      => $s['student_id'],
                    ],
                    [
                        'notes'                  => $s['notes'] ?? null,
                        'health_life'            => $s['health_life'] ?? null,
                        'motor_sensory'          => $s['motor_sensory'] ?? null,
                        'cognitive_behavior'     => $s['cognitive_behavior'] ?? null,
                        'language_communication' => $s['language_communication'] ?? null,
                        'social_relations'       => $s['social_relations'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => '連絡帳を保存しました。',
        ]);
    }

    /**
     * 統合連絡帳: 活動の参加者と記録を取得
     */
    public function integrateData(Request $request, DailyRecord $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $activity->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $activity->load([
            'studentRecords.student:id,student_name',
            'integratedNotes',
        ]);

        $participants = $activity->studentRecords->map(function ($sr) use ($activity) {
            $integratedNote = $activity->integratedNotes->firstWhere('student_id', $sr->student_id);
            return [
                'id'                  => $sr->student->id,
                'student_name'        => $sr->student->student_name,
                'notes'               => $sr->notes,
                'integrated_id'       => $integratedNote?->id,
                'integrated_content'  => $integratedNote?->integrated_content,
                'is_sent'             => $integratedNote?->is_sent ?? false,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'activity'     => $activity->only(['id', 'activity_name', 'record_date', 'common_activity']),
                'participants' => $participants,
            ],
        ]);
    }

    /**
     * 統合連絡帳を保存（旧 activity_integrate.php のPOST処理）
     */
    public function saveIntegration(Request $request, DailyRecord $activity): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id && $activity->classroom_id !== $user->classroom_id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'contents'              => 'required|array',
            'contents.*.student_id' => 'required|exists:students,id',
            'contents.*.content'    => 'nullable|string|max:10000',
        ]);

        DB::transaction(function () use ($activity, $validated) {
            foreach ($validated['contents'] as $item) {
                $content = trim($item['content'] ?? '');
                if (empty($content)) {
                    continue;
                }

                IntegratedNote::updateOrCreate(
                    [
                        'daily_record_id' => $activity->id,
                        'student_id'      => $item['student_id'],
                    ],
                    [
                        'integrated_content' => $content,
                        'is_sent'            => false,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => '統合連絡帳を保存しました。',
        ]);
    }

    /**
     * 生徒記録を統合して連絡帳（統合ノート）を作成（自動統合）
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
                $contents = [];
                foreach ($records as $record) {
                    $studentRecord = $record->studentRecords->firstWhere('student_id', $studentId);
                    if ($studentRecord && $studentRecord->notes) {
                        $contents[] = "【{$record->activity_name}】{$studentRecord->notes}";
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
