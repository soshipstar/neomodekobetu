<?php

namespace App\Jobs;

use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    /**
     * @param  string  $sourceType  e.g., 'support_plan', 'monitoring'
     * @param  int  $sourceId  ID of the source record
     */
    public function __construct(
        private readonly string $sourceType,
        private readonly int $sourceId,
    ) {
        $this->onQueue('ai');
    }

    /**
     * Build text content from the source record and generate + store its embedding.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        Log::info('Generating embedding', ['source_type' => $this->sourceType, 'source_id' => $this->sourceId]);

        [$text, $metadata] = $this->buildContent();

        if (empty($text)) {
            Log::warning('No content to embed', ['source_type' => $this->sourceType, 'source_id' => $this->sourceId]);

            return;
        }

        $embeddingService->storeEmbedding($this->sourceType, $this->sourceId, $text, $metadata);

        Log::info('Embedding generated', ['source_type' => $this->sourceType, 'source_id' => $this->sourceId]);
    }

    /**
     * Build text content and metadata from the source record.
     *
     * @return array  [string $text, array $metadata]
     */
    private function buildContent(): array
    {
        return match ($this->sourceType) {
            'support_plan' => $this->buildSupportPlanContent(),
            'monitoring' => $this->buildMonitoringContent(),
            default => ['', []],
        };
    }

    private function buildSupportPlanContent(): array
    {
        $plan = IndividualSupportPlan::with(['details', 'student'])->find($this->sourceId);

        if (! $plan) {
            return ['', []];
        }

        $parts = [
            "児童名: {$plan->student_name}",
            "本人の願い: {$plan->life_intention}",
            "支援方針: {$plan->overall_policy}",
            "長期目標: {$plan->long_term_goal}",
            "短期目標: {$plan->short_term_goal}",
        ];

        foreach ($plan->details as $detail) {
            $parts[] = "領域「{$detail->domain}」: 現状「{$detail->current_status}」 目標「{$detail->goal}」 支援内容「{$detail->support_content}」";
        }

        $text = implode("\n", $parts);

        $metadata = [
            'student_id' => $plan->student_id,
            'classroom_id' => $plan->classroom_id,
            'plan_date' => $plan->created_date?->toDateString(),
        ];

        return [$text, $metadata];
    }

    private function buildMonitoringContent(): array
    {
        $record = MonitoringRecord::with(['details', 'plan', 'student'])->find($this->sourceId);

        if (! $record) {
            return ['', []];
        }

        $parts = [
            "児童名: {$record->student->student_name}",
            "モニタリング日: {$record->monitoring_date?->format('Y/m/d')}",
            "総合所見: {$record->overall_comment}",
        ];

        foreach ($record->details as $detail) {
            $parts[] = "領域「{$detail->domain}」: 達成度「{$detail->achievement}」 所見「{$detail->comment}」";
        }

        $text = implode("\n", $parts);

        $metadata = [
            'student_id' => $record->student_id,
            'classroom_id' => $record->classroom_id,
            'plan_id' => $record->plan_id,
            'monitoring_date' => $record->monitoring_date?->toDateString(),
        ];

        return [$text, $metadata];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateEmbeddingJob failed', [
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
