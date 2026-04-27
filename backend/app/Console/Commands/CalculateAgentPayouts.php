<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\Billing\AgentPayoutCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * 代理店の月次手数料を集計するコマンド。
 *
 * Schedule では毎月1日の早朝に前月分を全代理店に対して実行する。
 * 結果は draft 状態で保存される（マスター管理者が確認 → finalize → mark-paid）。
 *
 * 使い方:
 *   php artisan agent-payouts:calculate              # 前月分を全代理店に対して
 *   php artisan agent-payouts:calculate --month=2026-04
 *   php artisan agent-payouts:calculate --agent=3
 *   php artisan agent-payouts:calculate --dry-run    # ログのみ、保存しない
 */
class CalculateAgentPayouts extends Command
{
    protected $signature = 'agent-payouts:calculate
        {--month= : 集計対象月 (YYYY-MM)。省略時は前月}
        {--agent= : 代理店IDで限定}
        {--dry-run : 計算ログのみ出力し、レコード作成をスキップ}';

    protected $description = '代理店の月次手数料を集計し AgentPayout レコードを作成 / 更新する。';

    public function handle(): int
    {
        $monthStr = $this->option('month');
        $month = $monthStr
            ? CarbonImmutable::createFromFormat('Y-m', (string) $monthStr)->startOfMonth()
            : CarbonImmutable::now()->subMonth()->startOfMonth();

        $agentId = $this->option('agent');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            '対象月: %s （支払期日: %s）%s',
            $month->format('Y年n月'),
            $month->endOfMonth()->addMonth()->endOfMonth()->toDateString(),
            $dryRun ? ' [DRY RUN]' : '',
        ));

        $agents = Agent::where('is_active', true)
            ->when($agentId, fn ($q) => $q->where('id', (int) $agentId))
            ->get();

        if ($agents->isEmpty()) {
            $this->warn('対象となる代理店がありません。');
            return self::SUCCESS;
        }

        $stripe = $this->stripe();
        $calc = new AgentPayoutCalculator($stripe);

        $totals = ['count' => 0, 'gross' => 0, 'commission' => 0];

        foreach ($agents as $agent) {
            try {
                if ($dryRun) {
                    // dry-run: 既存draft 上書きを避けるため計算だけ呼ばず、件数だけログ
                    $this->line(sprintf('  - %s (id=%d) → DRY RUN', $agent->name, $agent->id));
                    continue;
                }
                $payout = $calc->calculateMonth($agent, $month);
                if ($payout) {
                    $totals['count']++;
                    $totals['gross'] += (int) $payout->gross_revenue;
                    $totals['commission'] += (int) $payout->commission_amount;
                    $this->line(sprintf(
                        '  ✓ %s (id=%d): 売上 ¥%s / 利益 ¥%s / 手数料 ¥%s（status=%s）',
                        $agent->name,
                        $agent->id,
                        number_format((int) $payout->gross_revenue),
                        number_format((int) $payout->net_profit),
                        number_format((int) $payout->commission_amount),
                        $payout->status,
                    ));
                } else {
                    $this->line(sprintf('  - %s (id=%d): 対象invoice なし、スキップ', $agent->name, $agent->id));
                }
            } catch (\Throwable $e) {
                $this->error(sprintf('  ✗ %s (id=%d) 失敗: %s', $agent->name, $agent->id, $e->getMessage()));
                Log::error('CalculateAgentPayouts failed', [
                    'agent_id' => $agent->id,
                    'month' => $month->toDateString(),
                    'exception' => $e,
                ]);
            }
        }

        $this->info(sprintf(
            '完了: %d 件作成、売上計 ¥%s / 手数料計 ¥%s',
            $totals['count'],
            number_format($totals['gross']),
            number_format($totals['commission']),
        ));

        Log::info('agent-payouts:calculate completed', [
            'month' => $month->toDateString(),
            'agent_id' => $agentId,
            'dry_run' => $dryRun,
            'totals' => $totals,
        ]);

        return self::SUCCESS;
    }

    private function stripe(): ?StripeClient
    {
        $secret = config('cashier.secret');
        if (!$secret) {
            return null;
        }
        return new StripeClient($secret);
    }
}
