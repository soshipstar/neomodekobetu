<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU012: ユーザーの所属教室同期 API で企業境界を強制するテスト
 *
 * 差分カテゴリ: api
 * 背景: /admin/users/{user}/classrooms の PUT（UserClassroomController::sync）で、
 *       他企業の教室を混ぜて送ってもそのまま通ってしまう問題があった。
 *       UserClassroomModal（複数教室割当UI）から呼ばれる。
 */
class AU012_UserClassroomSyncCompanyBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::create([
            'username' => 'master_au012',
            'password' => bcrypt('pass123'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    public function test_sync_succeeds_with_classrooms_in_same_company(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室2', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_a',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフA',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/users/{$staff->id}/classrooms", [
                'classroom_ids' => [$c1->id, $c2->id],
            ]);

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing([$c1->id, $c2->id], $staff->classrooms()->pluck('classrooms.id')->toArray());
    }

    public function test_sync_rejects_classroom_from_another_company(): void
    {
        $master = $this->master();
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $c1 = Classroom::create(['classroom_name' => '教室A1', 'company_id' => $companyA->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室B1', 'company_id' => $companyB->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_b',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフB',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/users/{$staff->id}/classrooms", [
                'classroom_ids' => [$c1->id, $c2->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_ids');

        // 変更されていないこと
        $this->assertEquals([], $staff->classrooms()->pluck('classrooms.id')->toArray());
    }

    public function test_sync_rejects_orphan_classroom(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $orphan = Classroom::create(['classroom_name' => '企業なし', 'company_id' => null, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_c',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフC',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/users/{$staff->id}/classrooms", [
                'classroom_ids' => [$c1->id, $orphan->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_ids');
    }

    public function test_sync_rejects_when_user_has_no_company(): void
    {
        // ユーザーの classroom.company_id が null のケース（legacy データ想定）
        $master = $this->master();
        $orphanClassroom = Classroom::create(['classroom_name' => '企業なし', 'company_id' => null, 'is_active' => true]);
        $company = Company::create(['name' => '企業A']);
        $validClassroom = Classroom::create(['classroom_name' => '教室A1', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_orphan',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフOrphan',
            'user_type' => 'staff',
            'classroom_id' => $orphanClassroom->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/users/{$staff->id}/classrooms", [
                'classroom_ids' => [$validClassroom->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_ids');
    }

    public function test_index_returns_company_id(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_idx',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフIdx',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->getJson("/api/admin/users/{$staff->id}/classrooms");

        $response->assertStatus(200);
        $response->assertJsonPath('data.company_id', $company->id);
    }
}
