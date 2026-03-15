<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine whether the user can view any students.
     * Staff/admin can view students in their classroom; guardians can view their own children.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff() || $user->isGuardian();
    }

    /**
     * Determine whether the user can view the student.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can view students in their classroom
        if ($user->isStaff()) {
            return $user->classroom_id === $student->classroom_id;
        }

        // Guardians can only view their own children
        if ($user->isGuardian()) {
            return $student->guardian_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create students.
     * Only admin and staff can create students.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can update the student.
     * Must be in the same classroom (staff/admin).
     */
    public function update(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $user->classroom_id === $student->classroom_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the student.
     * Only admin or master staff can delete.
     */
    public function delete(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $user->isMaster()) {
            return $user->classroom_id === $student->classroom_id;
        }

        return false;
    }
}
