<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * AU010: 管理者アカウント作成・更新時の所属企業・所属教室必須チェック
 *
 * 差分カテゴリ: auth / logic
 * 背景: /admin/admin-accounts で所属企業がない管理者アカウントが作成できてしまう問題があった。
 *       users テーブルには company_id カラムが存在せず、classroom 経由で導出しているため、
 *       classroom_id が null あるいは classroom.company_id が null の場合に
 *       所属企業なしの管理者が作られてしまう。
 */
class AU010_AdminAccountClassroomCompanyTest extends TestCase
{
    use DatabaseMigrations;

    private function master(): User
    {
        return User::create([
            'username' => 'master_user',
            'password' => bcrypt('pass123'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    public function test_normal_admin_creation_without_classroom_is_rejected(): void
    {
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/admin-accounts', [
                'username' => 'admin_no_class',
                'password' => 'pass123',
                'full_name' => '教室なし管理者',
                'is_master' => false,
                'is_company_admin' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_normal_admin_creation_with_classroom_having_null_company_is_rejected(): void
    {
        $master = $this->master();
        $classroom = Classroom::create([
            'classroom_name' => '企業なし教室',
            'company_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/admin-accounts', [
                'username' => 'admin_orphan_class',
                'password' => 'pass123',
                'full_name' => '企業なし教室の管理者',
                'is_master' => false,
                'is_company_admin' => false,
                'classroom_id' => $classroom->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_normal_admin_creation_with_classroom_in_company_succeeds(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => 'テスト企業']);
        $classroom = Classroom::create([
            'classroom_name' => '企業あり教室',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/admin-accounts', [
                'username' => 'admin_with_company',
                'password' => 'pass123',
                'full_name' => '企業あり管理者',
                'is_master' => false,
                'is_company_admin' => false,
                'classroom_id' => $classroom->id,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals($company->id, $data['company_id']);
    }

    public function test_master_admin_can_be_created_without_classroom(): void
    {
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/admin-accounts', [
                'username' => 'another_master',
                'password' => 'pass123',
                'full_name' => '別のマスター',
                'is_master' => true,
                'is_company_admin' => false,
            ]);

        $response->assertStatus(201);
    }

    public function test_update_to_unset_classroom_for_normal_admin_is_rejected(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => 'テスト企業']);
        $classroom = Classroom::create([
            'classroom_name' => '企業あり教室',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $admin = User::create([
            'username' => 'existing_admin',
            'password' => bcrypt('pass'),
            'full_name' => '既存管理者',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/admin-accounts/{$admin->id}", [
                'classroom_id' => null,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }
}
