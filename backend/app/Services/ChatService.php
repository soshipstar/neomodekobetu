<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatMessageStaffRead;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    /**
     * Get all chat rooms accessible by a staff member in a given classroom,
     * ordered by last message timestamp.
     *
     * @param  int  $staffId
     * @param  int  $classroomId
     * @return Collection
     */
    public function getRoomsForStaff(int $staffId, int $classroomId): Collection
    {
        return ChatRoom::with(['student', 'guardian'])
            ->whereHas('student', fn ($q) => $q->where('classroom_id', $classroomId))
            ->withCount([
                'messages as unread_count' => function ($query) use ($staffId) {
                    $query->notDeleted()
                        ->where('sender_id', '!=', $staffId)
                        ->whereDoesntHave('staffReads', fn ($q) => $q->where('staff_id', $staffId));
                },
            ])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Get unread message count for a specific room and staff member.
     *
     * @param  int  $roomId
     * @param  int  $staffId
     * @return int
     */
    public function getUnreadCount(int $roomId, int $staffId): int
    {
        return ChatMessage::where('room_id', $roomId)
            ->notDeleted()
            ->where('sender_id', '!=', $staffId)
            ->whereDoesntHave('staffReads', fn ($q) => $q->where('staff_id', $staffId))
            ->count();
    }

    /**
     * Send a message in a chat room, optionally with a file attachment.
     *
     * @param  ChatRoom  $room
     * @param  User  $sender
     * @param  string  $message
     * @param  UploadedFile|null  $file
     * @return ChatMessage
     */
    public function sendMessage(ChatRoom $room, User $sender, string $message, ?UploadedFile $file = null): ChatMessage
    {
        return DB::transaction(function () use ($room, $sender, $message, $file) {
            $data = [
                'room_id' => $room->id,
                'sender_id' => $sender->id,
                'sender_type' => $sender->user_type,
                'message' => $message,
                'message_type' => 'text',
            ];

            if ($file) {
                $path = $file->store(
                    "chat_attachments/{$room->id}",
                    's3'
                );

                $data['message_type'] = 'file';
                $data['attachment_path'] = $path;
                $data['attachment_name'] = $file->getClientOriginalName();
                $data['attachment_size'] = $file->getSize();
                $data['attachment_mime'] = $file->getMimeType();
            }

            $chatMessage = ChatMessage::create($data);

            // Update the room's last message timestamp
            $room->update(['last_message_at' => now()]);

            return $chatMessage;
        });
    }

    /**
     * Mark all unread messages in a room as read by a staff member.
     *
     * @param  int  $roomId
     * @param  int  $staffId
     * @return void
     */
    public function markAsRead(int $roomId, int $staffId): void
    {
        $unreadMessageIds = ChatMessage::where('room_id', $roomId)
            ->notDeleted()
            ->where('sender_id', '!=', $staffId)
            ->whereDoesntHave('staffReads', fn ($q) => $q->where('staff_id', $staffId))
            ->pluck('id');

        if ($unreadMessageIds->isEmpty()) {
            return;
        }

        $now = now();
        $records = $unreadMessageIds->map(fn ($messageId) => [
            'message_id' => $messageId,
            'staff_id' => $staffId,
            'read_at' => $now,
        ])->all();

        ChatMessageStaffRead::insert($records);
    }
}
