<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\MonitoringRecord;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 期限通知ジョブ
 *
 * 毎朝9時に実行し、以下の期限通知を送信する:
 * - かけはし（保護者）提出期限リマインダー（7日前・当日）
 * - かけはし（スタッフ）提出期限リマインダー（7日前・当日）
 * - モニタリング作成期限リマインダー（1ヶ月前から当日まで）
 * - 個別支援計画書更新期限リマインダー（1ヶ月前から当日まで）
 */
class SendDeadlineNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /** @var array<string, int> */
    protected array $results = [
        'kakehashi_guardian' => 0,
        'kakehashi_staff' => 0,
        'monitoring' => 0,
        'plan' => 0,
        'errors' => 0,
    ];

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::info('SendDeadlineNotificationsJob: starting deadline notification check');

        $this->sendKakehashiGuardianReminders();
        $this->sendKakehashiStaffReminders();
        $this->sendMonitoringReminders();
        $this->sendPlanReminders();

        Log::info('SendDeadlineNotificationsJob: completed', $this->results);
    }

    /**
     * Get the results (for testing).
     */
    public function getResults(): array
    {
        return $this->results;
    }

    // =========================================================================
    // Kakehashi Guardian Reminders
    // =========================================================================

    /**
     * 保護者かけはし期限リマインダー
     * 提出期限が本日 または 7日後で未提出の保護者に通知
     */
    protected function sendKakehashiGuardianReminders(): void
    {
        try {
            $today = Carbon::today();
            $sevenDaysLater = $today->copy()->addDays(7);

            $periods = KakehashiPeriod::query()
                ->where('is_active', true)
                ->where(function ($q) use ($today, $sevenDaysLater) {
                    $q->whereDate('submission_deadline', $today)
                      ->orWhereDate('submission_deadline', $sevenDaysLater);
                })
                ->with(['student' => function ($q) {
                    $q->where('status', 'active');
                }, 'student.guardian', 'guardianEntries'])
                ->get();

            foreach ($periods as $period) {
                $student = $period->student;
                if (! $student || $student->status !== 'active') {
                    continue;
                }

                $guardian = $student->guardian;
                if (! $guardian || ! $guardian->is_active || empty($guardian->email)) {
                    continue;
                }

                // Check if guardian already submitted
                $submitted = $period->guardianEntries
                    ->where('student_id', $student->id)
                    ->where('is_submitted', true)
                    ->isNotEmpty();

                if ($submitted) {
                    continue;
                }

                $notificationKey = "kakehashi_guardian_{$period->id}_{$guardian->id}";

                if ($this->isAlreadySentToday('deadline_reminder', 'kakehashi_guardian', $notificationKey)) {
                    continue;
                }

                $daysLeft = (int) $today->diffInDays($period->submission_deadline, false);
                $urgency = $daysLeft <= 0
                    ? '本日が提出期限です'
                    : "提出期限まであと{$daysLeft}日です";

                $this->createNotification(
                    $guardian,
                    'deadline_reminder',
                    "【かけはし提出期限】{$student->student_name}",
                    "{$student->student_name}さんのかけはし（保護者）の提出期限が近づいています。{$urgency}（期限: {$period->submission_deadline->format('Y年n月j日')}）",
                    [
                        'type' => 'kakehashi_guardian',
                        'period_id' => $period->id,
                        'student_id' => $student->id,
                        'deadline' => $period->submission_deadline->toDateString(),
                        'days_left' => $daysLeft,
                    ]
                );

                $this->logNotificationSent(
                    'deadline_reminder',
                    'kakehashi_guardian',
                    $notificationKey,
                    "保護者かけはし期限通知: {$student->student_name} / 期限: {$period->submission_deadline->toDateString()}"
                );

                $this->results['kakehashi_guardian']++;
            }
        } catch (\Throwable $e) {
            Log::error('SendDeadlineNotificationsJob: kakehashi guardian error', [
                'error' => $e->getMessage(),
            ]);
            $this->results['errors']++;
        }
    }

    // =========================================================================
    // Kakehashi Staff Reminders
    // =========================================================================

    /**
     * スタッフかけはし期限リマインダー
     * 提出期限が本日 または 7日後で未提出のスタッフに通知
     */
    protected function sendKakehashiStaffReminders(): void
    {
        try {
            $today = Carbon::today();
            $sevenDaysLater = $today->copy()->addDays(7);

            $periods = KakehashiPeriod::query()
                ->where('is_active', true)
                ->where(function ($q) use ($today, $sevenDaysLater) {
                    $q->whereDate('submission_deadline', $today)
                      ->orWhereDate('submission_deadline', $sevenDaysLater);
                })
                ->with(['student' => function ($q) {
                    $q->where('status', 'active');
                }, 'staffEntries'])
                ->get();

            foreach ($periods as $period) {
                $student = $period->student;
                if (! $student || $student->status !== 'active') {
                    continue;
                }

                // Check if staff already submitted for this student
                $submitted = $period->staffEntries
                    ->where('student_id', $student->id)
                    ->where('is_submitted', true)
                    ->isNotEmpty();

                if ($submitted) {
                    continue;
                }

                // Find staff in the student's classroom
                $staffMembers = User::query()
                    ->where('classroom_id', $student->classroom_id)
                    ->whereIn('user_type', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get();

                $daysLeft = (int) $today->diffInDays($period->submission_deadline, false);
                $urgency = $daysLeft <= 0
                    ? '本日が提出期限です'
                    : "提出期限まであと{$daysLeft}日です";

                foreach ($staffMembers as $staff) {
                    $notificationKey = "kakehashi_staff_{$period->id}_{$staff->id}";

                    if ($this->isAlreadySentToday('deadline_reminder', 'kakehashi_staff', $notificationKey)) {
                        continue;
                    }

                    $this->createNotification(
                        $staff,
                        'deadline_reminder',
                        "【かけはし提出期限】{$student->student_name}",
                        "{$student->student_name}さんのかけはし（スタッフ）の提出期限が近づいています。{$urgency}（期限: {$period->submission_deadline->format('Y年n月j日')}）",
                        [
                            'type' => 'kakehashi_staff',
                            'period_id' => $period->id,
                            'student_id' => $student->id,
                            'deadline' => $period->submission_deadline->toDateString(),
                            'days_left' => $daysLeft,
                        ]
                    );

                    $this->logNotificationSent(
                        'deadline_reminder',
                        'kakehashi_staff',
                        $notificationKey,
                        "スタッフかけはし期限通知: {$student->student_name} / 期限: {$period->submission_deadline->toDateString()} / 送信先: {$staff->full_name}"
                    );

                    $this->results['kakehashi_staff']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendDeadlineNotificationsJob: kakehashi staff error', [
                'error' => $e->getMessage(),
            ]);
            $this->results['errors']++;
        }
    }

    // =========================================================================
    // Monitoring Reminders
    // =========================================================================

    /**
     * モニタリング期限リマインダー
     * かけはし期間の提出期限が1ヶ月以内で、モニタリングが未完了の生徒の教室スタッフに通知
     */
    protected function sendMonitoringReminders(): void
    {
        try {
            $today = Carbon::today();
            $oneMonthLater = $today->copy()->addMonth();

            // Find students with upcoming kakehashi deadlines who need monitoring
            $periods = KakehashiPeriod::query()
                ->where('is_active', true)
                ->whereDate('submission_deadline', '>=', $today)
                ->whereDate('submission_deadline', '<=', $oneMonthLater)
                ->with(['student' => function ($q) {
                    $q->where('status', 'active');
                }])
                ->get()
                // Only keep the latest deadline per student
                ->groupBy('student_id')
                ->map(fn ($group) => $group->sortByDesc('submission_deadline')->first());

            foreach ($periods as $period) {
                $student = $period->student;
                if (! $student || $student->status !== 'active') {
                    continue;
                }

                // Check if student has any support plans
                $hasPlan = IndividualSupportPlan::where('student_id', $student->id)->exists();
                if (! $hasPlan) {
                    continue;
                }

                // Check if a confirmed monitoring exists near this period
                $hasConfirmedMonitoring = MonitoringRecord::query()
                    ->where('student_id', $student->id)
                    ->where('is_draft', false)
                    ->where('guardian_confirmed', true)
                    ->whereDate('monitoring_date', '>=', $period->start_date?->copy()->subDays(30))
                    ->exists();

                if ($hasConfirmedMonitoring) {
                    continue;
                }

                $daysLeft = (int) $today->diffInDays($period->submission_deadline, false);
                $urgency = $daysLeft <= 0
                    ? '期限を過ぎています'
                    : "期限まであと{$daysLeft}日です";

                // Notify classroom staff
                $staffMembers = User::query()
                    ->where('classroom_id', $student->classroom_id)
                    ->whereIn('user_type', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get();

                foreach ($staffMembers as $staff) {
                    $notificationKey = "monitoring_{$student->id}_{$staff->id}";

                    if ($this->isAlreadySentToday('deadline_reminder', 'monitoring', $notificationKey)) {
                        continue;
                    }

                    $this->createNotification(
                        $staff,
                        'deadline_reminder',
                        "【モニタリング作成期限】{$student->student_name}",
                        "{$student->student_name}さんのモニタリングの作成期限が近づいています。{$urgency}（期限: {$period->submission_deadline->format('Y年n月j日')}）",
                        [
                            'type' => 'monitoring',
                            'student_id' => $student->id,
                            'deadline' => $period->submission_deadline->toDateString(),
                            'days_left' => $daysLeft,
                        ]
                    );

                    $this->logNotificationSent(
                        'deadline_reminder',
                        'monitoring',
                        $notificationKey,
                        "モニタリング期限通知: {$student->student_name} / 期限: {$period->submission_deadline->toDateString()} / 送信先: {$staff->full_name}"
                    );

                    $this->results['monitoring']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendDeadlineNotificationsJob: monitoring error', [
                'error' => $e->getMessage(),
            ]);
            $this->results['errors']++;
        }
    }

    // =========================================================================
    // Plan Reminders
    // =========================================================================

    /**
     * 個別支援計画書更新期限リマインダー
     * かけはし期間の提出期限が1ヶ月以内で、新しい計画書が必要な生徒の教室スタッフに通知
     */
    protected function sendPlanReminders(): void
    {
        try {
            $today = Carbon::today();
            $oneMonthLater = $today->copy()->addMonth();

            $periods = KakehashiPeriod::query()
                ->where('is_active', true)
                ->whereDate('submission_deadline', '>=', $today)
                ->whereDate('submission_deadline', '<=', $oneMonthLater)
                ->with(['student' => function ($q) {
                    $q->where('status', 'active');
                }])
                ->get()
                ->groupBy('student_id')
                ->map(fn ($group) => $group->sortByDesc('submission_deadline')->first());

            foreach ($periods as $period) {
                $student = $period->student;
                if (! $student || $student->status !== 'active') {
                    continue;
                }

                // Check if a finalized plan exists after the period start date
                $latestPlanDate = IndividualSupportPlan::query()
                    ->where('student_id', $student->id)
                    ->where('is_draft', false)
                    ->max('created_date');

                // If latest plan was created after period start, no reminder needed
                if ($latestPlanDate && $period->start_date && $latestPlanDate >= $period->start_date->toDateString()) {
                    continue;
                }

                $daysLeft = (int) $today->diffInDays($period->submission_deadline, false);
                $urgency = $daysLeft <= 0
                    ? '期限を過ぎています'
                    : "期限まであと{$daysLeft}日です";

                $staffMembers = User::query()
                    ->where('classroom_id', $student->classroom_id)
                    ->whereIn('user_type', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get();

                foreach ($staffMembers as $staff) {
                    $notificationKey = "plan_{$student->id}_{$staff->id}";

                    if ($this->isAlreadySentToday('deadline_reminder', 'plan', $notificationKey)) {
                        continue;
                    }

                    $this->createNotification(
                        $staff,
                        'deadline_reminder',
                        "【個別支援計画書の更新期限】{$student->student_name}",
                        "{$student->student_name}さんの個別支援計画書の更新期限が近づいています。{$urgency}（期限: {$period->submission_deadline->format('Y年n月j日')}）",
                        [
                            'type' => 'plan',
                            'student_id' => $student->id,
                            'deadline' => $period->submission_deadline->toDateString(),
                            'days_left' => $daysLeft,
                        ]
                    );

                    $this->logNotificationSent(
                        'deadline_reminder',
                        'plan',
                        $notificationKey,
                        "個別支援計画書期限通知: {$student->student_name} / 期限: {$period->submission_deadline->toDateString()} / 送信先: {$staff->full_name}"
                    );

                    $this->results['plan']++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendDeadlineNotificationsJob: plan error', [
                'error' => $e->getMessage(),
            ]);
            $this->results['errors']++;
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Check if a notification was already sent today by looking at audit_logs.
     * Uses action + target_table + notification_key stored in new_values JSON.
     */
    protected function isAlreadySentToday(string $action, string $targetTable, string $notificationKey): bool
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('target_table', $targetTable)
            ->whereJsonContains('new_values->notification_key', $notificationKey)
            ->whereDate('created_at', Carbon::today())
            ->exists();
    }

    /**
     * Log that a notification was sent via audit_logs.
     */
    protected function logNotificationSent(string $action, string $targetTable, string $notificationKey, string $description): void
    {
        try {
            AuditLog::create([
                'user_id' => null,
                'action' => $action,
                'target_table' => $targetTable,
                'target_id' => null,
                'old_values' => null,
                'new_values' => [
                    'notification_key' => $notificationKey,
                    'description' => $description,
                ],
                'ip_address' => '127.0.0.1',
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendDeadlineNotificationsJob: failed to log notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a notification record in the notifications table.
     */
    protected function createNotification(User $user, string $type, string $title, string $body, array $data = []): void
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('SendDeadlineNotificationsJob: notification created', [
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
            ]);
        } catch (\Throwable $e) {
            Log::error('SendDeadlineNotificationsJob: failed to create notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendDeadlineNotificationsJob failed', [
            'error' => $exception->getMessage(),
            'results' => $this->results,
        ]);
    }
}
