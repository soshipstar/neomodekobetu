<?php

namespace App\Observers;

use App\Events\PlanStatusChanged;
use App\Jobs\GenerateEmbeddingJob;
use App\Models\IndividualSupportPlan;

class SupportPlanObserver
{
    /**
     * Handle the IndividualSupportPlan "updated" event.
     *
     * If the status changed, dispatch a PlanStatusChanged event.
     * If the plan became official/approved, queue embedding generation.
     */
    public function updated(IndividualSupportPlan $plan): void
    {
        // Check if status was changed
        if ($plan->isDirty('status')) {
            broadcast(new PlanStatusChanged($plan, $plan->status))->toOthers();
        }

        // When a plan is approved/made official, generate an embedding for vector search
        if ($plan->isDirty('is_official') && $plan->is_official) {
            GenerateEmbeddingJob::dispatch('support_plan', $plan->id);
        }
    }
}
