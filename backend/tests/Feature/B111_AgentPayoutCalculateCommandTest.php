<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * B-111: agent-payouts:calculate コマンドの動作検証
 * - --month で対象月を指定できる
 * - active 代理店のみ対象
 * - --agent で限定できる
 * - --dry-run で保存しない
 * - finalized 状態の既存レコードは上書きしない
 */
class B111_AgentPayoutCalculateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_creates_drafts_for_active_agents_with_paid_invoices(): void
    {
        $agentA = Agent::factory()->create(['name' => 'A', 'default_commission_rate' => 0.20, 'is_active' => true]);
        $agentB = Agent::factory()->create(['name' => 'B', 'default_commission_rate' => 0.15, 'is_active' => true]);
        $agentInactive = Agent::factory()->create(['is_active' => false]);

        $companyA = Company::factory()->create(['agent_id' => $agentA->id, 'agent_assigned_at' => '2026-03-01']);
        $companyB = Company::factory()->create(['agent_id' => $agentB->id, 'agent_assigned_at' => '2026-03-01']);
        Company::factory()->create(['agent_id' => $agentInactive->id, 'agent_assigned_at' => '2026-03-01']);

        Invoice::create([
            'company_id' => $companyA->id, 'stripe_invoice_id' => 'in_a_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 100000, 'total' => 100000,
            'currency' => 'jpy', 'paid_at' => '2026-04-15 10:00:00',
        ]);
        Invoice::create([
            'company_id' => $companyB->id, 'stripe_invoice_id' => 'in_b_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 50000, 'total' => 50000,
            'currency' => 'jpy', 'paid_at' => '2026-04-20 10:00:00',
        ]);

        $exit = Artisan::call('agent-payouts:calculate', ['--month' => '2026-04']);
        $this->assertSame(0, $exit);

        $this->assertSame(1, AgentPayout::where('agent_id', $agentA->id)->count());
        $this->assertSame(1, AgentPayout::where('agent_id', $agentB->id)->count());
        $this->assertSame(0, AgentPayout::where('agent_id', $agentInactive->id)->count());

        $payoutA = AgentPayout::where('agent_id', $agentA->id)->first();
        $this->assertSame(100000, $payoutA->gross_revenue);
        $this->assertSame(20000, $payoutA->commission_amount); // 100000 * 0.20
        $this->assertSame('draft', $payoutA->status);
        $this->assertSame('2026-05-31', $payoutA->due_date->toDateString());

        $payoutB = AgentPayout::where('agent_id', $agentB->id)->first();
        $this->assertSame(7500, $payoutB->commission_amount); // 50000 * 0.15
    }

    public function test_calculate_with_agent_option_limits_scope(): void
    {
        $agentA = Agent::factory()->create(['default_commission_rate' => 0.20]);
        $agentB = Agent::factory()->create(['default_commission_rate' => 0.10]);

        $cA = Company::factory()->create(['agent_id' => $agentA->id, 'agent_assigned_at' => '2026-03-01']);
        $cB = Company::factory()->create(['agent_id' => $agentB->id, 'agent_assigned_at' => '2026-03-01']);

        Invoice::create([
            'company_id' => $cA->id, 'stripe_invoice_id' => 'in_lim_a_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 30000, 'total' => 30000,
            'currency' => 'jpy', 'paid_at' => '2026-04-10 10:00:00',
        ]);
        Invoice::create([
            'company_id' => $cB->id, 'stripe_invoice_id' => 'in_lim_b_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 30000, 'total' => 30000,
            'currency' => 'jpy', 'paid_at' => '2026-04-10 10:00:00',
        ]);

        Artisan::call('agent-payouts:calculate', ['--month' => '2026-04', '--agent' => $agentA->id]);

        $this->assertSame(1, AgentPayout::where('agent_id', $agentA->id)->count());
        $this->assertSame(0, AgentPayout::where('agent_id', $agentB->id)->count());
    }

    public function test_dry_run_does_not_create_records(): void
    {
        $agent = Agent::factory()->create();
        $company = Company::factory()->create(['agent_id' => $agent->id, 'agent_assigned_at' => '2026-03-01']);
        Invoice::create([
            'company_id' => $company->id, 'stripe_invoice_id' => 'in_dry_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 12345, 'total' => 12345,
            'currency' => 'jpy', 'paid_at' => '2026-04-10 10:00:00',
        ]);

        Artisan::call('agent-payouts:calculate', ['--month' => '2026-04', '--dry-run' => true]);

        $this->assertSame(0, AgentPayout::count());
    }

    public function test_finalized_payout_is_not_overwritten_by_recalculate(): void
    {
        $agent = Agent::factory()->create(['default_commission_rate' => 0.20]);
        $company = Company::factory()->create(['agent_id' => $agent->id, 'agent_assigned_at' => '2026-03-01']);

        Invoice::create([
            'company_id' => $company->id, 'stripe_invoice_id' => 'in_first_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 50000, 'total' => 50000,
            'currency' => 'jpy', 'paid_at' => '2026-04-10 10:00:00',
        ]);
        Artisan::call('agent-payouts:calculate', ['--month' => '2026-04']);
        $payout = AgentPayout::where('agent_id', $agent->id)->first();
        $payout->update(['status' => 'finalized']);

        // 後から invoice を追加して再集計しても finalized は触らない
        Invoice::create([
            'company_id' => $company->id, 'stripe_invoice_id' => 'in_second_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 20000, 'total' => 20000,
            'currency' => 'jpy', 'paid_at' => '2026-04-25 10:00:00',
        ]);
        Artisan::call('agent-payouts:calculate', ['--month' => '2026-04']);

        $payout->refresh();
        $this->assertSame(50000, $payout->gross_revenue);  // 元の値のまま
        $this->assertSame('finalized', $payout->status);
    }

    public function test_default_month_is_previous_month(): void
    {
        $agent = Agent::factory()->create();
        $company = Company::factory()->create(['agent_id' => $agent->id, 'agent_assigned_at' => now()->subYear()]);

        $prev = now()->subMonth();
        Invoice::create([
            'company_id' => $company->id, 'stripe_invoice_id' => 'in_prev_'.uniqid(),
            'status' => 'paid', 'amount_paid' => 10000, 'total' => 10000,
            'currency' => 'jpy', 'paid_at' => $prev->copy()->day(15),
        ]);

        Artisan::call('agent-payouts:calculate');  // --month 省略

        $this->assertSame(1, AgentPayout::where('agent_id', $agent->id)->count());
        $payout = AgentPayout::where('agent_id', $agent->id)->first();
        $this->assertSame($prev->startOfMonth()->toDateString(), $payout->period_start->toDateString());
    }
}
