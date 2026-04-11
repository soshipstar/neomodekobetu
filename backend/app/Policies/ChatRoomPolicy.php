<?php

namespace App\Policies;

use App\Models\ChatRoom;
use App\Models\User;

class ChatRoomPolicy
{
    /**
     * Determine whether the user can view (access) the chat room.
     * Staff see rooms in their classroom; guardians see only their own rooms.
     */
    public function view(User $user, ChatRoom $room): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can view rooms for students in their classroom
        if ($user->isStaff()) {
            $room->loadMissing('student');

            return $room->student
                && in_array($room->student->classroom_id, $user->accessibleClassroomIds(), true);
        }

        // Guardians can only view their own rooms
        if ($user->isGuardian()) {
            return $room->guardian_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can send a message in this chat room.
     * Same rules as view - participants only.
     */
    public function sendMessage(User $user, ChatRoom $room): bool
    {
        return $this->view($user, $room);
    }
}
