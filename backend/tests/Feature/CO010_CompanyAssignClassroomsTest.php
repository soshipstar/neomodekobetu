<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CO010: 企業への教室割当APIの相互排他制御
 *
 * 差分カテゴリ: api / logic
 * 背景: /admin/companies の教室割当で、他企業に既に所属している教室を
 *       上書きで奪えてしまう問題があった。また、チェックを外した教室を
 *       company_id = null に戻す同期処理が無かったため、関係を解除する
 *       手段が存在しなかった。
 */
class CO010_CompanyAssignClassroomsTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::create([
            'username' => 'master_co010',
            'password' => bcrypt('pass123'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    public function test_assign_unassigned_classrooms_to_company_succeeds(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => null, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室2', 'company_id' => null, 'is_active' => true]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/assign-classrooms", [
                'classroom_ids' => [$c1->id, $c2->id],
            ]);

        $response->assertStatus(200);
        $this->assertEquals($company->id, $c1->fresh()->company_id);
        $this->assertEquals($company->id, $c2->fresh()->company_id);
    }

    public function test_cannot_steal_classroom_already_assigned_to_another_company(): void
    {
        $master = $this->master();
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $classroom = Classroom::create([
            'classroom_name' => '教室X',
            'company_id' => $companyA->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$companyB->id}/assign-classrooms", [
                'classroom_ids' => [$classroom->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('conflicting_classroom_ids.0', $classroom->id);
        // 所属は元の企業Aのまま変わらない
        $this->assertEquals($companyA->id, $classroom->fresh()->company_id);
    }

    public function test_unchecking_a_classroom_unassigns_it(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => '教室2', 'company_id' => $company->id, 'is_active' => true]);

        // 教室1 だけをチェック状態にして保存 → 教室2 は解除されるべき
        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/assign-classrooms", [
                'classroom_ids' => [$c1->id],
            ]);

        $response->assertStatus(200);
        $this->assertEquals($company->id, $c1->fresh()->company_id);
        $this->assertNull($c2->fresh()->company_id);
    }

    public function test_empty_classroom_ids_unassigns_all(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => '教室1', 'company_id' => $company->id, 'is_active' => true]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/assign-classrooms", [
                'classroom_ids' => [],
            ]);

        $response->assertStatus(200);
        $this->assertNull($c1->fresh()->company_id);
    }

    public function test_unassigning_then_reassigning_to_another_company_works(): void
    {
        // 「関係性を切るまでは他企業で選択できない」ことを2ステップで検証
        $master = $this->master();
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $classroom = Classroom::create([
            'classroom_name' => '教室X',
            'company_id' => $companyA->id,
            'is_active' => true,
        ]);

        // (1) 企業Bがいきなり奪おうとする → 拒否
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$companyB->id}/assign-classrooms", [
                'classroom_ids' => [$classroom->id],
            ])
            ->assertStatus(422);

        // (2) 企業A が自身の割当から外して関係を解除
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$companyA->id}/assign-classrooms", [
                'classroom_ids' => [],
            ])
            ->assertStatus(200);
        $this->assertNull($classroom->fresh()->company_id);

        // (3) 企業B に改めて割り当てられる
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$companyB->id}/assign-classrooms", [
                'classroom_ids' => [$classroom->id],
            ])
            ->assertStatus(200);
        $this->assertEquals($companyB->id, $classroom->fresh()->company_id);
    }

    public function test_non_master_admin_cannot_assign(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室1', 'company_id' => null, 'is_active' => true]);
        $nonMaster = User::create([
            'username' => 'normal_admin_co010',
            'password' => bcrypt('pass'),
            'full_name' => '一般管理者',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        // 一般管理者は classroom.company_id が null だと作成時に弾かれる仕様だが、
        // ここでは割当APIの権限境界だけをテストするため、事前に直接作ったユーザーで叩く
        $response = $this->actingAs($nonMaster, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/assign-classrooms", [
                'classroom_ids' => [$classroom->id],
            ]);

        $response->assertStatus(403);
    }
}
