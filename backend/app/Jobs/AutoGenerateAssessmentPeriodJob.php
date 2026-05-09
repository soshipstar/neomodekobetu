<?php

namespace App\Jobs;

use App\Services\AssessmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoGenerateAssessmentPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    /**
     * Execute the job: auto-generate Assessment periods for the current half-year.
     */
    public function handle(AssessmentService $assessmentService): void
    {
        Log::info('Starting auto-generation of Assessment periods');

        $assessmentService->autoGenerateNextAssessmentPeriods();

        Log::info('Auto-generation of Assessment periods complete');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AutoGenerateAssessmentPeriodJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
