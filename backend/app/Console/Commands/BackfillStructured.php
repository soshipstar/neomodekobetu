<?php

namespace App\Console\Commands;

use App\Models\AiRevisionEvent;
use App\Services\StructuredExtractionService;
use Illuminate\Console\Command;

/**
 * 支援知蒸留エンジン D1: 既存の修正イベントに L2 構造化(structured)を後付けする。
 * 新規イベントは AiLearningCapture が記録時に付与するため、これは過去分の一括投入用。冪等。
 */
class BackfillStructured extends Command
{
    protected $signature = 'ai:backfill-structured {--limit=5000}';

    protected $description = '既存の ai_revision_events に L2 構造化(structured)を後付けする';

    public function handle(): int
    {
        $n = 0;
        AiRevisionEvent::whereNull('structured')->limit((int) $this->option('limit'))->cursor()
            ->each(function (AiRevisionEvent $rev) use (&$n) {
                $rev->structured = StructuredExtractionService::extract(
                    (string) $rev->after_text,
                    $rev->support_category,
                    $rev->program_category_id,
                    $rev->subj_cohort,
                    $rev->subj_growth_stage,
                );
                $rev->save();
                $n++;
            });

        $this->info("structured backfilled: {$n} events");

        return self::SUCCESS;
    }
}
