<?php

namespace App\Console\Commands;

use App\Models\AiGenerationLog;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * 監査ログ / AI 生成ログのハッシュチェーンを検証する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R9 (2026-05-17)
 *
 * 使い方:
 *   php artisan audit-logs:verify-chain                  # 両テーブル検証
 *   php artisan audit-logs:verify-chain --table=audit    # audit_logs のみ
 *   php artisan audit-logs:verify-chain --table=ai       # ai_generation_logs のみ
 *   php artisan audit-logs:verify-chain --limit=10000    # 検証件数上限
 */
class VerifyAuditChain extends Command
{
    protected $signature = 'audit-logs:verify-chain
                            {--table= : 検証対象 (audit | ai | both)}
                            {--limit=100000 : 検証する最大件数}';

    protected $description = '監査ログのハッシュチェーン整合性を検証します';

    public function handle(): int
    {
        $table = (string) ($this->option('table') ?: 'both');
        $limit = (int) $this->option('limit');

        $totalErrors = 0;

        if ($table === 'both' || $table === 'audit') {
            $this->info('Verifying audit_logs ...');
            $errors = AuditLog::verifyChain($limit);
            $this->reportErrors('audit_logs', $errors);
            $totalErrors += count($errors);
        }

        if ($table === 'both' || $table === 'ai') {
            $this->info('Verifying ai_generation_logs ...');
            $errors = AiGenerationLog::verifyChain($limit);
            $this->reportErrors('ai_generation_logs', $errors);
            $totalErrors += count($errors);
        }

        if ($totalErrors === 0) {
            $this->info('✓ All chains are intact.');
            return self::SUCCESS;
        }

        $this->error("✗ Found {$totalErrors} chain integrity errors.");
        return self::FAILURE;
    }

    private function reportErrors(string $tableName, array $errors): void
    {
        if (empty($errors)) {
            $this->info("  {$tableName}: OK");
            return;
        }
        $this->warn("  {$tableName}: " . count($errors) . " error(s)");
        foreach (array_slice($errors, 0, 20) as $e) {
            $this->line(sprintf('    id=%d : %s', $e['id'], $e['error']));
        }
        if (count($errors) > 20) {
            $this->line('    ... (' . (count($errors) - 20) . ' more)');
        }
    }
}
