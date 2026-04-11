<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU011: スタッフアカウント作成・更新時の所属教室と企業整合性チェック
 *
 * 差分カテゴリ: api
 * 背景: /admin/staff-accounts で所属企業のない教室（company_id = null）を
 *       スタッフに割り当てられてしまう問題があった。StaffAccountController の
 *       validation は 'classroom_id' => 'nullable|exists:classrooms,id' のみで、
 *       教室が企業に所属しているかを保証していなかった。
 */
class AU011_StaffAccountClassroomCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::create([
            'username' => 'master_au011',
            'password' => bcrypt('pass123'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    public function test_staff_creation_without_classroom_is_rejected(): void
    {
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/staff-accounts', [
                'username' => 'staff_no_class',
                'password' => 'pass123',
                'full_name' => '教室なしスタッフ',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_staff_creation_with_classroom_having_null_company_is_rejected(): void
    {
        $master = $this->master();
        $classroom = Classroom::create([
            'classroom_name' => '企業なし教室',
            'company_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/staff-accounts', [
                'username' => 'staff_orphan',
                'password' => 'pass123',
                'full_name' => '企業なし教室のスタッフ',
                'classroom_id' => $classroom->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_staff_creation_with_classroom_in_company_succeeds(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => 'テスト企業']);
        $classroom = Classroom::create([
            'classroom_name' => '企業あり教室',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/staff-accounts', [
                'username' => 'staff_with_company',
                'password' => 'pass123',
                'full_name' => '企業ありスタッフ',
                'classroom_id' => $classroom->id,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals($classroom->id, $data['classroom_id']);
        $this->assertEquals($company->id, $data['company_id']);
    }

    public function test_staff_update_to_classroom_with_null_company_is_rejected(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => 'テスト企業']);
        $validClassroom = Classroom::create([
            'classroom_name' => '企業あり教室',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $orphanClassroom = Classroom::create([
            'classroom_name' => '企業なし教室',
            'company_id' => null,
            'is_active' => true,
        ]);
        $staff = User::create([
            'username' => 'existing_staff',
            'password' => bcrypt('pass'),
            'full_name' => '既存スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $validClassroom->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/staff-accounts/{$staff->id}", [
                'classroom_id' => $orphanClassroom->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');

        // 元の教室のまま変わっていないこと
        $this->assertEquals($validClassroom->id, $staff->fresh()->classroom_id);
    }

    public function test_staff_update_without_classroom_id_does_not_trigger_check(): void
    {
        // 教室以外の項目（氏名など）だけ更新する場合は classroom_id チェックは走らない
        $master = $this->master();
        $company = Company::create(['name' => 'テスト企業']);
        $classroom = Classroom::create([
            'classroom_name' => '企業あり教室',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $staff = User::create([
            'username' => 'existing_staff2',
            'password' => bcrypt('pass'),
            'full_name' => '既存スタッフ2',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/staff-accounts/{$staff->id}", [
                'full_name' => '名前変更後',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('名前変更後', $staff->fresh()->full_name);
    }
}
