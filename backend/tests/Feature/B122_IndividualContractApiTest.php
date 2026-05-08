<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Company;
use App\Models\IndividualContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase C-2: 個別契約書 API
 *
 * 差分カテゴリ: api
 *
 * 代理店ユーザー: 自代理店分のみ CRUD 可能。soship_signed / customer_signed は
 *                変更不可 (admin のみ)。自分の agent_signed は切替可能。
 * マスター管理者: 全件閲覧 + 任意フィールド + 3者の署名フラグ全てを変更可能。
 */
class B122_IndividualContractApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupAgentAndCustomer(?Agent $agentArg = null): array
    {
        $agent = $agentArg ?? Agent::factory()->create();

        $agentUser = User::create([
            'username'  => 'agentu_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '代理店ユーザー',
            'user_type' => 'agent',
            'agent_id'  => $agent->id,
            'is_active' => true,
        ]);

        $company = Company::create([
            'name'              => '顧客企業A',
            'agent_id'          => $agent->id,
            'agent_assigned_at' => now(),
        ]);

        return [$agent, $agentUser, $company];
    }

    private function setupMaster(): User
    {
        return User::create([
            'username'  => 'master_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => 'マスター',
            'user_type' => 'admin',
            'is_master' => true,
            'is_active' => true,
        ]);
    }

    public function test_agent_can_create_contract_for_own_customer(): void
    {
        [, $agentUser, $company] = $this->setupAgentAndCustomer();

        $response = $this->actingAs($agentUser, 'sanctum')
            ->postJson('/api/agent/individual-contracts', [
                'company_id'      => $company->id,
                'contract_date'   => '2026-05-01',
                'start_date'      => '2026-05-01',
                'end_date'        => '2027-04-30',
                'monthly_fee'     => 30000,
                'commission_rate' => 0.20,
                'agent_signed'    => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.agent_signed', true);

        $this->assertDatabaseHas('individual_contracts', [
            'agent_id'   => $agentUser->agent_id,
            'company_id' => $company->id,
        ]);
        $contract = IndividualContract::first();
        $this->assertNotNull($contract->agent_signed_at);
        $this->assertSame($agentUser->id, $contract->created_by);
    }

    public function test_agent_cannot_create_contract_for_other_agents_customer(): void
    {
        [, $agentUser] = $this->setupAgentAndCustomer();
        // 別代理店の顧客
        $otherAgent = Agent::factory()->create();
        $otherCompany = Company::create([
            'name'     => '他代理店の顧客',
            'agent_id' => $otherAgent->id,
        ]);

        $response = $this->actingAs($agentUser, 'sanctum')
            ->postJson('/api/agent/individual-contracts', [
                'company_id' => $otherCompany->id,
            ]);

        $response->assertStatus(403);
        $this->assertSame(0, IndividualContract::count());
    }

    public function test_agent_cannot_create_duplicate(): void
    {
        [$agent, $agentUser, $company] = $this->setupAgentAndCustomer();
        IndividualContract::create([
            'agent_id'   => $agent->id,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($agentUser, 'sanctum')
            ->postJson('/api/agent/individual-contracts', [
                'company_id' => $company->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_agent_index_returns_only_own_contracts(): void
    {
        [$agent, $agentUser, $company] = $this->setupAgentAndCustomer();
        IndividualContract::create(['agent_id' => $agent->id, 'company_id' => $company->id]);

        // 別代理店の契約書も用意
        $otherAgent = Agent::factory()->create();
        $otherCompany = Company::create(['name' => '他社', 'agent_id' => $otherAgent->id]);
        IndividualContract::create(['agent_id' => $otherAgent->id, 'company_id' => $otherCompany->id]);

        $response = $this->actingAs($agentUser, 'sanctum')->getJson('/api/agent/individual-contracts');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($agent->id, $data[0]['agent_id']);
    }

    public function test_agent_can_update_own_signed_only(): void
    {
        [$agent, $agentUser, $company] = $this->setupAgentAndCustomer();
        $contract = IndividualContract::create([
            'agent_id'        => $agent->id,
            'company_id'      => $company->id,
            'soship_signed'   => false,
            'agent_signed'    => false,
            'customer_signed' => false,
        ]);

        $response = $this->actingAs($agentUser, 'sanctum')
            ->putJson("/api/agent/individual-contracts/{$contract->id}", [
                'agent_signed'    => true,
                // 以下は無視されるべき (validation rule にないので drop)
                'soship_signed'   => true,
                'customer_signed' => true,
            ]);

        $response->assertStatus(200);
        $fresh = $contract->fresh();
        $this->assertTrue($fresh->agent_signed);
        $this->assertNotNull($fresh->agent_signed_at);
        $this->assertFalse($fresh->soship_signed, '代理店からはソーシップ署名フラグを変更できない');
        $this->assertFalse($fresh->customer_signed, '代理店からは顧客署名フラグを変更できない');
    }

    public function test_agent_cannot_access_other_agents_contract(): void
    {
        [, $agentUser] = $this->setupAgentAndCustomer();
        $otherAgent = Agent::factory()->create();
        $otherCompany = Company::create(['name' => '他社', 'agent_id' => $otherAgent->id]);
        $other = IndividualContract::create([
            'agent_id'   => $otherAgent->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->actingAs($agentUser, 'sanctum')
            ->getJson("/api/agent/individual-contracts/{$other->id}")
            ->assertStatus(403);
        $this->actingAs($agentUser, 'sanctum')
            ->putJson("/api/agent/individual-contracts/{$other->id}", ['terms' => 'hijack'])
            ->assertStatus(403);
        $this->actingAs($agentUser, 'sanctum')
            ->deleteJson("/api/agent/individual-contracts/{$other->id}")
            ->assertStatus(403);
    }

    public function test_agent_can_upload_and_delete_document(): void
    {
        Storage::fake('local');
        [$agent, $agentUser, $company] = $this->setupAgentAndCustomer();
        $contract = IndividualContract::create(['agent_id' => $agent->id, 'company_id' => $company->id]);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');
        $upload = $this->actingAs($agentUser, 'sanctum')
            ->postJson("/api/agent/individual-contracts/{$contract->id}/document", ['file' => $file]);
        $upload->assertStatus(200);

        $fresh = $contract->fresh();
        $this->assertNotNull($fresh->contract_document_path);
        Storage::disk('local')->assertExists($fresh->contract_document_path);

        $delete = $this->actingAs($agentUser, 'sanctum')
            ->deleteJson("/api/agent/individual-contracts/{$contract->id}/document");
        $delete->assertStatus(200);
        $this->assertNull($contract->fresh()->contract_document_path);
    }

    public function test_master_can_change_all_signed_flags(): void
    {
        [$agent, , $company] = $this->setupAgentAndCustomer();
        $master = $this->setupMaster();
        $contract = IndividualContract::create([
            'agent_id'   => $agent->id,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/master/individual-contracts/{$contract->id}", [
                'soship_signed'   => true,
                'customer_signed' => true,
            ]);

        $response->assertStatus(200);
        $fresh = $contract->fresh();
        $this->assertTrue($fresh->soship_signed);
        $this->assertNotNull($fresh->soship_signed_at);
        $this->assertTrue($fresh->customer_signed);
        $this->assertNotNull($fresh->customer_signed_at);
    }

    public function test_master_index_returns_all_contracts_with_agent_filter(): void
    {
        $master = $this->setupMaster();
        $a1 = Agent::factory()->create();
        $c1 = Company::create(['name' => 'C1', 'agent_id' => $a1->id]);
        IndividualContract::create(['agent_id' => $a1->id, 'company_id' => $c1->id]);

        $a2 = Agent::factory()->create();
        $c2 = Company::create(['name' => 'C2', 'agent_id' => $a2->id]);
        IndividualContract::create(['agent_id' => $a2->id, 'company_id' => $c2->id]);

        $all = $this->actingAs($master, 'sanctum')->getJson('/api/admin/master/individual-contracts');
        $all->assertStatus(200);
        $this->assertSame(2, $all->json('data.total'));

        $filtered = $this->actingAs($master, 'sanctum')
            ->getJson("/api/admin/master/individual-contracts?agent_id={$a1->id}");
        $filtered->assertStatus(200);
        $this->assertSame(1, $filtered->json('data.total'));
    }

    public function test_non_master_admin_is_forbidden_on_master_endpoint(): void
    {
        // 普通の admin (is_master=false)
        $admin = User::create([
            'username'  => 'admin_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '一般管理',
            'user_type' => 'admin',
            'is_master' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/master/individual-contracts');

        $response->assertStatus(403);
    }
}
