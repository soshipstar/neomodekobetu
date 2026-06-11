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
            $detail = AuditLog::verifyChainDetailed($limit);
            $this->reportDetail('audit_logs', $detail);
            $totalErrors += count($detail['errors']);
        }

        if ($table === 'both' || $table === 'ai') {
            $this->info('Verifying ai_generation_logs ...');
            $detail = AiGenerationLog::verifyChainDetailed($limit);
            $this->reportDetail('ai_generation_logs', $detail);
            $totalErrors += count($detail['errors']);
        }

        // AI-09/10 修正: errors (= 行の改ざん) のみを失敗扱いにする。
        // warnings (= 並行 INSERT 分岐 / purge による正当な削除ギャップ) は
        // 改ざんではないため成功判定を妨げない。
        if ($totalErrors === 0) {
            $this->info('✓ All chains are intact (no tampering detected).');
            return self::SUCCESS;
        }

        $this->error("✗ Found {$totalErrors} row_hash mismatch (tampering suspected).");
        return self::FAILURE;
    }

    /**
     * @param array{errors: array, warnings: array} $detail
     */
    private function reportDetail(string $tableName, array $detail): void
    {
        $errors = $detail['errors'];
        $warnings = $detail['warnings'];

        if (empty($errors) && empty($warnings)) {
            $this->info("  {$tableName}: OK");
            return;
        }

        if (! empty($errors)) {
            $this->error("  {$tableName}: " . count($errors) . ' tampering error(s)');
            foreach (array_slice($errors, 0, 20) as $e) {
                $this->line(sprintf('    [ERROR] id=%d : %s', $e['id'], $e['error']));
            }
            if (count($errors) > 20) {
                $this->line('    ... (' . (count($errors) - 20) . ' more errors)');
            }
        }

        if (! empty($warnings)) {
            $this->warn("  {$tableName}: " . count($warnings) . ' continuity warning(s) (並行INSERT/purge 由来の可能性、改ざんではない)');
            foreach (array_slice($warnings, 0, 5) as $w) {
                $this->line(sprintf('    [WARN] id=%d : %s', $w['id'], $w['error']));
            }
            if (count($warnings) > 5) {
                $this->line('    ... (' . (count($warnings) - 5) . ' more warnings)');
            }
        }
    }
}
