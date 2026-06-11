<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU017: requireMaster 集約の回帰テスト (ARCH-AUTH-02)
 *
 * 差分カテゴリ: auth
 *
 * 完全一致だった 5 つの Admin コントローラ
 * (AdminAccount / Company / Classroom / IndividualContract / StaffAccount) の
 * private requireMaster() を削除し、基底 Controller::requireMaster()
 * (isMasterAdmin 判定) へ集約した。
 *
 * 集約は「挙動不変」が前提なので、代表として CompanyController を経由し
 * 「マスターのみ通過・非マスター管理者は 403・レスポンス形も従来同一」を検証する。
 * これが保てていれば、同一実装だった他 4 コントローラも同様に機能する。
 */
class AU017_RequireMasterConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides): User
    {
        return User::create(array_merge([
            'username'  => 'u_' . substr(md5(serialize($overrides)), 0, 8),
            'password'  => bcrypt('pass'),
            'full_name' => 'テスト',
            'is_active' => true,
        ], $overrides));
    }

    public function test_master_admin_passes_require_master(): void
    {
        $master = $this->makeUser([
            'user_type' => 'admin',
            'is_master' => true,
        ]);

        // マスターは requireMaster を通過する (403 ではない)。
        $res = $this->actingAs($master, 'sanctum')->getJson('/api/admin/companies');
        $this->assertNotSame(403, $res->getStatusCode(), 'マスター管理者が requireMaster で 403 になっています (集約で挙動が壊れた)。');
    }

    public function test_non_master_admin_is_denied(): void
    {
        $admin = $this->makeUser([
            'user_type' => 'admin',
            'is_master' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/companies')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'マスター管理者権限が必要です。']);
    }

    public function test_company_admin_is_denied_master_only_endpoint(): void
    {
        // 企業管理者 (is_company_admin=true) も requireMaster は通さない (master 限定)。
        $companyAdmin = $this->makeUser([
            'user_type'        => 'admin',
            'is_master'        => false,
            'is_company_admin' => true,
        ]);

        $this->actingAs($companyAdmin, 'sanctum')
            ->getJson('/api/admin/companies')
            ->assertStatus(403);
    }
}
