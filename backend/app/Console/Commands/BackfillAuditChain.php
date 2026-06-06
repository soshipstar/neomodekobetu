<?php

namespace App\Console\Commands;

use App\Models\AiGenerationLog;
use App\Models\AuditLog;
use App\Models\Concerns\HashChainable;
use Illuminate\Console\Command;

/**
 * 既存の監査ログ / AI 生成ログ行に対して row_hash と prev_row_hash を計算して埋める。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R9 (2026-05-17)
 *
 * 適用タイミング:
 *  - migration 2026_05_17_000004 実行直後に 1 回だけ実行
 *  - 以降は HashChainable::bootHashChainable() が自動計算するため不要
 *
 * 使い方:
 *   php artisan audit-logs:backfill-hash
 *   php artisan audit-logs:backfill-hash --table=audit --force
 *   php artisan audit-logs:backfill-hash --table=ai --force
 */
class BackfillAuditChain extends Command
{
    protected $signature = 'audit-logs:backfill-hash
                            {--table= : 対象テーブル (audit | ai | both)}
                            {--force : row_hash が既に入っている行も再計算}';

    protected $description = '既存ログに row_hash / prev_row_hash を埋め込みます';

    public function handle(): int
    {
        $table = (string) ($this->option('table') ?: 'both');
        $force = (bool) $this->option('force');

        if ($table === 'both' || $table === 'audit') {
            $this->info('Backfilling audit_logs ...');
            $count = $this->backfill(AuditLog::class, $force);
            $this->info("  {$count} row(s) updated.");
        }
        if ($table === 'both' || $table === 'ai') {
            $this->info('Backfilling ai_generation_logs ...');
            $count = $this->backfill(AiGenerationLog::class, $force);
            $this->info("  {$count} row(s) updated.");
        }

        $this->info('Done. Run audit-logs:verify-chain to confirm.');
        return self::SUCCESS;
    }

    /**
     * @param class-string $modelClass
     */
    private function backfill(string $modelClass, bool $force): int
    {
        $prev = null;
        $updated = 0;

        $query = $modelClass::query()->orderBy('id');
        $query->chunkById(500, function ($rows) use (&$prev, &$updated, $modelClass, $force) {
            foreach ($rows as $row) {
                if (! $force && ! empty($row->row_hash)) {
                    // 既存ハッシュを次の prev とする
                    $prev = $row->row_hash;
                    continue;
                }

                /** @var array<int, string> $fields */
                $fields = property_exists($row, 'hashFields') ? $row->hashFields : [];
                $newHash = HashChainable::computeHash($row, $fields, $prev);

                // saveQuietly 相当: creating フックを通さず直接書き込む
                // (これは backfill の特殊操作なので、自動計算をスキップする)
                $modelClass::query()->where('id', $row->id)->update([
                    'prev_row_hash' => $prev,
                    'row_hash'      => $newHash,
                ]);

                $prev = $newHash;
                $updated++;
            }
        });

        return $updated;
    }
}
