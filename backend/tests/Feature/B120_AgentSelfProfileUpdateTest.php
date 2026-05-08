<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A: 代理店ユーザーが自身のプロフィールを編集できる
 *
 * 差分カテゴリ: api
 * 背景: 代理店向けに自社情報の編集機能を提供する。利益相反・運用裁定に関わる項目
 *       (default_commission_rate / contract_terms / is_active / code /
 *        contract_document_path) は編集不可とする。
 */
class B120_AgentSelfProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function setupAgent(array $overrides = []): array
    {
        $agent = Agent::factory()->create(array_merge([
            'name'                    => '代理店A',
            'code'                    => 'ag_test_' . uniqid(),
            'contact_name'            => '担当太郎',
            'contact_email'           => 'old@example.com',
            'contact_phone'           => '03-1234-5678',
            'address'                 => '東京都千代田区1-1-1',
            'default_commission_rate' => 0.20,
            'contract_terms'          => '初期契約条件',
            'is_active'               => true,
        ], $overrides));

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

    public function test_agent_can_update_allowed_profile_fields(): void
    {
        [$agent, $user] = $this->setupAgent();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/agent/profile', [
                'name'          => '代理店A (新名称)',
                'contact_name'  => '新担当',
                'contact_email' => 'new@example.com',
                'contact_phone' => '090-1111-2222',
                'address'       => '東京都港区2-2-2',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', '代理店A (新名称)');
        $response->assertJsonPath('data.contact_email', 'new@example.com');

        $fresh = $agent->fresh();
        $this->assertSame('代理店A (新名称)', $fresh->name);
        $this->assertSame('新担当', $fresh->contact_name);
        $this->assertSame('new@example.com', $fresh->contact_email);
        $this->assertSame('090-1111-2222', $fresh->contact_phone);
        $this->assertSame('東京都港区2-2-2', $fresh->address);
    }

    public function test_agent_cannot_modify_protected_fields(): void
    {
        [$agent, $user] = $this->setupAgent();

        $originalCode = $agent->code;

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/agent/profile', [
                'name'                    => 'ok',
                // 以下は無視されるべき
                'code'                    => 'ag_hijack',
                'default_commission_rate' => 0.99,
                'contract_terms'          => '勝手に書き換えた条件',
                'is_active'               => false,
                'contract_document_path'  => 'attempted/inject.pdf',
            ]);

        $response->assertStatus(200);

        $fresh = $agent->fresh();
        $this->assertSame($originalCode, $fresh->code, 'code は変更不可');
        $this->assertEquals(0.20, (float) $fresh->default_commission_rate, '手数料率は変更不可');
        $this->assertSame('初期契約条件', $fresh->contract_terms, '契約条件は変更不可');
        $this->assertTrue($fresh->is_active, 'is_active は変更不可');
        $this->assertNull($fresh->contract_document_path, '契約書PDFパスは変更不可');
    }

    public function test_invalid_email_is_rejected(): void
    {
        [, $user] = $this->setupAgent();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/agent/profile', [
                'contact_email' => 'not-an-email',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contact_email');
    }

    public function test_non_agent_user_is_forbidden(): void
    {
        // 一般 staff ユーザー
        $staff = User::create([
            'username'  => 'staff_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson('/api/agent/profile', ['name' => 'attempt']);

        $response->assertStatus(403);
    }

    public function test_agent_user_without_agent_id_is_unprocessable(): void
    {
        // agent ロールだが所属未設定
        $orphan = User::create([
            'username'  => 'agent_orphan_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '所属なし代理店ユーザー',
            'user_type' => 'agent',
            'agent_id'  => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($orphan, 'sanctum')
            ->putJson('/api/agent/profile', ['name' => 'attempt']);

        $response->assertStatus(422);
    }

    public function test_partial_update_does_not_clear_other_fields(): void
    {
        [$agent, $user] = $this->setupAgent();

        // contact_email だけ更新
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/agent/profile', [
                'contact_email' => 'partial@example.com',
            ]);

        $response->assertStatus(200);
        $fresh = $agent->fresh();
        $this->assertSame('partial@example.com', $fresh->contact_email);
        $this->assertSame('担当太郎', $fresh->contact_name, '未指定の項目は維持される');
        $this->assertSame('東京都千代田区1-1-1', $fresh->address);
    }
}
