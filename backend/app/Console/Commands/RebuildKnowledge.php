<?php

namespace App\Console\Commands;

use App\Services\KnowledgeDistillationService;
use Illuminate\Console\Command;

/**
 * 支援知蒸留 D4: support_knowledge を法人単位で再計算する。
 * 同意ありの法人のみ・k匿名。冪等。
 */
class RebuildKnowledge extends Command
{
    protected $signature = 'ai:rebuild-knowledge {company? : company_id(省略時は全法人)}';

    protected $description = '支援知(support_knowledge)を法人内で蒸留・再計算する';

    public function handle(KnowledgeDistillationService $service): int
    {
        $cid = $this->argument('company');
        $n = $cid ? $service->rebuild((int) $cid) : $service->rebuildAll();
        $this->info("support_knowledge rebuilt: {$n} conditions");

        return self::SUCCESS;
    }
}
