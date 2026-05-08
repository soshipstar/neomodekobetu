<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase B: 代理店ユーザーが自身の代理店契約書 PDF をアップロード/削除できる
 *
 * 差分カテゴリ: api
 * 背景: 既存 Admin\AgentController::uploadContractDocument と同じロジックを
 *       Agent\AgentDashboardController に移植し、代理店ロールから直接
 *       自社契約書を管理できるようにする。マスター管理者は引き続き
 *       /admin/agents 経由で全代理店の契約書を管理可能 (変更なし)。
 */
class B121_AgentSelfContractUploadTest extends TestCase
{
    use RefreshDatabase;

    private function setupAgent(?string $existingPath = null): array
    {
        $agent = Agent::factory()->create([
            'name'                   => '代理店A',
            'contract_document_path' => $existingPath,
        ]);
        $user = User::create([
            'username'  => 'agent_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '代理店ユーザー',
            'user_type' => 'agent',
            'agent_id'  => $agent->id,
            'is_active' => true,
        ]);
        return [$agent, $user];
    }

    public function test_agent_can_upload_contract_pdf(): void
    {
        Storage::fake('local');
        [$agent, $user] = $this->setupAgent();

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/agent/contract-document', ['file' => $file]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $fresh = $agent->fresh();
        $this->assertNotNull($fresh->contract_document_path);
        $this->assertStringStartsWith('agent-contracts/' . $agent->id . '/', $fresh->contract_document_path);
        Storage::disk('local')->assertExists($fresh->contract_document_path);
    }

    public function test_uploading_replaces_existing_file(): void
    {
        Storage::fake('local');
        // 既存ファイルを作る
        $oldPath = 'agent-contracts/legacy/old.pdf';
        Storage::disk('local')->put($oldPath, 'old content');
        [$agent, $user] = $this->setupAgent($oldPath);

        $file = UploadedFile::fake()->create('new.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/agent/contract-document', ['file' => $file]);

        $response->assertStatus(200);

        $fresh = $agent->fresh();
        $this->assertNotSame($oldPath, $fresh->contract_document_path);
        Storage::disk('local')->assertExists($fresh->contract_document_path);
        // 旧ファイルは削除される
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_non_pdf_is_rejected(): void
    {
        Storage::fake('local');
        [, $user] = $this->setupAgent();

        $file = UploadedFile::fake()->create('image.png', 100, 'image/png');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/agent/contract-document', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('local');
        [, $user] = $this->setupAgent();

        // 11MB → 10MB制限超過
        $file = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/agent/contract-document', ['file' => $file]);

        $response->assertStatus(422);
    }

    public function test_agent_can_delete_contract(): void
    {
        Storage::fake('local');
        $existingPath = 'agent-contracts/x/existing.pdf';
        Storage::disk('local')->put($existingPath, 'pdf content');
        [$agent, $user] = $this->setupAgent($existingPath);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/agent/contract-document');

        $response->assertStatus(200);
        $this->assertNull($agent->fresh()->contract_document_path);
        Storage::disk('local')->assertMissing($existingPath);
    }

    public function test_delete_when_no_contract_is_422(): void
    {
        [, $user] = $this->setupAgent(null);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/agent/contract-document');

        $response->assertStatus(422);
    }

    public function test_non_agent_user_cannot_upload(): void
    {
        Storage::fake('local');
        $staff = User::create([
            'username'  => 'staff_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/agent/contract-document', ['file' => $file]);

        $response->assertStatus(403);
    }
}
