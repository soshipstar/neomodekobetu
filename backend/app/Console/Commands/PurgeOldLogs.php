<?php

namespace App\Console\Commands;

use App\Models\AiGenerationLog;
use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Models\MasterAdminAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 各種ログテーブルの保持期間ポリシーを適用し、古いレコードを削除する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R8 (2026-05-17):
 *  - V5 プライバシー保護 / 表 3-6 ⑤ データの保存期間管理・定期削除
 *  - V10 検証可能性 / 表 3-11 ⑤ 「監査証跡の保持期間が法令要件・ビジネス要件を踏まえて設定」
 *
 * デフォルト保持期間 (本サービスのプライバシーポリシー v1.0 に準拠):
 *  - ai_generation_logs : 5 年 (1825 日)
 *  - audit_logs         : 5 年
 *  - master_admin_audit_logs : 5 年 (マスター監査は最長保管)
 *  - error_logs         : 1 年 (運用ログは短期)
 *
 * 使い方:
 *   php artisan logs:purge                      # 全テーブル、デフォルト保持期間
 *   php artisan logs:purge --dry-run            # 削除対象件数のみ表示 (実削除しない)
 *   php artisan logs:purge --table=ai           # ai_generation_logs のみ
 *   php artisan logs:purge --days=1095          # 全テーブル一律 3 年で実行
 *
 * 注意:
 *  - 古い順 (オーダー id ASC) で削除する。R9 のハッシュチェーンは
 *    途中行が消えるとチェーンが切れるが、削除自体は監査上正当な運用
 *    (保持期間切れ) であるため、削除アクションそのものを master_admin_audit_logs
 *    に記録して将来の verifyChain 結果と突き合わせ可能にする。
 *  - cron / Scheduler から日次で呼ぶ運用を想定。
 */
class PurgeOldLogs extends Command
{
    protected $signature = 'logs:purge
                            {--table= : 対象テーブル (ai|audit|master|error|all)}
                            {--days= : 全テーブル一律の保持日数 (省略時はテーブル別デフォルト)}
                            {--dry-run : 削除対象件数のみ表示}';

    protected $description = '各種ログテーブルの古いレコードを削除します (保持期間ポリシー適用)';

    /** テーブル別のデフォルト保持日数 */
    private const DEFAULT_RETENTION_DAYS = [
        'ai'     => 1825,   // 5 年
        'audit'  => 1825,   // 5 年
        'master' => 1825,   // 5 年
        'error'  => 365,    // 1 年
    ];

    public function handle(): int
    {
        $only = (string) ($this->option('table') ?: 'all');
        $forcedDays = $this->option('days') !== null ? (int) $this->option('days') : null;
        $dryRun = (bool) $this->option('dry-run');

        $targets = [
            'ai'     => AiGenerationLog::class,
            'audit'  => AuditLog::class,
            'master' => MasterAdminAuditLog::class,
            'error'  => ErrorLog::class,
        ];

        $totalDeleted = 0;
        foreach ($targets as $key => $modelClass) {
            if ($only !== 'all' && $only !== $key) continue;

            $days = $forcedDays ?? self::DEFAULT_RETENTION_DAYS[$key];
            $cutoff = now()->subDays($days);
            $count = $modelClass::where('created_at', '<', $cutoff)->count();

            $this->info(sprintf(
                '  %-30s | retention=%4d days | cutoff=%s | %d row(s) older',
                class_basename($modelClass),
                $days,
                $cutoff->toDateString(),
                $count,
            ));

            if ($dryRun || $count === 0) continue;

            // バッチ削除 (10000 件単位) で長期保持テーブルへの単一トランザクション過大を避ける
            $deleted = 0;
            do {
                $ids = $modelClass::where('created_at', '<', $cutoff)
                    ->orderBy('id')
                    ->limit(10000)
                    ->pluck('id');
                if ($ids->isEmpty()) break;

                $n = $modelClass::whereIn('id', $ids)->delete();
                $deleted += $n;
            } while (! empty($n));

            $totalDeleted += $deleted;
            $this->line("    → deleted {$deleted} row(s)");

            // 削除アクション自体を監査記録に残す (M*A*A*L には削除しない)
            $this->recordPurgeAction($key, $days, $cutoff->toIso8601String(), $deleted);
        }

        if ($dryRun) {
            $this->warn('(dry-run: no rows were actually deleted)');
        } else {
            $this->info("Total: {$totalDeleted} row(s) deleted.");
        }
        return self::SUCCESS;
    }

    private function recordPurgeAction(string $tableKey, int $days, string $cutoffIso, int $deleted): void
    {
        try {
            MasterAdminAuditLog::create([
                'action'  => 'logs_purged',
                'context' => [
                    'table'        => $tableKey,
                    'retention_d'  => $days,
                    'cutoff'       => $cutoffIso,
                    'deleted_count' => $deleted,
                ],
            ]);
        } catch (\Throwable $e) {
            // 記録失敗は無視 (削除自体は完了)
        }
    }
}
