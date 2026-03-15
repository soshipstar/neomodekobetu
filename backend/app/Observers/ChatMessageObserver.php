<?php

namespace App\Observers;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ChatMessageObserver
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Handle the ChatMessage "created" event.
     *
     * Updates the room's last_message_at timestamp, broadcasts the message
     * to the room's WebSocket channel, and dispatches email notifications
     * to offline recipients.
     */
    public function created(ChatMessage $message): void
    {
        $room = $message->room;
        if (! $room) {
            return;
        }

        // Update the chat room's last_message_at
        $room->update(['last_message_at' => $message->created_at ?? now()]);

        // Broadcast the new message event via WebSocket
        broadcast(new ChatMessageSent($message, $room))->toOthers();

        // Dispatch email notifications to the other party
        $this->dispatchEmailNotification($message);
    }

    /**
     * Send email notification to the chat counterpart.
     *
     * - If sender is guardian -> notify all staff in the student's classroom
     * - If sender is staff   -> notify the guardian
     */
    private function dispatchEmailNotification(ChatMessage $message): void
    {
        try {
            $room = $message->room()->with(['student.classroom', 'guardian'])->first();
            if (! $room || ! $room->student) {
                return;
            }

            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            $senderName = $this->resolveSenderName($message);
            $facilityName = $room->student->classroom?->classroom_name ?? config('app.name', 'きづり');
            $messagePreview = $message->message ?: '(添付ファイル)';

            if ($message->sender_type === 'guardian') {
                // Guardian sent a message -> notify staff in the same classroom
                $chatUrl = $frontendUrl . '/staff/chat/' . $room->id;
                $staffUsers = User::where('classroom_id', $room->student->classroom_id)
                    ->where('user_type', 'staff')
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get();

                foreach ($staffUsers as $staff) {
                    $this->notificationService->sendChatEmailNotification(
                        $staff,
                        $senderName,
                        $messagePreview,
                        $chatUrl,
                        $facilityName,
                    );
                }
            } elseif ($message->sender_type === 'staff') {
                // Staff sent a message -> notify the guardian
                $guardian = $room->guardian;
                if ($guardian && $guardian->is_active && ! empty($guardian->email)) {
                    $chatUrl = $frontendUrl . '/guardian/chat/' . $room->id;
                    $this->notificationService->sendChatEmailNotification(
                        $guardian,
                        $senderName,
                        $messagePreview,
                        $chatUrl,
                        $facilityName,
                    );
                }
            }
        } catch (\Throwable $e) {
            // Email notification failure must not break chat functionality
            Log::error('Failed to dispatch chat email notification', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the sender's display name from the message.
     */
    private function resolveSenderName(ChatMessage $message): string
    {
        if (in_array($message->sender_type, ['staff', 'guardian'])) {
            return User::where('id', $message->sender_id)->value('full_name') ?? '不明';
        }

        if ($message->sender_type === 'student') {
            return \App\Models\Student::where('id', $message->sender_id)->value('student_name') ?? '不明';
        }

        return '不明';
    }
}
