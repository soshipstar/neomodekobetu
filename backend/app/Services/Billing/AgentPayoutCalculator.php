<?php

namespace App\Services\Billing;

use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\Company;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Stripe\StripeClient;

/**
 * 代理店への月次手数料を集計する。
 *
 * - 集計対象: 期間内に paid 状態となった Invoice（紹介企業に紐付くもの）
 * - 期間: 当月の period_start 〜 period_end（4月分なら 4/1 〜 4/30）
 * - 手数料率: companies.commission_rate_override > agents.default_commission_rate
 * - 利益 = gross_revenue - stripe_fees
 * - commission = net_profit * rate
 *
 * agent_assigned_at より前に paid されたInvoiceは対象外。
 *
 * Stripe手数料は Invoice.charge.balance_transaction.fee から取得。
 */
class AgentPayoutCalculator
{
    public function __construct(private readonly ?StripeClient $stripe = null) {}

    /**
     * 指定月の代理店手数料を集計し、AgentPayout を upsert する。
     * 既存の draft があれば再計算で上書き、 finalized 以降は触らない。
     *
     * @return AgentPayout|null 該当invoice が無い場合は null。
     */
    public function calculateMonth(Agent $agent, CarbonImmutable $month): ?AgentPayout
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $due = $end->addMonth()->endOfMonth();

        // 既存の payout を確認（すでに finalized なら触らない）
        $existing = AgentPayout::where('agent_id', $agent->id)
            ->where('period_start', $start->toDateString())
            ->first();
        if ($existing && $existing->isFinalized()) {
            return $existing;
        }

        // この代理店に紐付く企業のID
        $companyIds = Company::where('agent_id', $agent->id)->pluck('id', 'id');
        if ($companyIds->isEmpty()) {
            return $existing;
        }

        // 対象 invoice: 期間内に paid_at が入り、 paid 状態のもの
        // かつ 紐付く company の agent_assigned_at 以降のもの
        $invoices = Invoice::query()
            ->whereIn('company_id', $companyIds->keys())
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->get();

        // agent_assigned_at の制約を適用
        $companies = Company::whereIn('id', $companyIds->keys())
            ->get(['id', 'agent_assigned_at', 'commission_rate_override'])
            ->keyBy('id');

        $applicable = $invoices->filter(function (Invoice $inv) use ($companies) {
            $c = $companies->get($inv->company_id);
            if (!$c || !$c->agent_assigned_at) {
                return false;
            }
            return $inv->paid_at >= $c->agent_assigned_at;
        });

        if ($applicable->isEmpty() && !$existing) {
            return null;
        }

        $grossRevenue = (int) $applicable->sum('amount_paid');
        $stripeFees = $this->stripeFeesFor($applicable);
        $netProfit = $grossRevenue - $stripeFees;

        // 手数料率（企業ごと上書き優先 / なければ代理店default）。
        // 複数企業でレートが違う場合、各invoiceごとにrateを掛けて合計し、
        // 平均rateを記録する。
        $commission = 0;
        $weightedRateSum = 0.0;
        foreach ($applicable as $inv) {
            $c = $companies->get($inv->company_id);
            $rate = $c?->commission_rate_override !== null
                ? (float) $c->commission_rate_override
                : (float) $agent->default_commission_rate;
            $invFee = $this->feeForInvoice($inv);
            $invNet = ((int) $inv->amount_paid) - $invFee;
            $commission += (int) round($invNet * $rate);
            $weightedRateSum += $rate * (int) $inv->amount_paid;
        }
        $effectiveRate = $grossRevenue > 0 ? $weightedRateSum / $grossRevenue : (float) $agent->default_commission_rate;

        $data = [
            'agent_id' => $agent->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'due_date' => $due->toDateString(),
            'gross_revenue' => $grossRevenue,
            'stripe_fees' => $stripeFees,
            'net_profit' => $netProfit,
            'commission_rate' => round($effectiveRate, 4),
            'commission_amount' => max(0, $commission),
            'status' => $existing && $existing->isPaid() ? AgentPayout::STATUS_PAID : AgentPayout::STATUS_DRAFT,
            'included_invoice_ids' => $applicable->pluck('id')->all(),
        ];

        if ($existing) {
            $existing->update($data);
            return $existing->refresh();
        }
        return AgentPayout::create($data);
    }

    /**
     * Invoice群の Stripe手数料合計を返す。
     * 取得失敗時は0を返す（Stripe Connect未使用時など）。
     */
    private function stripeFeesFor($invoices): int
    {
        return (int) $invoices->sum(fn (Invoice $inv) => $this->feeForInvoice($inv));
    }

    private function feeForInvoice(Invoice $invoice): int
    {
        if (!$this->stripe || !$invoice->stripe_invoice_id) {
            return 0;
        }
        try {
            $stripeInvoice = $this->stripe->invoices->retrieve($invoice->stripe_invoice_id, [
                'expand' => ['charge.balance_transaction'],
            ]);
            $bt = $stripeInvoice->charge?->balance_transaction ?? null;
            if (is_string($bt)) {
                $bt = $this->stripe->balanceTransactions->retrieve($bt);
            }
            return (int) ($bt?->fee ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
