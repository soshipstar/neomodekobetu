<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * バグ報告 #25: 複数教室登録スタッフが他施設に切り替えられない問題の回帰テスト
 *
 * 差分カテゴリ: logic
 * 背景: classroom_user ピボットに複数教室が登録されているにも関わらず、
 *       User::switchableClassroomIds() がピボットを読まず users.classroom_id のみを
 *       返していたため、/api/my-classrooms が1教室しか返さなかった。
 */
class BugReport25_StaffMultiClassroomSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_classrooms_returns_all_pivot_classrooms(): void
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室2', 'company_id' => $company->id, 'is_active' => true]);
        $c3 = Classroom::create(['classroom_name' => '教室3', 'company_id' => $company->id, 'is_active' => true]);

        $staff = User::create([
            'username' => 'staff_multi',
            'password' => bcrypt('pass'),
            'full_name' => 'マルチ所属',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);
        $staff->classrooms()->sync([$c1->id, $c2->id, $c3->id]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$c1->id, $c2->id, $c3->id], $ids);
    }

    public function test_switch_to_pivot_classroom_succeeds(): void
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室2', 'company_id' => $company->id, 'is_active' => true]);

        $staff = User::create([
            'username' => 'staff_switch',
            'password' => bcrypt('pass'),
            'full_name' => 'スイッチスタッフ',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);
        $staff->classrooms()->sync([$c1->id, $c2->id]);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/switch-classroom', ['classroom_id' => $c2->id]);

        $response->assertStatus(200);
        $this->assertEquals($c2->id, $staff->fresh()->classroom_id);
    }

    public function test_switch_to_non_pivot_classroom_is_forbidden(): void
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室2', 'company_id' => $company->id, 'is_active' => true]);
        $c3 = Classroom::create(['classroom_name' => '教室3', 'company_id' => $company->id, 'is_active' => true]);

        $staff = User::create([
            'username' => 'staff_no_access',
            'password' => bcrypt('pass'),
            'full_name' => '非所属スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);
        $staff->classrooms()->sync([$c1->id, $c2->id]);

        // c3 はピボットにも users.classroom_id にも無い
        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/switch-classroom', ['classroom_id' => $c3->id]);

        $response->assertStatus(403);
        $this->assertEquals($c1->id, $staff->fresh()->classroom_id);
    }

    public function test_legacy_single_classroom_staff_still_works(): void
    {
        // classroom_user ピボット未登録（レガシー）スタッフの後方互換
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);

        $staff = User::create([
            'username' => 'staff_legacy',
            'password' => bcrypt('pass'),
            'full_name' => 'レガシースタッフ',
            'user_type' => 'staff',
            'classroom_id' => $c1->id,
            'is_active' => true,
        ]);
        // ピボットは意図的に空

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->all();
        $this->assertEquals([$c1->id], $ids);
    }
}
