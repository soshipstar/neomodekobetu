<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU-014: 国保連請求システム(kiduriacount) SSO チケット発行のアクセス制御。
 *
 * 要望: 請求システムは通常管理者も利用できるようにする。
 * 管理者(user_type='admin')は通常管理者・企業管理者(is_company_admin)・マスター(is_master)
 * を含み、いずれも利用可。スタッフ・保護者・生徒は利用不可。
 *
 * 差分カテゴリ: auth
 */
class AU014_BillingSsoTicketAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function makeUser(string $type, array $attrs = []): User
    {
        return User::create(array_merge([
            'username'  => $type . '_sso_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '太郎',
            'user_type' => $type,
            'is_active' => true,
        ], $attrs));
    }

    public function test_regular_admin_can_get_ticket(): void
    {
        // 通常管理者: user_type=admin, フラグなし, 事業所未所属
        $admin = $this->makeUser('admin', ['is_master' => false, 'is_company_admin' => false]);

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/sso/ticket');

        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data.ticket'));
    }

    public function test_company_admin_and_master_can_get_ticket(): void
    {
        $company = $this->makeUser('admin', ['is_company_admin' => true]);
        $master = $this->makeUser('admin', ['is_master' => true]);

        $this->actingAs($company, 'sanctum')->postJson('/api/sso/ticket')->assertStatus(200);
        $this->actingAs($master, 'sanctum')->postJson('/api/sso/ticket')->assertStatus(200);
    }

    public function test_staff_cannot_get_ticket(): void
    {
        $staff = $this->makeUser('staff');

        $this->actingAs($staff, 'sanctum')->postJson('/api/sso/ticket')->assertStatus(403);
    }

    public function test_guardian_cannot_get_ticket(): void
    {
        $guardian = $this->makeUser('guardian');

        $this->actingAs($guardian, 'sanctum')->postJson('/api/sso/ticket')->assertStatus(403);
    }

    public function test_admin_blocked_when_classroom_billing_disabled(): void
    {
        // 事業所単位の利用可否ゲート: 所属事業所で無効なら管理者でも不可
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '教室A', 'company_id' => $company->id,
            'is_active' => true, 'billing_system_enabled' => false,
        ]);
        $admin = $this->makeUser('admin', ['classroom_id' => $classroom->id]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/sso/ticket')->assertStatus(403);
    }
}
