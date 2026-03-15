<?php

namespace App\Jobs;

use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\MonitoringRecord;
use App\Models\Newsletter;
use App\Services\PdfGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    /**
     * @param  string  $type  One of: support_plan, monitoring, kakehashi, newsletter
     * @param  int  $recordId  ID of the source record
     */
    public function __construct(
        private readonly string $type,
        private readonly int $recordId,
    ) {
        $this->onQueue('pdf');
    }

    /**
     * Execute the job: generate the appropriate PDF.
     */
    public function handle(PdfGenerationService $pdfService): void
    {
        Log::info('Generating PDF', ['type' => $this->type, 'record_id' => $this->recordId]);

        $path = match ($this->type) {
            'support_plan' => $pdfService->generateSupportPlanPdf(
                IndividualSupportPlan::findOrFail($this->recordId)
            ),
            'monitoring' => $pdfService->generateMonitoringPdf(
                MonitoringRecord::findOrFail($this->recordId)
            ),
            'kakehashi' => $pdfService->generateKakehashiPdf(
                KakehashiPeriod::findOrFail($this->recordId)
            ),
            'newsletter' => $pdfService->generateNewsletterPdf(
                Newsletter::findOrFail($this->recordId)
            ),
            default => throw new \InvalidArgumentException("Unknown PDF type: {$this->type}"),
        };

        Log::info('PDF generated', ['type' => $this->type, 'record_id' => $this->recordId, 'path' => $path]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GeneratePdfJob failed', [
            'type' => $this->type,
            'record_id' => $this->recordId,
            'error' => $exception->getMessage(),
        ]);
    }
}
