<?php

namespace App\Jobs;

use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Services\AiGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonitoringReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly int $recordId,
    ) {
        $this->onQueue('ai');
    }

    /**
     * Execute the job: generate monitoring report content via AI.
     */
    public function handle(AiGenerationService $aiService): void
    {
        $record = MonitoringRecord::with(['plan.details', 'student'])->findOrFail($this->recordId);

        Log::info('Generating AI monitoring report', ['record_id' => $this->recordId]);

        $generated = $aiService->generateMonitoringReport($record);

        DB::transaction(function () use ($record, $generated) {
            $record->update([
                'overall_comment' => $generated['overall_comment'] ?? $record->overall_comment,
                'short_term_goal_achievement' => $generated['short_term_goal_achievement'] ?? null,
                'long_term_goal_achievement' => $generated['long_term_goal_achievement'] ?? null,
            ]);

            if (! empty($generated['details'])) {
                foreach ($generated['details'] as $detail) {
                    MonitoringDetail::updateOrCreate(
                        [
                            'monitoring_id' => $record->id,
                            'domain' => $detail['domain'] ?? '',
                        ],
                        [
                            'achievement' => $detail['achievement'] ?? null,
                            'comment' => $detail['comment'] ?? null,
                            'next_action' => $detail['next_action'] ?? null,
                        ]
                    );
                }
            }
        });

        Log::info('AI monitoring report generation complete', ['record_id' => $this->recordId]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateMonitoringReportJob failed', [
            'record_id' => $this->recordId,
            'error' => $exception->getMessage(),
        ]);
    }
}
