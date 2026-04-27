<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-112: 代理店手数料 CSV エクスポートの権限・出力検証
 */
class B112_AgentPayoutCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeMaster(): User
    {
        return User::create([
            'username' => 'master_b112_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'M',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    private function makeNormalAdmin(): User
    {
        $c = Classroom::create(['classroom_name' => 'cls_b112', 'is_active' => true]);
        return User::create([
            'username' => 'admin_b112_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'A',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $c->id,
            'is_active' => true,
        ]);
    }

    public function test_normal_admin_cannot_export(): void
    {
        $admin = $this->makeNormalAdmin();
        $this->actingAs($admin)
            ->get('/api/admin/master/agent-payouts/export.csv')
            ->assertStatus(403);
    }

    public function test_master_can_export_csv_with_bom_and_headers(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create(['name' => 'A代理店', 'code' => 'AG001']);
        AgentPayout::create([
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

        $res = $this->actingAs($master)
            ->get('/api/admin/master/agent-payouts/export.csv')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $res->streamedContent();
        // UTF-8 BOM
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));
        // ヘッダ行に主要列
        $this->assertStringContainsString('代理店名', $body);
        $this->assertStringContainsString('売上総額', $body);
        $this->assertStringContainsString('Stripe手数料', $body);
        $this->assertStringContainsString('手数料額', $body);
        // データ行
        $this->assertStringContainsString('A代理店', $body);
        $this->assertStringContainsString('AG001', $body);
        $this->assertStringContainsString('100000', $body);
        $this->assertStringContainsString('19280', $body);
        $this->assertStringContainsString('0.2000', $body);
        $this->assertStringContainsString('確定(未払)', $body);
    }

    public function test_master_can_filter_by_status(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create();
        AgentPayout::create([
            'agent_id' => $agent->id, 'period_start' => '2026-04-01',
            'period_end' => '2026-04-30', 'due_date' => '2026-05-31',
            'gross_revenue' => 1, 'stripe_fees' => 0, 'net_profit' => 1,
            'commission_rate' => 0.20, 'commission_amount' => 1,
            'status' => 'paid',
        ]);
        AgentPayout::create([
            'agent_id' => $agent->id, 'period_start' => '2026-03-01',
            'period_end' => '2026-03-31', 'due_date' => '2026-04-30',
            'gross_revenue' => 2, 'stripe_fees' => 0, 'net_profit' => 2,
            'commission_rate' => 0.20, 'commission_amount' => 2,
            'status' => 'draft',
        ]);

        $res = $this->actingAs($master)
            ->get('/api/admin/master/agent-payouts/export.csv?status=paid')
            ->assertStatus(200);
        $body = $res->streamedContent();
        $this->assertStringContainsString('支払済', $body);
        $this->assertStringNotContainsString('集計中(draft)', $body);
    }
}
