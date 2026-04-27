<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-110: 代理店API（CRUD・販売チャネル・手数料集計・代理店用閲覧）の権限とロジック検証
 */
class B110_AgentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        $companyA = Company::create(['name' => 'A社', 'is_active' => true]);
        $classA = Classroom::create(['classroom_name' => 'A教室', 'company_id' => $companyA->id, 'is_active' => true]);

        $agent = Agent::factory()->create(['name' => 'AG', 'default_commission_rate' => 0.20]);

        $mkUser = fn(string $uname, string $type, array $extra = []) => User::create(array_merge([
            'username' => $uname,
            'password' => bcrypt('pass'),
            'full_name' => $uname,
            'user_type' => $type,
            'is_master' => false,
            'is_company_admin' => false,
            'is_active' => true,
        ], $extra));

        return [
            'companyA' => $companyA,
            'classA' => $classA,
            'agent' => $agent,
            'master' => $mkUser('master_b110', 'admin', ['is_master' => true]),
            'normalAdmin' => $mkUser('admin_b110', 'admin', ['classroom_id' => $classA->id]),
            'companyAdmin' => $mkUser('cadmin_b110', 'admin', ['classroom_id' => $classA->id, 'is_company_admin' => true]),
            'agentUser' => $mkUser('agentuser_b110', 'agent', ['agent_id' => $agent->id]),
        ];
    }

    public function test_normal_admin_cannot_list_agents(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['normalAdmin'])
            ->getJson('/api/admin/master/agents')
            ->assertStatus(403);
    }

    public function test_company_admin_cannot_list_agents(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['companyAdmin'])
            ->getJson('/api/admin/master/agents')
            ->assertStatus(403);
    }

    public function test_master_can_create_and_list_agent(): void
    {
        $f = $this->fixture();
        $res = $this->actingAs($f['master'])
            ->postJson('/api/admin/master/agents', [
                'name' => 'B代理店',
                'default_commission_rate' => 0.25,
                'contact_email' => 'b@example.com',
            ])
            ->assertStatus(201);
        $res->assertJsonPath('data.name', 'B代理店');

        $this->actingAs($f['master'])
            ->getJson('/api/admin/master/agents')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');  // fixture の AG + 新規B
    }

    public function test_master_cannot_delete_agent_with_companies(): void
    {
        $f = $this->fixture();
        $f['companyA']->update(['agent_id' => $f['agent']->id, 'agent_assigned_at' => now()]);

        $this->actingAs($f['master'])
            ->deleteJson('/api/admin/master/agents/'.$f['agent']->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_master_assigns_company_to_agent_via_sales_channel(): void
    {
        $f = $this->fixture();
        $res = $this->actingAs($f['master'])
            ->putJson('/api/admin/master/billing/companies/'.$f['companyA']->id.'/sales-channel', [
                'agent_id' => $f['agent']->id,
                'commission_rate_override' => 0.30,
            ])
            ->assertStatus(200);

        $res->assertJsonPath('data.agent_id', $f['agent']->id);
        $res->assertJsonPath('data.effective_commission_rate', 0.30);

        $f['companyA']->refresh();
        $this->assertNotNull($f['companyA']->agent_assigned_at);
    }

    public function test_master_returns_to_direct_sales(): void
    {
        $f = $this->fixture();
        $f['companyA']->update(['agent_id' => $f['agent']->id, 'agent_assigned_at' => now()]);

        $this->actingAs($f['master'])
            ->putJson('/api/admin/master/billing/companies/'.$f['companyA']->id.'/sales-channel', [
                'agent_id' => null,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.agent_id', null);

        $f['companyA']->refresh();
        $this->assertNull($f['companyA']->agent_assigned_at);
    }

    public function test_agent_user_can_access_dashboard_with_self_data(): void
    {
        $f = $this->fixture();
        $f['companyA']->update(['agent_id' => $f['agent']->id, 'agent_assigned_at' => now()->subDay()]);

        // 当月paid invoice を1件用意
        Invoice::create([
            'company_id' => $f['companyA']->id,
            'stripe_invoice_id' => 'in_test_'.uniqid(),
            'status' => 'paid',
            'amount_due' => 25000,
            'amount_paid' => 25000,
            'total' => 25000,
            'currency' => 'jpy',
            'paid_at' => now(),
        ]);

        $res = $this->actingAs($f['agentUser'])
            ->getJson('/api/agent/dashboard')
            ->assertStatus(200);

        $res->assertJsonPath('data.company_count', 1);
        $res->assertJsonPath('data.current_month.applicable_revenue', 25000);
        // commission = 25000 * 0.20 = 5000
        $res->assertJsonPath('data.current_month.estimated_commission', 5000);
    }

    public function test_agent_user_companies_endpoint_returns_only_self_companies(): void
    {
        $f = $this->fixture();
        $companyB = Company::create(['name' => 'B社（別代理店）']);
        $otherAgent = Agent::factory()->create();
        $companyB->update(['agent_id' => $otherAgent->id, 'agent_assigned_at' => now()]);

        $f['companyA']->update(['agent_id' => $f['agent']->id, 'agent_assigned_at' => now()]);

        $res = $this->actingAs($f['agentUser'])
            ->getJson('/api/agent/companies')
            ->assertStatus(200);

        $names = array_column($res->json('data'), 'name');
        $this->assertContains('A社', $names);
        $this->assertNotContains('B社（別代理店）', $names);
    }

    public function test_normal_admin_cannot_access_agent_endpoints(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['normalAdmin'])
            ->getJson('/api/agent/dashboard')
            ->assertStatus(403);
    }

    public function test_calculate_payouts_creates_draft(): void
    {
        $f = $this->fixture();
        $f['companyA']->update(['agent_id' => $f['agent']->id, 'agent_assigned_at' => now()->startOfMonth()->subDay()]);

        Invoice::create([
            'company_id' => $f['companyA']->id,
            'stripe_invoice_id' => 'in_calc_'.uniqid(),
            'status' => 'paid',
            'amount_paid' => 100000,
            'total' => 100000,
            'currency' => 'jpy',
            'paid_at' => now()->startOfMonth()->addDays(5),
        ]);

        $this->actingAs($f['master'])
            ->postJson('/api/admin/master/agent-payouts/calculate', [
                'period' => now()->format('Y-m'),
                'agent_id' => $f['agent']->id,
            ])
            ->assertStatus(200);

        $payout = AgentPayout::where('agent_id', $f['agent']->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame(100000, $payout->gross_revenue);
        $this->assertSame('draft', $payout->status);
        // commission = (100000 - 0 fees) * 0.20 = 20000 (Stripe 手数料はテスト時 null)
        $this->assertSame(20000, $payout->commission_amount);
    }

    public function test_finalize_and_mark_paid_state_machine(): void
    {
        $f = $this->fixture();
        $payout = AgentPayout::create([
            'agent_id' => $f['agent']->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'due_date' => '2026-05-31',
            'gross_revenue' => 50000,
            'stripe_fees' => 1000,
            'net_profit' => 49000,
            'commission_rate' => 0.20,
            'commission_amount' => 9800,
            'status' => 'draft',
        ]);

        // draft → finalized
        $this->actingAs($f['master'])
            ->postJson('/api/admin/master/agent-payouts/'.$payout->id.'/finalize')
            ->assertStatus(200);
        $this->assertSame('finalized', $payout->fresh()->status);

        // 重ねて finalize は 422
        $this->actingAs($f['master'])
            ->postJson('/api/admin/master/agent-payouts/'.$payout->id.'/finalize')
            ->assertStatus(422);

        // finalized → paid
        $this->actingAs($f['master'])
            ->postJson('/api/admin/master/agent-payouts/'.$payout->id.'/mark-paid', [
                'paid_at' => '2026-05-30',
                'transaction_ref' => 'BANK-001',
            ])
            ->assertStatus(200);
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('BANK-001', $payout->fresh()->transaction_ref);
    }

    public function test_invoice_before_agent_assigned_at_is_excluded(): void
    {
        $f = $this->fixture();
        // 4/15 から代理店紐付け
        $f['companyA']->update(['agent_id' => $f['agent']->id, 'agent_assigned_at' => '2026-04-15 00:00:00']);

        // 4/10 の paid invoice → 対象外
        Invoice::create([
            'company_id' => $f['companyA']->id,
            'stripe_invoice_id' => 'in_before_'.uniqid(),
            'status' => 'paid',
            'amount_paid' => 50000,
            'total' => 50000,
            'currency' => 'jpy',
            'paid_at' => '2026-04-10 12:00:00',
        ]);
        // 4/20 の paid invoice → 対象
        Invoice::create([
            'company_id' => $f['companyA']->id,
            'stripe_invoice_id' => 'in_after_'.uniqid(),
            'status' => 'paid',
            'amount_paid' => 30000,
            'total' => 30000,
            'currency' => 'jpy',
            'paid_at' => '2026-04-20 12:00:00',
        ]);

        $this->actingAs($f['master'])
            ->postJson('/api/admin/master/agent-payouts/calculate', [
                'period' => '2026-04',
                'agent_id' => $f['agent']->id,
            ])
            ->assertStatus(200);

        $payout = AgentPayout::where('agent_id', $f['agent']->id)
            ->where('period_start', '2026-04-01')
            ->first();
        $this->assertNotNull($payout);
        $this->assertSame(30000, $payout->gross_revenue);  // 4/20分のみ
        $this->assertSame(6000, $payout->commission_amount); // 30000 * 0.20
    }
}
