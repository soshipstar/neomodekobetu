<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\KakehashiGuardian;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Services\KakehashiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PendingTaskController extends Controller
{
    /**
     * 未作成タスク一覧を取得
     * 個別支援計画書・モニタリング・かけはし（保護者・スタッフ）の未作成タスクを集約
     * Legacy: pending_tasks.php + pending_tasks_helper.php の完全移植
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $today = Carbon::today();
        $oneMonthLater = Carbon::today()->addMonth();

        // かけはし期間の自動生成（Legacy: pending_tasks.php の autoGenerateNextKakehashiPeriods と同等）
        try {
            app(KakehashiService::class)->autoGenerateNextKakehashiPeriods();
        } catch (\Exception $e) {
            // 自動生成失敗してもタスク一覧は返す
            \Log::warning('Auto-generate kakehashi periods failed: ' . $e->getMessage());
        }

        // 1. 個別支援計画書タスク
        $planTasks = $this->getPlanTasks($classroomId, $today, $oneMonthLater);

        // 2. モニタリングタスク
        $monitoringTasks = $this->getMonitoringTasks($classroomId, $today, $oneMonthLater);

        // 3. 保護者かけはしタスク
        $guardianKakehashiTasks = $this->getGuardianKakehashiTasks($classroomId, $today, $oneMonthLater);

        // 4. スタッフかけはしタスク
        $staffKakehashiTasks = $this->getStaffKakehashiTasks($classroomId, $today, $oneMonthLater);

        return response()->json([
            'success' => true,
            'data'    => [
                'plans'              => $planTasks,
                'monitoring'         => $monitoringTasks,
                'guardian_kakehashi' => $guardianKakehashiTasks,
                'staff_kakehashi'    => $staffKakehashiTasks,
            ],
            'summary' => [
                'plans'              => count($planTasks),
                'monitoring'         => count($monitoringTasks),
                'guardian_kakehashi' => count($guardianKakehashiTasks),
                'staff_kakehashi'    => count($staffKakehashiTasks),
            ],
            'total_count' => count($planTasks) + count($monitoringTasks) + count($guardianKakehashiTasks) + count($staffKakehashiTasks),
        ]);
    }

    /**
     * 個別支援計画書の未作成タスクを取得
     */
    private function getPlanTasks(?int $classroomId, Carbon $today, Carbon $oneMonthLater): array
    {
        $query = Student::query()
            ->where('is_active', true)
            ->whereHas('guardian');

        if ($classroomId) {
            $query->whereHas('guardian', fn ($q) => $q->where('classroom_id', $classroomId));
        }

        $students = $query->with(['supportPlans' => function ($q) {
            $q->orderByDesc('created_date');
        }])->get();

        $result = [];

        foreach ($students as $student) {
            $plans = $student->supportPlans;
            $latestSubmittedPlan = $plans->where('is_draft', false)->sortByDesc('id')->first();
            $latestSubmittedId = $latestSubmittedPlan?->id;
            $latestSubmittedPlanDate = $latestSubmittedPlan?->created_date;
            $supportStartDate = $student->support_start_date;

            // 計画書がない場合
            if ($plans->isEmpty()) {
                if ($this->isNextPlanDeadlineWithinOneMonth($student->id, $supportStartDate, null, $oneMonthLater)) {
                    $nextPeriod = $this->getNextTargetPeriod($supportStartDate, 0);
                    $result[] = [
                        'student_id'          => $student->id,
                        'student_name'        => $student->student_name,
                        'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                        'plan_id'             => null,
                        'latest_plan_date'    => null,
                        'days_since_plan'     => null,
                        'status_code'         => 'none',
                        'has_newer'           => false,
                        'is_hidden'           => false,
                        'target_period_start' => $nextPeriod['start'],
                        'target_period_end'   => $nextPeriod['end'],
                        'plan_number'         => $nextPeriod['number'],
                    ];
                }
                continue;
            }

            // 下書きがあるかチェック（非表示を除外）
            $draftPlan = $plans->where('is_draft', true)->where('is_hidden', false)->first();

            if ($draftPlan) {
                if ($this->isNextPlanDeadlineWithinOneMonth($student->id, $supportStartDate, $latestSubmittedPlanDate?->format('Y-m-d'), $oneMonthLater)) {
                    $hasNewer = $latestSubmittedId && $draftPlan->id != $latestSubmittedId;
                    $daysSince = $draftPlan->created_date ? (int) abs($today->diffInDays($draftPlan->created_date, false)) : null;

                    // 対象期間を計算
                    $submittedCount = $plans->where('is_draft', false)->count();
                    $nextPeriod = $this->getNextTargetPeriod($supportStartDate, $submittedCount);

                    $result[] = [
                        'student_id'          => $student->id,
                        'student_name'        => $student->student_name,
                        'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                        'plan_id'             => $draftPlan->id,
                        'latest_plan_date'    => $draftPlan->created_date?->format('Y-m-d'),
                        'days_since_plan'     => $daysSince,
                        'status_code'         => 'draft',
                        'has_newer'           => $hasNewer,
                        'is_hidden'           => false,
                        'target_period_start' => $nextPeriod['start'],
                        'target_period_end'   => $nextPeriod['end'],
                        'plan_number'         => $nextPeriod['number'],
                    ];
                }
                continue;
            }

            // 提出済みで保護者確認が必要かチェック
            $needsGuardianConfirm = false;
            if ($latestSubmittedPlan && !$latestSubmittedPlan->is_hidden && !$latestSubmittedPlan->guardian_confirmed) {
                $daysSince = $latestSubmittedPlan->created_date ? (int) abs($today->diffInDays($latestSubmittedPlan->created_date, false)) : null;
                $submittedCount = $plans->where('is_draft', false)->count();
                $nextPeriod = $this->getNextTargetPeriod($supportStartDate, $submittedCount);

                $result[] = [
                    'student_id'          => $student->id,
                    'student_name'        => $student->student_name,
                    'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                    'plan_id'             => $latestSubmittedPlan->id,
                    'latest_plan_date'    => $latestSubmittedPlan->created_date?->format('Y-m-d'),
                    'days_since_plan'     => $daysSince,
                    'status_code'         => 'needs_confirm',
                    'has_newer'           => false,
                    'is_hidden'           => false,
                    'target_period_start' => $nextPeriod['start'] ?? null,
                    'target_period_end'   => $nextPeriod['end'] ?? null,
                    'plan_number'         => $nextPeriod['number'] ?? null,
                ];
                $needsGuardianConfirm = true;
            }

            // 保護者確認が必要でない場合、期限切れかチェック
            if (!$needsGuardianConfirm && $latestSubmittedPlan && !$latestSubmittedPlan->is_hidden) {
                $daysSince = $latestSubmittedPlan->created_date ? (int) abs($today->diffInDays($latestSubmittedPlan->created_date, false)) : 0;
                $needsNewPlan = false;

                // 方法1: 150日以上経過
                if ($daysSince >= 150) {
                    $needsNewPlan = true;
                }

                // 方法2: かけはし期間ベースチェック
                if (!$needsNewPlan) {
                    $hasNewPeriod = KakehashiPeriod::where('student_id', $student->id)
                        ->where('is_active', true)
                        ->where('start_date', '>', $latestSubmittedPlan->created_date)
                        ->where('submission_deadline', '<=', $oneMonthLater)
                        ->exists();
                    if ($hasNewPeriod) {
                        $needsNewPlan = true;
                    }
                }

                if ($needsNewPlan) {
                    $submittedCount = $plans->where('is_draft', false)->count();
                    $nextPeriod = $this->getNextTargetPeriod($supportStartDate, $submittedCount);

                    $result[] = [
                        'student_id'          => $student->id,
                        'student_name'        => $student->student_name,
                        'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                        'plan_id'             => $latestSubmittedPlan->id,
                        'latest_plan_date'    => $latestSubmittedPlan->created_date?->format('Y-m-d'),
                        'days_since_plan'     => $daysSince,
                        'status_code'         => 'outdated',
                        'has_newer'           => false,
                        'is_hidden'           => false,
                        'guardian_confirmed'   => (bool) $latestSubmittedPlan->guardian_confirmed,
                        'target_period_start' => $nextPeriod['start'],
                        'target_period_end'   => $nextPeriod['end'],
                        'plan_number'         => $nextPeriod['number'],
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * モニタリングの未作成タスクを取得
     */
    private function getMonitoringTasks(?int $classroomId, Carbon $today, Carbon $oneMonthLater): array
    {
        $query = Student::query()
            ->where('is_active', true)
            ->whereHas('guardian')
            ->whereHas('supportPlans'); // 個別支援計画書が存在する生徒のみ

        if ($classroomId) {
            $query->whereHas('guardian', fn ($q) => $q->where('classroom_id', $classroomId));
        }

        $students = $query->with(['monitoringRecords' => function ($q) {
            $q->orderByDesc('monitoring_date');
        }])->get();

        $result = [];

        foreach ($students as $student) {
            $supportStartDate = $student->support_start_date;
            if (!$supportStartDate) {
                continue;
            }

            // かけはし期間からモニタリング期限を取得
            $currentPeriod = KakehashiPeriod::where('student_id', $student->id)
                ->where('is_active', true)
                ->where('submission_deadline', '<=', $oneMonthLater)
                ->orderByDesc('submission_deadline')
                ->first();

            if (!$currentPeriod) {
                continue;
            }

            $monitoringDeadline = $currentPeriod->submission_deadline;
            $daysLeft = (int) $today->diffInDays($monitoringDeadline, false);
            $isOverdue = $monitoringDeadline->lt($today);
            $nextPlanDeadline = $currentPeriod->end_date->copy()->addDay()->format('Y-m-d');

            // この期間のモニタリングが完了済みか確認
            $hasCompletedMonitoring = MonitoringRecord::where('student_id', $student->id)
                ->where('is_draft', false)
                ->where('guardian_confirmed', true)
                ->where('monitoring_date', '>=', $currentPeriod->start_date->copy()->subDays(30))
                ->exists();

            if ($hasCompletedMonitoring) {
                continue;
            }

            $monitorings = $student->monitoringRecords;
            $latestSubmitted = $monitorings->where('is_draft', false)->sortByDesc('id')->first();
            $latestSubmittedId = $latestSubmitted?->id;

            // モニタリングがない場合
            if ($monitorings->isEmpty()) {
                if (!$student->hide_initial_monitoring) {
                    if ($isOverdue) {
                        $statusCode = 'outdated';
                    } elseif ($daysLeft <= 7) {
                        $statusCode = 'urgent';
                    } else {
                        $statusCode = 'none';
                    }
                    $result[] = [
                        'student_id'           => $student->id,
                        'student_name'         => $student->student_name,
                        'support_start_date'   => $supportStartDate->format('Y-m-d'),
                        'monitoring_id'        => null,
                        'monitoring_deadline'   => $monitoringDeadline->format('Y-m-d'),
                        'days_since_monitoring' => null,
                        'status_code'          => $statusCode,
                        'has_newer'            => false,
                        'is_hidden'            => false,
                        'guardian_confirmed'    => false,
                        'next_plan_deadline'   => $nextPlanDeadline,
                        'days_left'            => $daysLeft,
                    ];
                }
                continue;
            }

            // 下書きがあるかチェック
            $draftMonitoring = $monitorings->where('is_draft', true)->where('is_hidden', false)->first();

            if ($draftMonitoring) {
                $hasNewer = $latestSubmittedId && $draftMonitoring->id != $latestSubmittedId;
                $daysSince = $draftMonitoring->monitoring_date ? (int) abs($today->diffInDays($draftMonitoring->monitoring_date, false)) : null;
                $result[] = [
                    'student_id'           => $student->id,
                    'student_name'         => $student->student_name,
                    'support_start_date'   => $supportStartDate->format('Y-m-d'),
                    'monitoring_id'        => $draftMonitoring->id,
                    'plan_id'              => $draftMonitoring->plan_id,
                    'monitoring_deadline'   => $monitoringDeadline->format('Y-m-d'),
                    'days_since_monitoring' => $daysSince,
                    'status_code'          => 'draft',
                    'has_newer'            => $hasNewer,
                    'is_hidden'            => false,
                    'guardian_confirmed'    => false,
                    'next_plan_deadline'   => $nextPlanDeadline,
                    'days_left'            => $daysLeft,
                ];
                continue;
            }

            // 提出済みで保護者確認が必要かチェック
            $needsGuardianConfirm = false;
            if ($latestSubmitted && !$latestSubmitted->is_hidden && !$latestSubmitted->guardian_confirmed) {
                $daysSince = $latestSubmitted->monitoring_date ? (int) abs($today->diffInDays($latestSubmitted->monitoring_date, false)) : null;
                $result[] = [
                    'student_id'           => $student->id,
                    'student_name'         => $student->student_name,
                    'support_start_date'   => $supportStartDate->format('Y-m-d'),
                    'monitoring_id'        => $latestSubmitted->id,
                    'plan_id'              => $latestSubmitted->plan_id,
                    'monitoring_deadline'   => $monitoringDeadline->format('Y-m-d'),
                    'days_since_monitoring' => $daysSince,
                    'status_code'          => 'needs_confirm',
                    'has_newer'            => false,
                    'is_hidden'            => false,
                    'guardian_confirmed'    => false,
                    'next_plan_deadline'   => $nextPlanDeadline,
                    'days_left'            => $daysLeft,
                ];
                $needsGuardianConfirm = true;
            }

            // 新しいモニタリングが必要かチェック
            if (!$needsGuardianConfirm) {
                // 最新の提出済みモニタリングが保護者確認済みかつ期間開始日以降なら完了
                $latestConfirmedInPeriod = false;
                if ($latestSubmitted && $latestSubmitted->guardian_confirmed) {
                    if ($latestSubmitted->monitoring_date >= $currentPeriod->start_date) {
                        $latestConfirmedInPeriod = true;
                    }
                }

                if (!$latestConfirmedInPeriod) {
                    if ($isOverdue) {
                        $statusCode = 'outdated';
                    } elseif ($daysLeft <= 7) {
                        $statusCode = 'urgent';
                    } else {
                        $statusCode = 'none';
                    }
                    $result[] = [
                        'student_id'           => $student->id,
                        'student_name'         => $student->student_name,
                        'support_start_date'   => $supportStartDate->format('Y-m-d'),
                        'monitoring_id'        => null,
                        'plan_id'              => null,
                        'monitoring_deadline'   => $monitoringDeadline->format('Y-m-d'),
                        'days_since_monitoring' => null,
                        'status_code'          => $statusCode,
                        'has_newer'            => false,
                        'is_hidden'            => false,
                        'guardian_confirmed'    => false,
                        'next_plan_deadline'   => $nextPlanDeadline,
                        'days_left'            => $daysLeft,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 保護者かけはしの未提出タスクを取得
     */
    private function getGuardianKakehashiTasks(?int $classroomId, Carbon $today, Carbon $oneMonthLater): array
    {
        $query = DB::table('students as s')
            ->join('users as u', 's.guardian_id', '=', 'u.id')
            ->join('kakehashi_periods as kp', 's.id', '=', 'kp.student_id')
            ->leftJoin('kakehashi_guardian as kg', function ($join) {
                $join->on('kp.id', '=', 'kg.period_id')
                    ->on('kg.student_id', '=', 's.id');
            })
            ->where('s.is_active', true)
            ->where('kp.is_active', true)
            ->where(function ($q) {
                $q->where('kg.is_submitted', false)
                    ->orWhereNull('kg.is_submitted');
            })
            ->where(function ($q) {
                $q->where('kg.is_hidden', false)
                    ->orWhereNull('kg.is_hidden');
            })
            ->where('kp.submission_deadline', '<=', $oneMonthLater)
            ->whereRaw('kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = true
                AND kp2.submission_deadline <= ?
            )', [$oneMonthLater]);

        if ($classroomId) {
            $query->where('u.classroom_id', $classroomId);
        }

        $rows = $query->select([
            's.id as student_id',
            's.student_name',
            'kp.id as period_id',
            'kp.period_name',
            'kp.submission_deadline',
            'kp.start_date',
            'kp.end_date',
            DB::raw("(kp.submission_deadline::date - '{$today->format('Y-m-d')}'::date) as days_left"),
            'kg.id as kakehashi_id',
            'kg.is_submitted',
        ])
            ->orderBy('kp.submission_deadline')
            ->orderBy('s.student_name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $daysLeft = (int) $row->days_left;

            if ($daysLeft < 0) {
                $statusCode = 'overdue';
            } elseif ($daysLeft <= 7) {
                $statusCode = 'urgent';
            } else {
                $statusCode = 'warning';
            }

            $result[] = [
                'student_id'          => $row->student_id,
                'student_name'        => $row->student_name,
                'period_id'           => $row->period_id,
                'period_name'         => $row->period_name,
                'submission_deadline' => $row->submission_deadline,
                'start_date'          => $row->start_date,
                'end_date'            => $row->end_date,
                'days_left'           => $daysLeft,
                'kakehashi_id'        => $row->kakehashi_id,
                'status_code'         => $statusCode,
            ];
        }

        return $result;
    }

    /**
     * スタッフかけはしの未作成タスクを取得
     */
    private function getStaffKakehashiTasks(?int $classroomId, Carbon $today, Carbon $oneMonthLater): array
    {
        $query = DB::table('students as s')
            ->join('users as u', 's.guardian_id', '=', 'u.id')
            ->join('kakehashi_periods as kp', 's.id', '=', 'kp.student_id')
            ->leftJoin('kakehashi_staff as ks', function ($join) {
                $join->on('kp.id', '=', 'ks.period_id')
                    ->on('ks.student_id', '=', 's.id');
            })
            ->where('s.is_active', true)
            ->where('kp.is_active', true)
            ->where(function ($q) {
                $q->where('ks.is_submitted', false)
                    ->orWhereNull('ks.is_submitted')
                    ->orWhere(function ($q2) {
                        $q2->where('ks.is_submitted', true)
                            ->where(function ($q3) {
                                $q3->where('ks.guardian_confirmed', false)
                                    ->orWhereNull('ks.guardian_confirmed');
                            });
                    });
            })
            ->where(function ($q) {
                $q->where('ks.is_hidden', false)
                    ->orWhereNull('ks.is_hidden');
            })
            ->where('kp.submission_deadline', '<=', $oneMonthLater)
            ->whereRaw('kp.submission_deadline = (
                SELECT MAX(kp2.submission_deadline)
                FROM kakehashi_periods kp2
                WHERE kp2.student_id = s.id AND kp2.is_active = true
                AND kp2.submission_deadline <= ?
            )', [$oneMonthLater]);

        if ($classroomId) {
            $query->where('u.classroom_id', $classroomId);
        }

        $rows = $query->select([
            's.id as student_id',
            's.student_name',
            'kp.id as period_id',
            'kp.period_name',
            'kp.submission_deadline',
            'kp.start_date',
            'kp.end_date',
            DB::raw("(kp.submission_deadline::date - '{$today->format('Y-m-d')}'::date) as days_left"),
            'ks.id as kakehashi_id',
            'ks.is_submitted',
            DB::raw('COALESCE(ks.is_hidden, false) as is_hidden'),
            DB::raw('COALESCE(ks.guardian_confirmed, false) as guardian_confirmed'),
        ])
            ->orderBy('kp.submission_deadline')
            ->orderBy('s.student_name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $isNotCreated = empty($row->kakehashi_id);
            $isDraft = !empty($row->kakehashi_id) && !$row->is_submitted;
            $isNeedsGuardianConfirm = !empty($row->kakehashi_id) && $row->is_submitted && !$row->guardian_confirmed;
            $daysLeft = (int) $row->days_left;

            if ($isNeedsGuardianConfirm) {
                $statusCode = 'needs_confirm';
            } elseif ($isDraft) {
                $statusCode = 'draft';
            } elseif ($daysLeft < 0) {
                $statusCode = 'overdue';
            } elseif ($daysLeft <= 7) {
                $statusCode = 'urgent';
            } else {
                $statusCode = 'warning';
            }

            $result[] = [
                'student_id'          => $row->student_id,
                'student_name'        => $row->student_name,
                'period_id'           => $row->period_id,
                'period_name'         => $row->period_name,
                'submission_deadline' => $row->submission_deadline,
                'start_date'          => $row->start_date,
                'end_date'            => $row->end_date,
                'days_left'           => $daysLeft,
                'kakehashi_id'        => $row->kakehashi_id,
                'is_submitted'        => (bool) $row->is_submitted,
                'guardian_confirmed'  => (bool) $row->guardian_confirmed,
                'status_code'         => $statusCode,
            ];
        }

        return $result;
    }

    /**
     * 次の個別支援計画書期限が1ヶ月以内かチェック
     * かけはし期間ベースの判定
     */
    private function isNextPlanDeadlineWithinOneMonth(int $studentId, $supportStartDate, ?string $latestPlanDate, Carbon $oneMonthLater): bool
    {
        // かけはし期間の提出期限が1ヶ月以内のものがあればtrue
        $exists = KakehashiPeriod::where('student_id', $studentId)
            ->where('is_active', true)
            ->where('submission_deadline', '<=', $oneMonthLater)
            ->exists();

        if ($exists) {
            return true;
        }

        // 従来のロジック（後方互換性のため維持）
        if (!$supportStartDate) {
            return false;
        }

        if (!$latestPlanDate) {
            $firstDeadline = Carbon::parse($supportStartDate)->subDay();
            return $firstDeadline->lte($oneMonthLater);
        }

        $nextDeadline = Carbon::parse($latestPlanDate)->addDays(180);
        return $nextDeadline->lte($oneMonthLater);
    }

    /**
     * 次の対象期間を計算する
     */
    private function getNextTargetPeriod($supportStartDate, int $existingPlanCount): array
    {
        if (!$supportStartDate) {
            return ['start' => null, 'end' => null, 'number' => null];
        }

        $start = Carbon::parse($supportStartDate);
        $planNumber = $existingPlanCount + 1;

        // 対象期間を計算（6ヶ月ごと）
        $periodStart = $start->copy()->addMonths($existingPlanCount * 6);
        $periodEnd = $periodStart->copy()->addMonths(6)->subDay();

        return [
            'start'  => $periodStart->format('Y-m-d'),
            'end'    => $periodEnd->format('Y-m-d'),
            'number' => $planNumber,
        ];
    }

    /**
     * タスクの非表示切り替え
     * Legacy: pending_tasks_toggle_hide.php の完全移植
     */
    public function toggleHide(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $id = (int) $request->input('id', 0);
        $studentId = (int) $request->input('student_id', 0);
        $action = $request->input('action', 'hide');

        $isHidden = $action === 'hide';

        try {
            if ($type === 'plan') {
                if (!$id) {
                    return response()->json(['success' => false, 'error' => 'Invalid parameters'], 422);
                }
                IndividualSupportPlan::where('id', $id)->update(['is_hidden' => $isHidden]);
            } elseif ($type === 'monitoring') {
                if (!$id) {
                    return response()->json(['success' => false, 'error' => 'Invalid parameters'], 422);
                }
                MonitoringRecord::where('id', $id)->update(['is_hidden' => $isHidden]);
            } elseif ($type === 'initial_monitoring') {
                if (!$studentId) {
                    return response()->json(['success' => false, 'error' => 'Invalid parameters'], 422);
                }
                Student::where('id', $studentId)->update(['hide_initial_monitoring' => $isHidden]);
            } elseif ($type === 'guardian_kakehashi') {
                $periodId = (int) $request->input('period_id', 0);
                if (!$periodId || !$studentId) {
                    return response()->json(['success' => false, 'error' => 'Invalid parameters'], 422);
                }
                KakehashiGuardian::updateOrCreate(
                    ['period_id' => $periodId, 'student_id' => $studentId],
                    ['is_hidden' => $isHidden]
                );
            } elseif ($type === 'staff_kakehashi') {
                $periodId = (int) $request->input('period_id', 0);
                if (!$periodId || !$studentId) {
                    return response()->json(['success' => false, 'error' => 'Invalid parameters'], 422);
                }
                KakehashiStaff::updateOrCreate(
                    ['period_id' => $periodId, 'student_id' => $studentId],
                    ['is_hidden' => $isHidden]
                );
            } else {
                return response()->json(['success' => false, 'error' => 'Invalid type'], 422);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
