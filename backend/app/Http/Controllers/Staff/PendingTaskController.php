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
        $accessibleIds = $user->classroom_id ? $user->accessibleClassroomIds() : null;
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
        $planTasks = $this->getPlanTasks($accessibleIds, $today, $oneMonthLater);

        // 2. モニタリングタスク
        $monitoringTasks = $this->getMonitoringTasks($accessibleIds, $today, $oneMonthLater);

        // 3. 保護者かけはしタスク
        $guardianKakehashiTasks = $this->getGuardianKakehashiTasks($accessibleIds, $today, $oneMonthLater);

        // 4. スタッフかけはしタスク
        $staffKakehashiTasks = $this->getStaffKakehashiTasks($accessibleIds, $today, $oneMonthLater);

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
    private function getPlanTasks(?array $accessibleIds, Carbon $today, Carbon $oneMonthLater): array
    {
        $query = Student::query()
            ->where('is_active', true)
            ->whereHas('guardian');

        if ($accessibleIds) {
            $query->whereHas('guardian', fn ($q) => $q->whereIn('classroom_id', $accessibleIds));
        }

        $students = $query->with(['supportPlans' => function ($q) {
            $q->orderByDesc('created_date');
        }])->get();

        $result = [];

        foreach ($students as $student) {
            $plans = $student->supportPlans;
            $supportStartDate = $student->support_start_date;

            // 非表示の計画のみ → ユーザーが意図的に非表示にしたのでスキップ
            $hasHiddenPlans = $plans->where('is_hidden', true)->isNotEmpty();
            $visiblePlans = $plans->where('is_hidden', false);

            // 計画書がない場合 → 空の下書きを自動生成（ただし非表示にされた計画がある場合は除く）
            if ($visiblePlans->isEmpty()) {
                if ($hasHiddenPlans) {
                    // ユーザーが非表示にした → 再生成しない
                    continue;
                }
                if ($this->isNextPlanDeadlineWithinOneMonth($student->id, $supportStartDate, null, $oneMonthLater)) {
                    $nextPeriod = $this->getNextTargetPeriod($supportStartDate, 0);
                    $deadlineDate = $nextPeriod['start'] ?? now()->format('Y-m-d');

                    // 同じ期間内の計画が既にあれば自動生成しない
                    $existsForPeriod = IndividualSupportPlan::where('student_id', $student->id)
                        ->where('is_hidden', false)
                        ->exists();
                    if ($existsForPeriod) {
                        continue;
                    }

                    // 空の支援計画を下書きで自動作成
                    $autoPlan = IndividualSupportPlan::create([
                        'student_id'    => $student->id,
                        'classroom_id'  => $student->classroom_id,
                        'student_name'  => $student->student_name,
                        'created_date'  => $deadlineDate,
                        'status'        => 'draft',
                        'is_draft'      => true,
                        'is_official'   => false,
                        'is_hidden'     => false,
                    ]);

                    // デフォルトの7行詳細を作成
                    $defaultDetails = [
                        ['domain' => '健康・生活', 'category' => '本人支援', 'sub_category' => '生活習慣（健康・生活）'],
                        ['domain' => '言語・コミュニケーション', 'category' => '本人支援', 'sub_category' => 'コミュニケーション（言語・コミュニケーション）'],
                        ['domain' => '人間関係・社会性', 'category' => '本人支援', 'sub_category' => '対人関係（人間関係・社会性）'],
                        ['domain' => '運動・感覚', 'category' => '本人支援', 'sub_category' => '運動機能（運動・感覚）'],
                        ['domain' => '認知・行動', 'category' => '本人支援', 'sub_category' => '学習面（認知・行動）'],
                        ['domain' => '家族支援', 'category' => '家族支援', 'sub_category' => '保護者支援'],
                        ['domain' => '地域支援', 'category' => '地域支援', 'sub_category' => '地域連携'],
                    ];
                    foreach ($defaultDetails as $i => $detail) {
                        $autoPlan->details()->create(array_merge($detail, ['sort_order' => $i]));
                    }

                    $result[] = [
                        'student_id'          => $student->id,
                        'student_name'        => $student->student_name,
                        'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                        'plan_id'             => $autoPlan->id,
                        'latest_plan_date'    => $deadlineDate,
                        'days_since_plan'     => null,
                        'status_code'         => 'draft',
                        'has_newer'           => false,
                        'is_hidden'           => false,
                        'target_period_start' => $nextPeriod['start'],
                        'target_period_end'   => $nextPeriod['end'],
                        'plan_number'         => $nextPeriod['number'],
                    ];
                }
                continue;
            }

            // status ベースで計画を分類（is_draft フラグは信頼できないため不使用）
            // published + is_official: 完了済み → タスク不要（次期間チェックのみ）
            // published + !is_official: 確認待ち
            // submitted: 提出済み → 確認待ち
            // draft: 下書き → 作業が必要

            $completedPlan = $visiblePlans
                ->where('is_official', true)
                ->whereIn('status', ['published', 'submitted'])
                ->sortByDesc('id')
                ->first();

            $submittedPlan = $visiblePlans
                ->where('is_official', false)
                ->whereIn('status', ['submitted', 'published'])
                ->sortByDesc('id')
                ->first();

            $draftPlan = $visiblePlans
                ->where('status', 'draft')
                ->sortByDesc('id')
                ->first();

            // 完了済み計画より古い下書き・提出済みは無視
            if ($completedPlan) {
                if ($draftPlan && $draftPlan->id <= $completedPlan->id) {
                    $draftPlan = null;
                }
                if ($submittedPlan && $submittedPlan->id <= $completedPlan->id) {
                    $submittedPlan = null;
                }
            }

            // 下書きがある場合
            if ($draftPlan) {
                $period = $this->getPeriodFromPlanDate($supportStartDate, $draftPlan->created_date);
                $periodEnd = $period['end'] ? Carbon::parse($period['end']) : null;
                $daysLeft = $periodEnd ? (int) $today->diffInDays($periodEnd, false) : null;
                $daysSincePlan = ($daysLeft !== null && $daysLeft < 0) ? abs($daysLeft) : null;

                $result[] = [
                    'student_id'          => $student->id,
                    'student_name'        => $student->student_name,
                    'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                    'plan_id'             => $draftPlan->id,
                    'latest_plan_date'    => $draftPlan->created_date?->format('Y-m-d'),
                    'days_since_plan'     => $daysSincePlan,
                    'status_code'         => $daysLeft !== null && $daysLeft < 0 ? 'outdated' : 'draft',
                    'has_newer'           => (bool) $completedPlan,
                    'is_hidden'           => false,
                    'target_period_start' => $period['start'],
                    'target_period_end'   => $period['end'],
                    'plan_number'         => $period['number'],
                ];
                continue;
            }

            // 提出済みで保護者確認が必要かチェック（完了済みでない提出済み計画）
            $needsGuardianConfirm = false;
            if ($submittedPlan && !$submittedPlan->is_hidden) {
                $daysSince = $submittedPlan->created_date ? (int) abs($today->diffInDays($submittedPlan->created_date, false)) : null;
                $completedCount = $visiblePlans->where('is_official', true)->count();
                $nextPeriod = $this->getNextTargetPeriod($supportStartDate, $completedCount);

                $result[] = [
                    'student_id'          => $student->id,
                    'student_name'        => $student->student_name,
                    'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                    'plan_id'             => $submittedPlan->id,
                    'latest_plan_date'    => $submittedPlan->created_date?->format('Y-m-d'),
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

            // 完了済み計画があり、次期間が必要かチェック
            if (!$needsGuardianConfirm && $completedPlan && !$completedPlan->is_hidden) {
                // 最新完了済み計画の期間を逆算し、次の期間を計算
                $currentPeriod = $this->getPeriodFromPlanDate($supportStartDate, $completedPlan->created_date);
                $currentPeriodEnd = $currentPeriod['end'] ? Carbon::parse($currentPeriod['end']) : null;

                $needsNewPlan = false;

                // 現在の期間の終了日を過ぎていて、次期間開始が1ヶ月以内
                if ($currentPeriodEnd && $currentPeriodEnd->lt($today)) {
                    $nextStart = $currentPeriodEnd->copy()->addDay();
                    if ($nextStart->lte($oneMonthLater)) {
                        $needsNewPlan = true;
                    }
                }

                // 方法2: かけはし期間ベースチェック
                if (!$needsNewPlan) {
                    $hasNewPeriod = KakehashiPeriod::where('student_id', $student->id)
                        ->where('is_active', true)
                        ->where('start_date', '>', $completedPlan->created_date)
                        ->where('submission_deadline', '<=', $oneMonthLater)
                        ->exists();
                    if ($hasNewPeriod) {
                        $needsNewPlan = true;
                    }
                }

                if ($needsNewPlan) {
                    // 最新計画の次の期間を計算
                    $nextPeriodNumber = ($currentPeriod['number'] ?? 0) + 1;
                    $nextPeriodStart = $currentPeriodEnd ? $currentPeriodEnd->copy()->addDay() : null;
                    $nextPeriodEnd = $nextPeriodStart ? $nextPeriodStart->copy()->addMonths(6)->subDay() : null;
                    $nextPeriod = [
                        'start'  => $nextPeriodStart?->format('Y-m-d'),
                        'end'    => $nextPeriodEnd?->format('Y-m-d'),
                        'number' => $nextPeriodNumber,
                    ];
                    $daysLeft = $nextPeriodEnd ? (int) $today->diffInDays($nextPeriodEnd, false) : null;
                    $deadlineDate = $nextPeriodStart?->format('Y-m-d') ?? now()->format('Y-m-d');

                    // 同じ期間内の計画が既にあるかチェック（日付ずれの重複防止）
                    $existsForPeriod = IndividualSupportPlan::where('student_id', $student->id)
                        ->where('is_hidden', false)
                        ->where(function ($q) use ($deadlineDate, $nextPeriodStart, $nextPeriodEnd) {
                            $q->where('created_date', $deadlineDate);
                            if ($nextPeriodStart && $nextPeriodEnd) {
                                $q->orWhereBetween('created_date', [
                                    $nextPeriodStart->copy()->subDay()->format('Y-m-d'),
                                    $nextPeriodEnd->format('Y-m-d'),
                                ]);
                            }
                        })
                        ->exists();

                    if (!$existsForPeriod) {
                        // 次の期間の下書きを自動生成
                        $autoPlan = IndividualSupportPlan::create([
                            'student_id'    => $student->id,
                            'classroom_id'  => $student->classroom_id,
                            'student_name'  => $student->student_name,
                            'created_date'  => $deadlineDate,
                            'status'        => 'draft',
                            'is_draft'      => true,
                            'is_official'   => false,
                            'is_hidden'     => false,
                            'source_monitoring_id' => $student->monitoringRecords()->where('plan_id', $completedPlan->id)->value('id'),
                        ]);

                        $defaultDetails = [
                            ['domain' => '健康・生活', 'category' => '本人支援', 'sub_category' => '生活習慣（健康・生活）'],
                            ['domain' => '言語・コミュニケーション', 'category' => '本人支援', 'sub_category' => 'コミュニケーション（言語・コミュニケーション）'],
                            ['domain' => '人間関係・社会性', 'category' => '本人支援', 'sub_category' => '対人関係（人間関係・社会性）'],
                            ['domain' => '運動・感覚', 'category' => '本人支援', 'sub_category' => '運動機能（運動・感覚）'],
                            ['domain' => '認知・行動', 'category' => '本人支援', 'sub_category' => '学習面（認知・行動）'],
                            ['domain' => '家族支援', 'category' => '家族支援', 'sub_category' => '保護者支援'],
                            ['domain' => '地域支援', 'category' => '地域支援', 'sub_category' => '地域連携'],
                        ];
                        foreach ($defaultDetails as $i => $detail) {
                            $autoPlan->details()->create(array_merge($detail, ['sort_order' => $i]));
                        }

                        $result[] = [
                            'student_id'          => $student->id,
                            'student_name'        => $student->student_name,
                            'support_start_date'  => $supportStartDate?->format('Y-m-d'),
                            'plan_id'             => $autoPlan->id,
                            'latest_plan_date'    => $deadlineDate,
                            'days_since_plan'     => null,
                            'status_code'         => 'draft',
                            'has_newer'           => false,
                            'is_hidden'           => false,
                            'target_period_start' => $nextPeriod['start'],
                            'target_period_end'   => $nextPeriod['end'],
                            'plan_number'         => $nextPeriod['number'],
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * モニタリングの未作成タスクを取得
     * ルール: 初回=利用開始日+5ヶ月、以降=6ヶ月ごと
     * 前提: 提出済み個別支援計画がある生徒のみ対象
     */
    private function getMonitoringTasks(?array $accessibleIds, Carbon $today, Carbon $oneMonthLater): array
    {
        // 提出済み（非下書き）の個別支援計画がある生徒のみ対象
        $query = Student::query()
            ->where('is_active', true)
            ->whereHas('guardian')
            ->whereHas('supportPlans', fn ($q) => $q->where('is_draft', false));

        if ($accessibleIds) {
            $query->whereHas('guardian', fn ($q) => $q->whereIn('classroom_id', $accessibleIds));
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

            $start = Carbon::parse($supportStartDate);

            // 完了済みモニタリング数（提出済み＋保護者確認済み）
            $completedCount = MonitoringRecord::where('student_id', $student->id)
                ->where('is_draft', false)
                ->where('guardian_confirmed', true)
                ->count();

            // 次のモニタリング期限: 初回=開始+5ヶ月、以降=+6ヶ月ごと
            $monitoringDeadline = $start->copy()->addMonths(5 + $completedCount * 6);

            // 期限が1ヶ月以上先ならスキップ
            if ($monitoringDeadline->gt($oneMonthLater)) {
                continue;
            }

            $daysLeft = (int) $today->diffInDays($monitoringDeadline, false);
            $isOverdue = $monitoringDeadline->lt($today);
            $nextPlanDeadline = $monitoringDeadline->copy()->addMonth()->format('Y-m-d');

            $monitorings = $student->monitoringRecords;
            $latestSubmitted = $monitorings->where('is_draft', false)->sortByDesc('id')->first();
            $latestSubmittedId = $latestSubmitted?->id;

            // 下書きモニタリングがあるか
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

            // 提出済みだが保護者未確認
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
                continue;
            }

            // モニタリング未作成 or 新規作成が必要
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

        return $result;
    }

    /**
     * 保護者かけはしの未提出タスクを取得
     */
    private function getGuardianKakehashiTasks(?array $accessibleIds, Carbon $today, Carbon $oneMonthLater): array
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

        if ($accessibleIds) {
            $query->whereIn('u.classroom_id', $accessibleIds);
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
    private function getStaffKakehashiTasks(?array $accessibleIds, Carbon $today, Carbon $oneMonthLater): array
    {
        $query = DB::table('students as s')
            ->join('users as u', 's.guardian_id', '=', 'u.id')
            ->join('kakehashi_periods as kp', 's.id', '=', 'kp.student_id')
            ->leftJoin('kakehashi_staff as ks', function ($join) {
                $join->on('kp.id', '=', 'ks.period_id')
                    ->on('ks.student_id', '=', 's.id');
            })
            ->leftJoin('kakehashi_guardian as kg', function ($join) {
                $join->on('kp.id', '=', 'kg.period_id')
                    ->on('kg.student_id', '=', 's.id');
            })
            ->where('s.is_active', true)
            ->where('kp.is_active', true)
            ->where(function ($q) {
                // スタッフ未提出 OR (スタッフ提出済みで保護者が未提出)
                $q->where('ks.is_submitted', false)
                    ->orWhereNull('ks.is_submitted')
                    ->orWhere(function ($q2) {
                        $q2->where('ks.is_submitted', true)
                            ->where(function ($q3) {
                                $q3->where('kg.is_submitted', false)
                                    ->orWhereNull('kg.is_submitted');
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

        if ($accessibleIds) {
            $query->whereIn('u.classroom_id', $accessibleIds);
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
            DB::raw('COALESCE(kg.is_submitted, false) as guardian_submitted'),
        ])
            ->orderBy('kp.submission_deadline')
            ->orderBy('s.student_name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $isNotCreated = empty($row->kakehashi_id);
            $isDraft = !empty($row->kakehashi_id) && !$row->is_submitted;
            $isNeedsGuardianConfirm = !empty($row->kakehashi_id) && $row->is_submitted && !$row->guardian_submitted;
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
                'guardian_confirmed'  => (bool) $row->guardian_submitted,
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
     * 計画の作成日からどの期間に属するか逆算する
     */
    private function getPeriodFromPlanDate($supportStartDate, $planDate): array
    {
        if (!$supportStartDate || !$planDate) {
            return ['start' => null, 'end' => null, 'number' => null];
        }

        $start = Carbon::parse($supportStartDate);
        $plan = Carbon::parse($planDate);

        // 支援開始日から何ヶ月経過しているかで期間番号を算出
        $monthsDiff = $start->diffInMonths($plan);
        $periodIndex = (int) floor($monthsDiff / 6);

        $periodStart = $start->copy()->addMonths($periodIndex * 6);
        $periodEnd = $periodStart->copy()->addMonths(6)->subDay();

        return [
            'start'  => $periodStart->format('Y-m-d'),
            'end'    => $periodEnd->format('Y-m-d'),
            'number' => $periodIndex + 1,
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
