<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    /**
     * Determine whether the user can view the classroom.
     * Users can view their own classroom; admins can view any.
     */
    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return in_array($classroom->id, $user->switchableClassroomIds(), true);
    }

    /**
     * Determine whether the user can update the classroom settings.
     * Only admin or master staff of the classroom.
     */
    public function update(User $user, Classroom $classroom): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $user->isMaster()) {
            return in_array($classroom->id, $user->switchableClassroomIds(), true);
        }

        return false;
    }
}
