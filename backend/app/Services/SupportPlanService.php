<?php

namespace App\Services;

use App\Events\PlanStatusChanged;
use App\Jobs\GenerateEmbeddingJob;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\SupportPlanDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportPlanService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Create a new individual support plan with details.
     *
     * @param  Student  $student
     * @param  array  $data  Plan data including 'details' array
     * @return IndividualSupportPlan
     */
    public function createPlan(Student $student, array $data): IndividualSupportPlan
    {
        return DB::transaction(function () use ($student, $data) {
            $plan = IndividualSupportPlan::create([
                'student_id' => $student->id,
                'classroom_id' => $student->classroom_id,
                'student_name' => $student->student_name,
                'created_date' => $data['created_date'] ?? now()->toDateString(),
                'life_intention' => $data['life_intention'] ?? null,
                'overall_policy' => $data['overall_policy'] ?? null,
                'long_term_goal' => $data['long_term_goal'] ?? null,
                'short_term_goal' => $data['short_term_goal'] ?? null,
                'plan_source_period' => $data['plan_source_period'] ?? null,
                'start_type' => $data['start_type'] ?? null,
                'basis_content' => $data['basis_content'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            if (! empty($data['details'])) {
                $this->saveDetails($plan, $data['details']);
            }

            return $plan->load('details');
        });
    }

    /**
     * Update an existing support plan and its details.
     *
     * @param  IndividualSupportPlan  $plan
     * @param  array  $data
     * @return IndividualSupportPlan
     */
    public function updatePlanWithDetails(IndividualSupportPlan $plan, array $data): IndividualSupportPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update(collect($data)->only([
                'life_intention',
                'overall_policy',
                'long_term_goal',
                'short_term_goal',
                'plan_source_period',
                'start_type',
                'basis_content',
                'consent_date',
            ])->all());

            if (isset($data['details'])) {
                // Delete existing details and recreate
                $plan->details()->delete();
                $this->saveDetails($plan, $data['details']);
            }

            return $plan->load('details');
        });
    }

    /**
     * Submit a plan for guardian review.
     *
     * @param  IndividualSupportPlan  $plan
     * @return void
     */
    public function submitForReview(IndividualSupportPlan $plan): void
    {
        $plan->update(['status' => 'pending_review']);

        broadcast(new PlanStatusChanged($plan, 'pending_review'))->toOthers();

        // Notify the guardian
        $guardian = $plan->student->guardian;
        if ($guardian) {
            $this->notificationService->notify(
                $guardian,
                'plan_review',
                '支援計画の確認依頼',
                "{$plan->student_name}さんの個別支援計画書が確認待ちです。",
                [
                    'plan_id' => $plan->id,
                    'student_id' => $plan->student_id,
                ]
            );

            $this->notificationService->sendEmailNotification(
                $guardian,
                '【きづり】支援計画の確認依頼',
                "{$plan->student_name}さんの個別支援計画書が作成されました。ログインして内容をご確認ください。"
            );
        }
    }

    /**
     * Approve (finalize) a support plan, marking it as official.
     *
     * @param  IndividualSupportPlan  $plan
     * @return void
     */
    public function approvePlan(IndividualSupportPlan $plan): void
    {
        $plan->update([
            'status' => 'approved',
            'is_official' => true,
        ]);

        broadcast(new PlanStatusChanged($plan, 'approved'))->toOthers();

        // Queue embedding generation for vector search
        GenerateEmbeddingJob::dispatch('support_plan', $plan->id);

        // Notify staff in the classroom
        $this->notificationService->notifyClassroom(
            $plan->classroom_id,
            'staff',
            'plan_approved',
            '支援計画が承認されました',
            "{$plan->student_name}さんの個別支援計画書が承認されました。",
            [
                'plan_id' => $plan->id,
                'student_id' => $plan->student_id,
            ]
        );
    }

    /**
     * Save detail rows for a support plan.
     */
    private function saveDetails(IndividualSupportPlan $plan, array $details): void
    {
        foreach ($details as $index => $detail) {
            SupportPlanDetail::create([
                'plan_id' => $plan->id,
                'domain' => $detail['domain'],
                'current_status' => $detail['current_status'] ?? null,
                'goal' => $detail['goal'] ?? null,
                'support_content' => $detail['support_content'] ?? null,
                'achievement_status' => $detail['achievement_status'] ?? null,
                'sort_order' => $detail['sort_order'] ?? $index,
            ]);
        }
    }
}
