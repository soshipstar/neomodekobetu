<?php

namespace App\Jobs;

use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Services\AiGenerationService;
use App\Services\SupportPlanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSupportPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    public function __construct(
        private readonly int $studentId,
        private readonly int $planId,
        private readonly array $context = [],
    ) {
        $this->onQueue('ai');
    }

    /**
     * Execute the job: generate support plan content via AI and update the plan.
     */
    public function handle(AiGenerationService $aiService, SupportPlanService $planService): void
    {
        $student = Student::findOrFail($this->studentId);
        $plan = IndividualSupportPlan::findOrFail($this->planId);

        Log::info('Generating AI support plan', [
            'student_id' => $this->studentId,
            'plan_id' => $this->planId,
        ]);

        $generated = $aiService->generateSupportPlan($student, $this->context);

        $planService->updatePlanWithDetails($plan, $generated);

        Log::info('AI support plan generation complete', [
            'plan_id' => $this->planId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSupportPlanJob failed', [
            'student_id' => $this->studentId,
            'plan_id' => $this->planId,
            'error' => $exception->getMessage(),
        ]);
    }
}
