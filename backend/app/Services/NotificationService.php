<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Jobs\SendNotificationEmailJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification for a specific user and broadcast via WebSocket.
     *
     * @param  User  $user
     * @param  string  $type  Notification type (e.g., 'chat', 'plan_review', 'meeting', 'absence')
     * @param  string  $title
     * @param  string  $body
     * @param  array  $data  Additional structured data
     * @return Notification
     */
    public function notify(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'is_read' => false,
        ]);

        // Broadcast to the user's private WebSocket channel
        broadcast(new NotificationCreated($notification))->toOthers();

        return $notification;
    }

    /**
     * Send notifications to all users of a specific type within a classroom.
     *
     * @param  int  $classroomId
     * @param  string  $userType  'staff', 'guardian', or 'admin'
     * @param  string  $type
     * @param  string  $title
     * @param  string  $body
     * @param  array  $data
     * @return int  Number of notifications sent
     */
    public function notifyClassroom(
        int $classroomId,
        string $userType,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): int {
        $users = User::where('classroom_id', $classroomId)
            ->where('user_type', $userType)
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $this->notify($user, $type, $title, $body, $data);
            $count++;
        }

        Log::info('Classroom notifications sent', [
            'classroom_id' => $classroomId,
            'user_type' => $userType,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Send an email notification to a user using a Blade template (dispatched as a queued job).
     *
     * @param  User    $user
     * @param  string  $subject       Email subject line
     * @param  string  $template      Blade template name (e.g. 'chat-notification')
     * @param  array   $data          Template variables
     * @param  string  $fallbackBody  Plain-text fallback
     * @return void
     */
    public function sendEmailNotification(
        User $user,
        string $subject,
        string $template = '',
        array $data = [],
        string $fallbackBody = '',
    ): void {
        if (empty($user->email)) {
            Log::warning('Cannot send email: user has no email address', ['user_id' => $user->id]);

            return;
        }

        SendNotificationEmailJob::dispatch($user, $subject, $template, $data, $fallbackBody);
    }

    /**
     * Send a chat message notification email.
     */
    public function sendChatEmailNotification(
        User $recipient,
        string $senderName,
        string $messagePreview,
        string $chatUrl,
        string $facilityName = '',
    ): void {
        $subject = "【新着メッセージ】チャットに新しいメッセージがあります";

        $this->sendEmailNotification($recipient, $subject, 'chat-notification', [
            'recipientName' => $recipient->full_name,
            'senderName' => $senderName,
            'messagePreview' => $messagePreview,
            'chatUrl' => $chatUrl,
            'facilityName' => $facilityName ?: config('app.name', 'きづり'),
        ]);
    }

    /**
     * Send a support plan notification email.
     */
    public function sendSupportPlanEmailNotification(
        User $recipient,
        string $studentName,
        string $planPeriod,
        string $actionType,
        string $planUrl,
        string $facilityName = '',
    ): void {
        $actionLabels = [
            'review' => '確認依頼',
            'confirmation' => '承認依頼',
            'updated' => '更新通知',
            'created' => '新規作成',
        ];
        $actionLabel = $actionLabels[$actionType] ?? $actionType;

        $subject = "【個別支援計画】{$studentName}さんの支援計画 - {$actionLabel}";

        $this->sendEmailNotification($recipient, $subject, 'support-plan-notification', [
            'recipientName' => $recipient->full_name,
            'studentName' => $studentName,
            'planPeriod' => $planPeriod,
            'actionType' => $actionType,
            'planUrl' => $planUrl,
            'facilityName' => $facilityName ?: config('app.name', 'きづり'),
        ]);
    }

    /**
     * Send a kakehashi deadline reminder email.
     */
    public function sendKakehashiReminderEmail(
        User $recipient,
        string $studentName,
        string $deadline,
        int $daysRemaining,
        string $kakehashiUrl,
        string $facilityName = '',
    ): void {
        $subject = "【かけはし】{$studentName}さんのかけはし提出期限が近づいています";

        $this->sendEmailNotification($recipient, $subject, 'kakehashi-reminder', [
            'recipientName' => $recipient->full_name,
            'studentName' => $studentName,
            'deadline' => $deadline,
            'daysRemaining' => $daysRemaining,
            'kakehashiUrl' => $kakehashiUrl,
            'facilityName' => $facilityName ?: config('app.name', 'きづり'),
        ]);
    }

    /**
     * Send a general notification email with optional CTA button.
     */
    public function sendGeneralEmailNotification(
        User $recipient,
        string $title,
        string $body,
        string $actionUrl = '',
        string $actionLabel = '',
        string $facilityName = '',
    ): void {
        $subject = "【お知らせ】{$title}";

        $this->sendEmailNotification($recipient, $subject, 'general-notification', [
            'recipientName' => $recipient->full_name,
            'title' => $title,
            'body' => $body,
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
            'facilityName' => $facilityName ?: config('app.name', 'きづり'),
        ]);
    }

    /**
     * Create an in-app notification and also send an email for a user.
     * Convenience method combining notify() + sendEmailNotification().
     */
    public function notifyWithEmail(
        User $user,
        string $type,
        string $title,
        string $body,
        string $emailTemplate,
        array $emailData = [],
        array $notificationData = [],
    ): Notification {
        $notification = $this->notify($user, $type, $title, $body, $notificationData);

        $this->sendEmailNotification($user, "【{$title}】", $emailTemplate, $emailData);

        return $notification;
    }
}
