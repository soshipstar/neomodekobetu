<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * B-113: 代理店契約書PDFのアップロード/ダウンロード/削除
 * - PDFは文字化けしないこと（アップロードしたバイト列とダウンロードしたバイト列が完全一致）
 * - マスター管理者のみアップロード/削除可能
 * - 代理店ユーザーは自代理店のPDFのみダウンロード可能
 * - 他代理店ユーザーや通常管理者はアクセス不可
 */
class B113_AgentContractDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 'local' ディスクをfakeしてテスト分離
        Storage::fake('local');
    }

    private function makeMaster(): User
    {
        return User::create([
            'username' => 'master_b113_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    private function makeNormalAdmin(): User
    {
        $c = Classroom::create(['classroom_name' => 'cls_b113', 'is_active' => true]);
        return User::create([
            'username' => 'admin_b113_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'Admin',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $c->id,
            'is_active' => true,
        ]);
    }

    private function makeAgentUser(Agent $agent): User
    {
        return User::create([
            'username' => 'agent_b113_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'AgentUser',
            'user_type' => 'agent',
            'is_master' => false,
            'is_company_admin' => false,
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);
    }

    /**
     * 実物に近いバイト列のPDFを作る（最低限PDFとして認識される構造）。
     * 中身に日本語コメントを入れて、エンコード変換が走らないことも確認したい。
     */
    private function fakePdfContent(): string
    {
        $body = "%PDF-1.4\n"
            ."% 日本語コメント: 代理店契約書テスト\n"
            ."1 0 obj<<>>endobj\n"
            ."xref\n0 1\n0000000000 65535 f \n"
            ."trailer<</Size 1/Root 1 0 R>>\n"
            ."startxref\n0\n%%EOF\n";
        return $body;
    }

    public function test_normal_admin_cannot_upload(): void
    {
        $admin = $this->makeNormalAdmin();
        $agent = Agent::factory()->create();
        $file = UploadedFile::fake()->createWithContent('contract.pdf', $this->fakePdfContent());

        $this->actingAs($admin)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file])
            ->assertStatus(403);
    }

    public function test_master_can_upload_and_download_pdf_with_byte_identity(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create(['name' => 'A代理店']);
        $original = $this->fakePdfContent();
        $file = UploadedFile::fake()->createWithContent('contract.pdf', $original);

        $up = $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file])
            ->assertStatus(200);

        $agent->refresh();
        $this->assertNotNull($agent->contract_document_path);
        Storage::disk('local')->assertExists($agent->contract_document_path);

        // ダウンロードしてバイト列が完全一致するか
        $down = $this->actingAs($master)
            ->get('/api/admin/master/agents/'.$agent->id.'/contract-document')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $downloaded = $down->streamedContent();
        $this->assertSame(
            md5($original),
            md5($downloaded),
            'アップロードしたPDFのバイト列とダウンロードしたバイト列が一致しない（文字化けの可能性）'
        );
        $this->assertSame(strlen($original), strlen($downloaded));
    }

    public function test_download_filename_is_ascii_safe(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create(['name' => '日本語の代理店名！@#$%']);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', $this->fakePdfContent());

        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file])
            ->assertStatus(200);

        $down = $this->actingAs($master)
            ->get('/api/admin/master/agents/'.$agent->id.'/contract-document')
            ->assertStatus(200);

        $disposition = $down->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        // ファイル名が ASCII のみ（[^A-Za-z0-9_\-]/u を _ に置換済み）
        // 「agent-contract_」プレフィックスが含まれ、日本語そのままの「日本語」は含まれない
        $this->assertStringContainsString('agent-contract_', $disposition);
        $this->assertStringNotContainsString('日本語', $disposition);
    }

    public function test_only_pdf_mime_type_accepted(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create();
        // PNG 偽装
        $file = UploadedFile::fake()->image('not-pdf.png');

        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_replacing_uploads_old_file_deleted(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create();
        $file1 = UploadedFile::fake()->createWithContent('old.pdf', $this->fakePdfContent());
        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file1])
            ->assertStatus(200);
        $oldPath = $agent->fresh()->contract_document_path;
        Storage::disk('local')->assertExists($oldPath);

        $file2 = UploadedFile::fake()->createWithContent('new.pdf', $this->fakePdfContent().'extra');
        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file2])
            ->assertStatus(200);

        $newPath = $agent->fresh()->contract_document_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_master_can_delete_contract(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create();
        $file = UploadedFile::fake()->createWithContent('c.pdf', $this->fakePdfContent());
        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file])
            ->assertStatus(200);

        $path = $agent->fresh()->contract_document_path;
        $this->actingAs($master)
            ->delete('/api/admin/master/agents/'.$agent->id.'/contract-document')
            ->assertStatus(200);

        $this->assertNull($agent->fresh()->contract_document_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_agent_user_can_download_own_contract_via_agent_endpoint(): void
    {
        $master = $this->makeMaster();
        $agent = Agent::factory()->create();
        $agentUser = $this->makeAgentUser($agent);
        $original = $this->fakePdfContent();
        $file = UploadedFile::fake()->createWithContent('c.pdf', $original);

        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agent->id.'/contract-document', ['file' => $file])
            ->assertStatus(200);

        $down = $this->actingAs($agentUser)
            ->get('/api/agent/contract-document')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(md5($original), md5($down->streamedContent()));
    }

    public function test_agent_user_cannot_download_other_agent_via_master_endpoint(): void
    {
        $master = $this->makeMaster();
        $agentSelf = Agent::factory()->create();
        $agentOther = Agent::factory()->create();
        $userSelf = $this->makeAgentUser($agentSelf);

        $file = UploadedFile::fake()->createWithContent('c.pdf', $this->fakePdfContent());
        $this->actingAs($master)
            ->post('/api/admin/master/agents/'.$agentOther->id.'/contract-document', ['file' => $file])
            ->assertStatus(200);

        // /api/admin/master/* は user_type:admin が必須なので、 agent ユーザーは middleware で 403
        $this->actingAs($userSelf)
            ->get('/api/admin/master/agents/'.$agentOther->id.'/contract-document')
            ->assertStatus(403);
    }
}
