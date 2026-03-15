<?php

namespace App\Policies;

use App\Models\IndividualSupportPlan;
use App\Models\User;

class SupportPlanPolicy
{
    /**
     * Determine whether the user can view the support plan.
     */
    public function view(User $user, IndividualSupportPlan $plan): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can view plans in their classroom
        if ($user->isStaff()) {
            return $user->classroom_id === $plan->classroom_id;
        }

        // Guardians can view plans for their children
        if ($user->isGuardian()) {
            $plan->loadMissing('student');

            return $plan->student && $plan->student->guardian_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create support plans.
     * Only staff and admin.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can update the support plan.
     * Staff in the same classroom, and only if the plan is still in draft.
     */
    public function update(User $user, IndividualSupportPlan $plan): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $user->classroom_id === $plan->classroom_id) {
            return in_array($plan->status, ['draft', 'pending_review']);
        }

        return false;
    }

    /**
     * Determine whether the user can sign (provide guardian signature) the plan.
     * Only the guardian of the student.
     */
    public function sign(User $user, IndividualSupportPlan $plan): bool
    {
        if (! $user->isGuardian()) {
            return false;
        }

        $plan->loadMissing('student');

        return $plan->student && $plan->student->guardian_id === $user->id;
    }

    /**
     * Determine whether the user can review/approve the plan.
     * Only master staff or admin in the same classroom.
     */
    public function review(User $user, IndividualSupportPlan $plan): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $user->isMaster()) {
            return $user->classroom_id === $plan->classroom_id;
        }

        return false;
    }
}
