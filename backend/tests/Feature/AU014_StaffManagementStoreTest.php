<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU014: /api/admin/staff の POST（StaffManagementController::store）
 *
 * 差分カテゴリ: api
 * 背景: /admin/staff-management ページの新規スタッフ登録で POST /api/admin/staff
 *       を叩いていたが、ルートと store メソッドが存在せず 405 を返していた。
 *       リクエストユーザーの classroom_id をデフォルトにしてスタッフを作成する
 *       挙動を追加した。
 */
class AU014_StaffManagementStoreTest extends TestCase
{
    use RefreshDatabase;

    private function adminInClassroom(Classroom $classroom, bool $isMaster = false): User
    {
        return User::create([
            'username' => 'admin_au014_' . uniqid(),
            'password' => bcrypt('pass'),
            'full_name' => 'Admin',
            'user_type' => 'admin',
            'is_master' => $isMaster,
            'is_company_admin' => false,
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
    }

    public function test_store_uses_auth_user_classroom_when_not_provided(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->adminInClassroom($classroom);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/staff', [
                'username' => 'new_staff_1',
                'password' => 'pass123',
                'full_name' => '新人スタッフ',
                'email' => 'new@example.com',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('new_staff_1', $data['username']);
        $this->assertEquals($classroom->id, $data['classroom_id']);
        $this->assertEquals('staff', $data['user_type']);
        $this->assertFalse((bool) $data['is_master']);
    }

    public function test_store_accepts_explicit_classroom_id(): void
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'A2', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->adminInClassroom($c1);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/staff', [
                'classroom_id' => $c2->id,
                'username' => 'new_staff_2',
                'password' => 'pass123',
                'full_name' => 'A2 のスタッフ',
            ]);

        $response->assertStatus(201);
        $this->assertEquals($c2->id, $response->json('data.classroom_id'));
    }

    public function test_store_rejects_when_classroom_has_no_company(): void
    {
        $classroom = Classroom::create(['classroom_name' => '企業なし', 'company_id' => null, 'is_active' => true]);
        $admin = $this->adminInClassroom($classroom);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/staff', [
                'username' => 'orphan_staff',
                'password' => 'pass123',
                'full_name' => '企業なし教室のスタッフ',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_store_rejects_when_auth_user_has_no_classroom(): void
    {
        $admin = User::create([
            'username' => 'master_no_classroom',
            'password' => bcrypt('pass'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/staff', [
                'username' => 'nowhere_staff',
                'password' => 'pass123',
                'full_name' => '所属なし',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_store_rejects_duplicate_username(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->adminInClassroom($classroom);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/staff', [
                'username' => 'dupe_user',
                'password' => 'pass123',
                'full_name' => '重複1',
            ])->assertStatus(201);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/staff', [
                'username' => 'dupe_user',
                'password' => 'pass123',
                'full_name' => '重複2',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('username');
    }
}
