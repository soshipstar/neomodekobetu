<?php

namespace App\Console\Commands;

use App\Services\AiEditMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * AI学習基盤 Layer2: ai_edit_metrics を期間(月)単位で再計算する。
 * 同意済みのみ・k匿名。冪等(delete→insert)なので何度実行しても安全。
 */
class RebuildAiEditMetrics extends Command
{
    protected $signature = 'ai:rebuild-edit-metrics {period? : 対象月 YYYY-MM(省略時は当月)}';

    protected $description = 'AI学習基盤の修正傾向ロールアップ(ai_edit_metrics)を再計算する';

    public function handle(AiEditMetricsService $service): int
    {
        $period = (string) ($this->argument('period') ?: Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("period は YYYY-MM 形式で指定してください: {$period}");

            return self::FAILURE;
        }

        $count = $service->rebuild($period);
        $this->info("ai_edit_metrics rebuilt for {$period}: {$count} cells");

        return self::SUCCESS;
    }
}
