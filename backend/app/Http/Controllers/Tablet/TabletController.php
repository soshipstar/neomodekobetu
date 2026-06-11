<?php

namespace App\Http\Controllers\Tablet;

use App\Http\Controllers\Controller;
use App\Models\ActivitySupportPlan;
use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Services\ServiceTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TabletController extends Controller
{
    /**
     * 強み(才能)チェック payload を、対象事業所のサービス種別で定義された
     * 強みキー一覧に限定し、0-10 にクランプする。
     * 全項目が未指定なら null を返してカラムを空にする。
     */
    private function sanitizeStrengths(?array $strengths, ?string $serviceType = null): ?array
    {
        if (empty($strengths)) {
            return null;
        }

        $keys = ServiceTypeRegistry::strengthKeys($serviceType ?? ServiceTypeRegistry::AFTER_SCHOOL);

        $sanitized = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $strengths)) {
                continue;
            }
            $value = $strengths[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $sanitized[$key] = max(0, min(10, (int) $value));
        }

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * 対象事業所のサービス種別を取得する。after_school にフォールバック。
     */
    private function classroomServiceType(?int $classroomId): string
    {
        if (!$classroomId) {
            return ServiceTypeRegistry::AFTER_SCHOOL;
        }
        $type = Classroom::query()->where('id', $classroomId)->value('service_type');
        return ServiceTypeRegistry::isValid((string) $type)
            ? (string) $type
            : ServiceTypeRegistry::AFTER_SCHOOL;
    }

    /**
     * service_type_data の payload をサービス種別ごとの想定キーだけに限定する。
     * 詳細は Staff\RenrakuchoController::sanitizeServiceTypeData と同じ。
     */
    private function sanitizeServiceTypeData(?array $data, string $serviceType): ?array
    {
        if (empty($data)) {
            return null;
        }

        $allowed = match ($serviceType) {
            ServiceTypeRegistry::EMPLOYMENT_A,
            ServiceTypeRegistry::EMPLOYMENT_B => [
                'wage_eligible_hours' => 'float',
                'clock_in'            => 'time',
                'clock_out'           => 'time',
                'work_content'        => 'string',
            ],
            ServiceTypeRegistry::TRANSITION => [
                'practice_content'      => 'string',
                'job_search_record'     => 'string',
                'business_manner_score' => 'int_1_5',
            ],
            default => [],
        };

        if ($allowed === []) {
            return null;
        }

        $sanitized = [];
        foreach ($allowed as $key => $type) {
            if (!array_key_exists($key, $data)) continue;
            $value = $data[$key];
            if ($value === null || $value === '') continue;

            switch ($type) {
                case 'float':
                    if (is_numeric($value)) {
                        $sanitized[$key] = (float) $value;
                    }
                    break;
                case 'int_1_5':
                    if (is_numeric($value)) {
                        $sanitized[$key] = max(1, min(5, (int) $value));
                    }
                    break;
                case 'time':
                    if (is_string($value) && preg_match('/^\d{1,2}:\d{2}$/', $value)) {
                        $sanitized[$key] = $value;
                    }
                    break;
                case 'string':
                default:
                    if (is_string($value)) {
                        $trimmed = trim($value);
                        if ($trimmed !== '') $sanitized[$key] = $trimmed;
                    }
                    break;
            }
        }

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * 本日の利用者一覧 (= スタッフ画面の dashboard/attendance 相当)。
     *
     * その日に「来所予定」「振替予定」「追加利用」のいずれかに該当する生徒を返す。
     * 各エントリは:
     *  - 出欠状況 (is_absent)
     *  - 到着済か (is_checked_in)、退所済か (is_checked_out)、到着時刻 (check_in_time)
     *  - 種別 (regular / makeup / additional)
     *  - 学年区分 (grade_group)
     * を含む。タブレットの出欠確認画面で誰に出欠連絡が必要かを一覧する用途。
     */
    public function todayStudents(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $accessibleIds = $classroomId ? [$classroomId] : [];
        $date = \Carbon\Carbon::parse($request->query('date', \Carbon\Carbon::today()->toDateString()));
        $dayColumn = 'scheduled_' . strtolower($date->format('l'));

        $gradeGroupMap = function ($gradeLevel): string {
            if ($gradeLevel === null) return '未就学';
            $gl = (string) $gradeLevel;
            if (str_starts_with($gl, 'preschool')) return '未就学';
            if (str_starts_with($gl, 'elementary')) return '小学生';
            if (str_starts_with($gl, 'junior_high')) return '中学生';
            if (str_starts_with($gl, 'high_school')) return '高校生';
            return 'その他';
        };

        $results = [];

        // 通常通所
        $regularStudents = Student::whereIn('classroom_id', $accessibleIds)
            ->where('status', 'active')
            ->where($dayColumn, true)
            ->get(['id', 'student_name', 'grade_level']);
        foreach ($regularStudents as $s) {
            $results[$s->id] = [
                'id'          => $s->id,
                'name'        => $s->student_name,
                'grade_level' => $s->grade_level,
                'grade_group' => $gradeGroupMap($s->grade_level),
                'type'        => 'regular',
                'is_absent'   => false,
            ];
        }

        // 振替 (approved)
        try {
            $makeup = \App\Models\AbsenceNotification::whereHas('student', function ($q) use ($accessibleIds) {
                $q->whereIn('classroom_id', $accessibleIds)->where('is_active', true);
            })
                ->where('makeup_status', 'approved')
                ->whereDate('makeup_request_date', $date)
                ->with('student:id,student_name,grade_level')
                ->get();
            foreach ($makeup as $m) {
                if ($m->student && !isset($results[$m->student->id])) {
                    $results[$m->student->id] = [
                        'id'          => $m->student->id,
                        'name'        => $m->student->student_name,
                        'grade_level' => $m->student->grade_level,
                        'grade_group' => $gradeGroupMap($m->student->grade_level),
                        'type'        => 'makeup',
                        'is_absent'   => false,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // 振替が無くてもエラーにしない
        }

        // 加算利用 (additional_usages)
        try {
            $additional = DB::table('additional_usages')
                ->join('students', 'additional_usages.student_id', '=', 'students.id')
                ->whereIn('students.classroom_id', $accessibleIds)
                ->where('students.is_active', true)
                ->whereDate('additional_usages.usage_date', $date)
                ->select('students.id', 'students.student_name', 'students.grade_level')
                ->get();
            foreach ($additional as $s) {
                if (!isset($results[$s->id])) {
                    $results[$s->id] = [
                        'id'          => $s->id,
                        'name'        => $s->student_name,
                        'grade_level' => $s->grade_level,
                        'grade_group' => $gradeGroupMap($s->grade_level),
                        'type'        => 'additional',
                        'is_absent'   => false,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // additional_usages テーブルが無い環境はスキップ
        }

        // 欠席フラグ
        if (!empty($results)) {
            $absentIds = \App\Models\AbsenceNotification::whereIn('student_id', array_keys($results))
                ->whereDate('absence_date', $date)
                ->pluck('student_id')
                ->toArray();
            foreach ($absentIds as $sid) {
                if (isset($results[$sid])) {
                    $results[$sid]['is_absent'] = true;
                }
            }
        }

        // 出欠記録 (attendance_records) を結合
        $attendance = DB::table('attendance_records')
            ->whereIn('student_id', array_keys($results) ?: [0])
            ->whereDate('record_date', $date)
            ->get(['student_id', 'check_in_time', 'check_out_time']);
        foreach ($attendance as $a) {
            if (isset($results[$a->student_id])) {
                $results[$a->student_id]['is_checked_in']  = $a->check_in_time !== null;
                $results[$a->student_id]['is_checked_out'] = $a->check_out_time !== null;
                $results[$a->student_id]['check_in_time']  = $a->check_in_time;
                $results[$a->student_id]['check_out_time'] = $a->check_out_time;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => array_values($results),
            'date'    => $date->toDateString(),
        ]);
    }

    /**
     * 教室に所属する生徒一覧を取得
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();
        // tablet も /staff/* と同様に「現在 workspace 切替中の教室」のみを対象にする。
        // (マスターに対する accessibleClassroomIds() の全教室返却を回避)
        $classroomId = $user->classroom_id;
        $accessibleIds = $classroomId ? [$classroomId] : [];

        $query = Student::where('status', 'active')
            ->whereIn('classroom_id', $accessibleIds);

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

        // AUTH-09 修正: classroom_id=null のタブレットアカウントでガードを
        // 完全スキップしていた ($user->classroom_id && ... が false になる) ため、
        // authorizeClassroomId で null=権限なしに統一する。
        $this->authorizeClassroomId($user, $student->classroom_id, 'アクセス権限がありません。');

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

        // AUTH-09 修正: classroom_id=null のタブレットアカウントでガードを
        // 完全スキップしていた ($user->classroom_id && ... が false になる) ため、
        // authorizeClassroomId で null=権限なしに統一する。
        $this->authorizeClassroomId($user, $student->classroom_id, 'アクセス権限がありません。');

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
        $accessibleIds = $user->accessibleClassroomIds();
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
            $query->whereIn('students.classroom_id', $accessibleIds);
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
        $accessibleIds = $user->accessibleClassroomIds();

        // ActivityType モデルが存在する場合はそこから取得
        $options = DB::table('activity_types')
            ->when($classroomId, fn ($q) => $q->where(function ($qq) use ($accessibleIds) {
                $qq->whereIn('classroom_id', $accessibleIds)->orWhereNull('classroom_id');
            }))
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
        $accessibleIds = $user->accessibleClassroomIds();
        $date = $request->input('date', now()->toDateString());

        $query = DailyRecord::where('record_date', $date)
            ->with(['studentRecords.student:id,student_name', 'staff:id,full_name']);

        if ($classroomId) {
            $query->whereIn('classroom_id', $accessibleIds);
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
        $accessibleIds = $user->accessibleClassroomIds();

        $query = DailyRecord::where('record_date', $date)
            ->with(['studentRecords.student:id,student_name', 'staff:id,full_name']);

        if ($classroomId) {
            $query->whereIn('classroom_id', $accessibleIds);
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

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
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
        $accessibleIds = $user->accessibleClassroomIds();
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $query = DailyRecord::whereYear('record_date', $year)
            ->whereMonth('record_date', $month)
            ->select(DB::raw('DISTINCT record_date'));

        if ($classroomId) {
            $query->whereIn('classroom_id', $accessibleIds);
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
     * 指定日の活動案一覧を取得
     */
    public function supportPlans(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $accessibleIds = $user->accessibleClassroomIds();
        $date = $request->input('date', now()->toDateString());

        $query = ActivitySupportPlan::where('activity_date', $date)
            ->with('staff:id,full_name');

        if ($classroomId) {
            $query->whereIn('classroom_id', $accessibleIds);
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

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
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
                // 差分更新: 既存の連絡帳記録（notes/5領域）を消さないため、
                // 参加者リストから外れた生徒のみ削除し、新規追加のみ作成する。
                $newIds = array_map('intval', $validated['student_ids']);
                $existingIds = $activity->studentRecords()->pluck('student_id')->map(fn ($v) => (int) $v)->all();

                $toDelete = array_values(array_diff($existingIds, $newIds));
                $toAdd    = array_values(array_diff($newIds, $existingIds));

                if (!empty($toDelete)) {
                    $activity->studentRecords()->whereIn('student_id', $toDelete)->delete();
                }

                foreach ($toAdd as $studentId) {
                    StudentRecord::updateOrCreate(
                        ['daily_record_id' => $activity->id, 'student_id' => $studentId],
                        []
                    );
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

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
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
            'strengths'             => 'nullable|array',
            'strengths.*'           => 'nullable|integer|min:0|max:10',
            'service_type_data'     => 'nullable|array',
        ]);

        // 権限チェック
        $record = DailyRecord::findOrFail($validated['daily_record_id']);
        if ($user->classroom_id && !in_array($record->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $serviceType = $this->classroomServiceType($record->classroom_id);
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
                'strengths'              => $this->sanitizeStrengths($validated['strengths'] ?? null, $serviceType),
                'service_type_data'      => $this->sanitizeServiceTypeData($validated['service_type_data'] ?? null, $serviceType),
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
            'common_activity'       => 'nullable|string|max:2000',
            'students'              => 'required|array|min:1',
            'students.*.student_id' => 'required|exists:students,id',
            'students.*.notes'      => 'nullable|string|max:5000',
            'students.*.health_life' => 'nullable|string|max:5000',
            'students.*.motor_sensory' => 'nullable|string|max:5000',
            'students.*.cognitive_behavior' => 'nullable|string|max:5000',
            'students.*.language_communication' => 'nullable|string|max:5000',
            'students.*.social_relations' => 'nullable|string|max:5000',
            'students.*.strengths'              => 'nullable|array',
            'students.*.strengths.*'            => 'nullable|integer|min:0|max:10',
            'students.*.service_type_data'      => 'nullable|array',
        ]);

        $record = DailyRecord::findOrFail($validated['daily_record_id']);
        if ($user->classroom_id && !in_array($record->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $serviceType = $this->classroomServiceType($record->classroom_id);
        DB::transaction(function () use ($record, $validated, $serviceType) {
            $record->update([
                'common_activity' => $validated['common_activity'] ?? $record->common_activity,
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
                        'strengths'              => $this->sanitizeStrengths($s['strengths'] ?? null, $serviceType),
                        'service_type_data'      => $this->sanitizeServiceTypeData($s['service_type_data'] ?? null, $serviceType),
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

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
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

        if ($user->classroom_id && !in_array($activity->classroom_id, $user->switchableClassroomIds(), true)) {
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
        $accessibleIds = $user->accessibleClassroomIds();
        $recordDate = $validated['record_date'];
        $studentIds = $validated['student_ids'];

        // 指定日の活動記録を取得
        $records = DailyRecord::where('record_date', $recordDate)
            ->when($classroomId, fn ($q) => $q->whereIn('classroom_id', $accessibleIds))
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
