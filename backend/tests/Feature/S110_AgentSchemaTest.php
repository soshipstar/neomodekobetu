<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S-110: 代理店（Agent）スキーマ移行
 * - agents テーブルが必要列を持つ
 * - companies に agent_id / commission_rate_override / agent_assigned_at が追加される
 * - users に agent_id が追加される
 * - agent_payouts テーブルが必要列を持つ
 * - Agent / AgentPayout モデルの cast / relation が動く
 * - Company::effectiveCommissionRate() が想定通り動く
 */
class S110_AgentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_agents_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('agents'));
        foreach ([
            'name', 'code', 'contact_name', 'contact_email', 'contact_phone', 'address',
            'default_commission_rate', 'bank_info',
            'contract_document_path', 'contract_terms',
            'is_active', 'notes',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('agents', $col),
                "agents should have column: {$col}"
            );
        }
    }

    public function test_companies_has_agent_columns(): void
    {
        foreach (['agent_id', 'commission_rate_override', 'agent_assigned_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('companies', $col),
                "companies should have column: {$col}"
            );
        }
    }

    public function test_users_has_agent_id(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'agent_id'));
    }

    public function test_agent_payouts_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('agent_payouts'));
        foreach ([
            'agent_id', 'period_start', 'period_end', 'due_date',
            'gross_revenue', 'stripe_fees', 'net_profit',
            'commission_rate', 'commission_amount',
            'status', 'paid_at', 'transaction_ref', 'notes',
            'included_invoice_ids',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('agent_payouts', $col),
                "agent_payouts should have column: {$col}"
            );
        }
    }

    public function test_agent_factory_creates_agent(): void
    {
        $agent = Agent::factory()->create();
        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertTrue($agent->is_active);
        $this->assertEquals(0.2, (float) $agent->default_commission_rate);
    }

    public function test_company_can_belong_to_agent(): void
    {
        $agent = Agent::factory()->create(['name' => 'A代理店', 'default_commission_rate' => 0.25]);
        $company = Company::factory()->create([
            'agent_id' => $agent->id,
            'agent_assigned_at' => now(),
        ]);

        $this->assertSame($agent->id, $company->agent->id);
        $this->assertFalse($company->isDirectSales());
    }

    public function test_direct_sales_company_returns_zero_commission_rate(): void
    {
        $company = Company::factory()->create();
        $this->assertTrue($company->isDirectSales());
        $this->assertSame(0.0, $company->effectiveCommissionRate());
    }

    public function test_company_uses_agent_default_rate_when_no_override(): void
    {
        $agent = Agent::factory()->create(['default_commission_rate' => 0.30]);
        $company = Company::factory()->create([
            'agent_id' => $agent->id,
            'commission_rate_override' => null,
        ]);
        $company->loadMissing('agent');
        $this->assertEqualsWithDelta(0.30, $company->effectiveCommissionRate(), 0.0001);
    }

    public function test_company_override_rate_takes_precedence(): void
    {
        $agent = Agent::factory()->create(['default_commission_rate' => 0.20]);
        $company = Company::factory()->create([
            'agent_id' => $agent->id,
            'commission_rate_override' => 0.35,
        ]);
        $this->assertEqualsWithDelta(0.35, $company->effectiveCommissionRate(), 0.0001);
    }

    public function test_agent_payout_creates_with_required_fields(): void
    {
        $agent = Agent::factory()->create();
        $payout = AgentPayout::create([
            'agent_id' => $agent->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'due_date' => '2026-05-31',
            'gross_revenue' => 100000,
            'stripe_fees' => 3600,
            'net_profit' => 96400,
            'commission_rate' => 0.20,
            'commission_amount' => 19280,
            'status' => 'finalized',
        ]);

        $this->assertSame(96400, $payout->net_profit);
        $this->assertSame(19280, $payout->commission_amount);
        $this->assertEqualsWithDelta(0.20, (float) $payout->commission_rate, 0.0001);
        $this->assertSame('finalized', $payout->status);
        $this->assertTrue($payout->isFinalized());
        $this->assertFalse($payout->isPaid());
    }

    public function test_agent_user_can_be_created_with_agent_id(): void
    {
        $agent = Agent::factory()->create();
        $user = User::create([
            'username' => 'agent_user_'.uniqid(),
            'password' => bcrypt('test'),
            'full_name' => '代理店スタッフ',
            'user_type' => 'agent',
            'is_master' => false,
            'is_company_admin' => false,
            'is_active' => true,
            'agent_id' => $agent->id,
        ]);

        $this->assertTrue($user->isAgentUser());
        $this->assertSame($agent->id, $user->agent->id);
    }
}
