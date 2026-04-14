<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassroomCapacity;
use App\Models\Student;
use App\Services\KakehashiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WaitingListController extends Controller
{
    /**
     * 待機リスト（status=waiting の生徒一覧）を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Student::where('status', 'waiting')
            ->with(['guardian:id,full_name,email']);

        // マスター管理者は全教室の待機者を閲覧可能。
        // それ以外は自教室のみ（リクエスト経由の指定もマスター時のみ反映）
        $isMaster = (bool) ($user->is_master ?? false);
        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }
        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        // Legacy order: desired_start_date ASC (NULLS LAST), created_at ASC
        $students = $query->orderByRaw('desired_start_date ASC NULLS LAST')
            ->orderBy('created_at')
            ->get();

        $data = $students->map(function ($s) {
            return [
                'id'              => $s->id,
                'student_name'    => $s->student_name,
                'birth_date'      => $s->birth_date,
                'grade_level'     => $s->grade_level,
                'guardian_name'   => $s->guardian?->full_name ?? '-',
                'guardian_email'  => $s->guardian?->email,
                'desired_start_date'   => $s->desired_start_date,
                'desired_weekly_count' => $s->desired_weekly_count,
                'waiting_notes'        => $s->waiting_notes,
                'desired_monday'       => $s->desired_monday,
                'desired_tuesday'      => $s->desired_tuesday,
                'desired_wednesday'    => $s->desired_wednesday,
                'desired_thursday'     => $s->desired_thursday,
                'desired_friday'       => $s->desired_friday,
                'desired_saturday'     => $s->desired_saturday,
                'desired_sunday'       => $s->desired_sunday,
                'status'               => $s->status,
                'created_at'           => $s->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * 曜日別の待機数・在籍利用者数・定員サマリーを返す
     * Legacy: active, trial, short_term を利用者としてカウント
     *
     * マスター管理者の場合、特定の classroom_id をクエリで指定したときのみ
     * その教室のサマリーを返す。指定しない場合は教室別の集計を行わず
     * 全教室横断のサマリーを返す（classroomId=null）。
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = (bool) ($user->is_master ?? false);
        if ($isMaster) {
            // master はリクエスト指定の教室、または全教室
            $classroomId = $request->filled('classroom_id') ? (int) $request->classroom_id : null;
        } else {
            $classroomId = $user->classroom_id;
        }

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        // day_of_week mapping: sunday=0, monday=1, ..., saturday=6
        $dayOfWeekMap = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6,
        ];

        // 定員設定を取得
        $capacitySettings = collect();
        if ($classroomId) {
            $capacitySettings = ClassroomCapacity::where('classroom_id', $classroomId)
                ->get()
                ->keyBy('day_of_week');

            // 定員設定がない場合はデフォルトで初期化
            if ($capacitySettings->count() < 7) {
                for ($d = 0; $d <= 6; $d++) {
                    if (!$capacitySettings->has($d)) {
                        ClassroomCapacity::create([
                            'classroom_id' => $classroomId,
                            'day_of_week' => $d,
                            'max_capacity' => 10,
                            'is_open' => true,
                        ]);
                    }
                }
                $capacitySettings = ClassroomCapacity::where('classroom_id', $classroomId)
                    ->get()
                    ->keyBy('day_of_week');
            }
        }

        $result = [];

        foreach ($days as $day) {
            $waitingQuery = Student::where('status', 'waiting');
            // Legacy: active, trial, short_term AND is_active=1
            $activeQuery = Student::whereIn('status', ['active', 'trial', 'short_term'])
                ->where('is_active', true);

            if ($classroomId) {
                $waitingQuery->where('classroom_id', $classroomId);
                $activeQuery->where('classroom_id', $classroomId);
            }

            $dow = $dayOfWeekMap[$day];
            $cap = $capacitySettings->get($dow);

            $activeCount = (clone $activeQuery)->where("scheduled_{$day}", true)->count();
            $maxCapacity = $cap ? $cap->max_capacity : 10;
            $isOpen = $cap ? $cap->is_open : true;

            $result[] = [
                'day'            => $day,
                'waiting_count'  => (clone $waitingQuery)->where("desired_{$day}", true)->count(),
                'active_count'   => $activeCount,
                'max_capacity'   => $maxCapacity,
                'is_open'        => $isOpen,
                'available'      => max(0, $maxCapacity - $activeCount),
            ];
        }

        // 合計
        $totalWaiting = Student::where('status', 'waiting')
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->count();

        // 曜日別の在籍生徒リスト & 待機生徒リスト
        $dayStudents = [];
        foreach ($days as $day) {
            $dow = $dayOfWeekMap[$day];
            $cap = $capacitySettings->get($dow);
            $isOpen = $cap ? $cap->is_open : true;

            if (!$isOpen) {
                $dayStudents[$day] = ['enrolled' => [], 'waiting' => []];
                continue;
            }

            $enrolledQuery = Student::whereIn('status', ['active', 'trial', 'short_term'])
                ->where('is_active', true)
                ->where("scheduled_{$day}", true);
            if ($classroomId) {
                $enrolledQuery->where('classroom_id', $classroomId);
            }
            $enrolled = $enrolledQuery->with('guardian:id,full_name')
                ->orderBy('grade_level')
                ->orderBy('student_name')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'student_name' => $s->student_name,
                    'grade_level' => $s->grade_level,
                    'status' => $s->status,
                    'guardian_name' => $s->guardian?->full_name ?? '-',
                ]);

            $waitingDayQuery = Student::where('status', 'waiting')
                ->where("desired_{$day}", true);
            if ($classroomId) {
                $waitingDayQuery->where('classroom_id', $classroomId);
            }
            $waitingList = $waitingDayQuery->with('guardian:id,full_name')
                ->orderByRaw('desired_start_date ASC NULLS LAST')
                ->orderBy('student_name')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'student_name' => $s->student_name,
                    'grade_level' => $s->grade_level,
                    'desired_start_date' => $s->desired_start_date,
                    'guardian_name' => $s->guardian?->full_name ?? '-',
                ]);

            $dayStudents[$day] = [
                'enrolled' => $enrolled,
                'waiting' => $waitingList,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'days'          => $result,
                'total_waiting' => $totalWaiting,
                'day_students'  => $dayStudents,
            ],
        ]);
    }

    /**
     * 待機生徒の情報を更新（ステータス変更含む）
     * Legacy admit: desired → scheduled にコピーし、desired フィールドをクリア、
     * support_start_date を設定、かけはし期間を自動生成
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'status'               => 'sometimes|string|in:waiting,active,pre_withdrawal',
            'desired_start_date'   => 'nullable|date',
            'desired_weekly_count' => 'nullable|integer|min:1|max:7',
            'waiting_notes'        => 'nullable|string|max:1000',
            'withdrawal_reason'    => 'nullable|string|max:1000',
            'desired_monday'       => 'nullable|boolean',
            'desired_tuesday'      => 'nullable|boolean',
            'desired_wednesday'    => 'nullable|boolean',
            'desired_thursday'     => 'nullable|boolean',
            'desired_friday'       => 'nullable|boolean',
            'desired_saturday'     => 'nullable|boolean',
            'desired_sunday'       => 'nullable|boolean',
        ]);

        // 入所処理: desired → scheduled にコピー、desired をクリア
        if (($validated['status'] ?? null) === 'active' && $student->status === 'waiting') {
            $validated['is_active'] = true;
            $supportStartDate = $student->desired_start_date ?: now()->toDateString();
            $validated['support_start_date'] = $supportStartDate;

            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                // Copy desired to scheduled
                $validated["scheduled_{$day}"] = $student->{"desired_{$day}"} ?? false;
                // Clear desired fields (legacy behavior)
                $validated["desired_{$day}"] = false;
            }
            // Clear other waiting-specific fields
            $validated['desired_start_date'] = null;
            $validated['desired_weekly_count'] = null;
            $validated['waiting_notes'] = null;

            $student->update($validated);

            // かけはし期間の自動生成 (legacy behavior)
            try {
                $kakehashiService = app(KakehashiService::class);
                $generatedPeriods = $kakehashiService->generateKakehashiPeriodsForStudent($student->id, $supportStartDate);
                Log::info("Generated " . count($generatedPeriods) . " kakehashi periods for student {$student->id} on admission");
            } catch (\Exception $e) {
                Log::error("かけはし期間生成エラー（入所時）: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data'    => $student->fresh(),
                'message' => '入所処理が完了しました。',
            ]);
        }

        // 入所前辞退処理
        if (($validated['status'] ?? null) === 'pre_withdrawal' && $student->status === 'waiting') {
            $student->update([
                'status' => 'pre_withdrawal',
                'is_active' => false,
                'withdrawal_reason' => $validated['withdrawal_reason'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $student->fresh(),
                'message' => '入所前辞退として処理しました。',
            ]);
        }

        $student->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $student->fresh(),
            'message' => '更新しました。',
        ]);
    }

    /**
     * 営業日・定員設定の更新
     */
    public function updateCapacity(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        if (!$classroomId) {
            return response()->json([
                'success' => false,
                'message' => '教室IDが設定されていません。',
            ], 422);
        }

        $validated = $request->validate([
            'capacities' => 'required|array',
            'capacities.*.day_of_week' => 'required|integer|min:0|max:6',
            'capacities.*.max_capacity' => 'required|integer|min:0|max:100',
            'capacities.*.is_open' => 'required|boolean',
        ]);

        foreach ($validated['capacities'] as $cap) {
            ClassroomCapacity::updateOrCreate(
                [
                    'classroom_id' => $classroomId,
                    'day_of_week' => $cap['day_of_week'],
                ],
                [
                    'max_capacity' => $cap['max_capacity'],
                    'is_open' => $cap['is_open'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => '営業日・定員設定を更新しました。',
        ]);
    }
}
