<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * B137: 企業管理者がタブレットアカウントを CRUD できるようにする
 *
 * 差分カテゴリ: logic
 *
 * 報告: 「タブレットユーザーが企業管理者から追加できない」
 *
 * 原因 (BE): index/update/toggle が `$account->classroom_id !== $user->classroom_id`
 * (= 自分の所属教室と完全一致のみ) で認可しており、企業管理者でも自社の他教室の
 * タブレットアカウントを参照・編集できなかった。store() には認可チェック自体が無く、
 * しかし FE 側でリクエストに classroom_id が含まれていなかったため必須バリデーションで
 * 422 となっていた (FE 側修正は別 commit)。
 *
 * 修正: 認可判定を accessibleClassroomIds() ベースから、自社全教室を返す
 * manageableClassroomIds() に変更。マスター=全教室 / 企業管理者=自社全教室 /
 * 通常管理者=自所属教室のみ、で 3 段階の権限を実装する。
 */
class B137_TabletAccountCompanyAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 企業 A + 2教室 (A1, A2) + 企業管理者 (A1所属, is_company_admin=true)
     *
     * @return array{company:Company, c1:Classroom, c2:Classroom, companyAdmin:User}
     */
    private function fixture(): array
    {
        $company = Company::create(['name' => 'B137企業']);
        $c1 = Classroom::create(['classroom_name' => 'B137_A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'B137_A2', 'company_id' => $company->id, 'is_active' => true]);

        $companyAdmin = User::create([
            'username'         => 'admin_b137_' . uniqid(),
            'password'         => Hash::make('p'),
            'full_name'        => 'B137企業管理者',
            'user_type'        => 'admin',
            'is_master'        => false,
            'is_company_admin' => true,
            'classroom_id'     => $c1->id,
            'is_active'        => true,
        ]);

        return compact('company', 'c1', 'c2', 'companyAdmin');
    }

    /**
     * 企業管理者は自社別教室にタブレットアカウントを作成できる
     */
    public function test_company_admin_can_create_tablet_in_sibling_classroom(): void
    {
        ['companyAdmin' => $admin, 'c2' => $c2] = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id' => $c2->id,
                'username'     => 'tablet_b137_a2',
                'password'     => 'pass1234',
                'full_name'    => 'B137 A2 タブレット',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.user_type', 'tablet');
        $response->assertJsonPath('data.classroom_id', $c2->id);
    }

    /**
     * 企業管理者は別企業の教室には作成できない (403)
     */
    public function test_company_admin_cannot_create_tablet_in_other_company(): void
    {
        ['companyAdmin' => $admin] = $this->fixture();

        $otherCompany = Company::create(['name' => '別企業B137']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '別企業教室',
            'company_id'     => $otherCompany->id,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id' => $otherClassroom->id,
                'username'     => 'tablet_b137_other',
                'password'     => 'pass1234',
                'full_name'    => '別企業タブレット',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 企業管理者の index は自社全教室のタブレットアカウントを返す
     */
    public function test_company_admin_index_includes_sibling_classroom_accounts(): void
    {
        ['companyAdmin' => $admin, 'c1' => $c1, 'c2' => $c2] = $this->fixture();

        // c1, c2 にそれぞれタブレットを作成
        User::create([
            'username' => 'tablet_c1_' . uniqid(),
            'password' => Hash::make('p'),
            'full_name' => 'C1タブレット',
            'user_type' => 'tablet',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);
        User::create([
            'username' => 'tablet_c2_' . uniqid(),
            'password' => Hash::make('p'),
            'full_name' => 'C2タブレット',
            'user_type' => 'tablet',
            'classroom_id' => $c2->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/tablet-accounts');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('full_name')->all();
        $this->assertContains('C1タブレット', $names);
        $this->assertContains('C2タブレット', $names);
    }

    /**
     * 通常管理者 (is_company_admin=false) は自分の教室のタブレットアカウントだけ操作可能
     */
    public function test_regular_admin_only_sees_own_classroom_accounts(): void
    {
        ['c1' => $c1, 'c2' => $c2] = $this->fixture();

        $regularAdmin = User::create([
            'username'         => 'admin_b137_reg_' . uniqid(),
            'password'         => Hash::make('p'),
            'full_name'        => 'B137通常管理者',
            'user_type'        => 'admin',
            'is_master'        => false,
            'is_company_admin' => false,
            'classroom_id'     => $c1->id,
            'is_active'        => true,
        ]);

        // c2 にタブレットを作ろうとして 403
        $response = $this->actingAs($regularAdmin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id' => $c2->id,
                'username'     => 'tablet_reg_attempt',
                'password'     => 'pass1234',
                'full_name'    => '権限外作成試行',
            ]);

        $response->assertStatus(403);

        // c1 にはOK
        $response2 = $this->actingAs($regularAdmin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id' => $c1->id,
                'username'     => 'tablet_reg_ok_' . uniqid(),
                'password'     => 'pass1234',
                'full_name'    => '通常管理者作成',
            ]);

        $response2->assertStatus(201);
    }
}
