<?php

namespace App\Jobs;

use App\Services\KakehashiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoGenerateKakehashiPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    /**
     * Execute the job: auto-generate Kakehashi periods for the current half-year.
     */
    public function handle(KakehashiService $kakehashiService): void
    {
        Log::info('Starting auto-generation of Kakehashi periods');

        $kakehashiService->autoGeneratePeriods();

        Log::info('Auto-generation of Kakehashi periods complete');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AutoGenerateKakehashiPeriodJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
