<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ① タブレットアカウントの複数事業所対応
 *
 * 差分カテゴリ: logic
 *
 * 報告: 「タブレットユーザーが複数事業所を表示できるようにしたい」
 *
 * 仕様: 管理画面 (/admin/tablet-accounts) で classroom_ids[] を指定すると
 * classroom_user ピボットへ同期する。主事業所 (users.classroom_id) は必ず含める。
 * これにより User::switchableClassroomIds() (pivot ∪ classroom_id) が複数教室を返し、
 * タブレットアプリの ClassroomSwitcher / switch-classroom で教室を切り替えられる。
 *
 * 認可: 同期対象の各教室は操作者の manageableClassroomIds() 内であること。
 * 範囲外が含まれる場合は 403。
 */
class TabletMultiClassroomTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 企業 A + 3教室 (A1, A2, A3) + 企業管理者 (A1所属) と、別企業教室 B1
     *
     * @return array{c1:Classroom, c2:Classroom, c3:Classroom, b1:Classroom, companyAdmin:User}
     */
    private function fixture(): array
    {
        $company = Company::create(['name' => '複数事業所企業']);
        $c1 = Classroom::create(['classroom_name' => 'MC_A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'MC_A2', 'company_id' => $company->id, 'is_active' => true]);
        $c3 = Classroom::create(['classroom_name' => 'MC_A3', 'company_id' => $company->id, 'is_active' => true]);

        $otherCompany = Company::create(['name' => '別企業MC']);
        $b1 = Classroom::create(['classroom_name' => 'MC_B1', 'company_id' => $otherCompany->id, 'is_active' => true]);

        $companyAdmin = User::create([
            'username'         => 'admin_mc_' . uniqid(),
            'password'         => Hash::make('p'),
            'full_name'        => '複数事業所管理者',
            'user_type'        => 'admin',
            'is_master'        => false,
            'is_company_admin' => true,
            'classroom_id'     => $c1->id,
            'is_active'        => true,
        ]);

        return compact('c1', 'c2', 'c3', 'b1', 'companyAdmin');
    }

    /**
     * 作成時に classroom_ids[] を渡すと classroom_user ピボットへ同期され、
     * switchableClassroomIds() が主教室＋追加教室を返す
     */
    public function test_store_syncs_classroom_ids_to_pivot(): void
    {
        ['companyAdmin' => $admin, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3] = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id'  => $c1->id,
                'classroom_ids' => [$c2->id, $c3->id],
                'username'      => 'tablet_mc_store',
                'password'      => 'pass1234',
                'full_name'     => 'MC ストアタブレット',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.classroom_id', $c1->id);

        $account = User::where('username', 'tablet_mc_store')->firstOrFail();
        $pivotIds = $account->classrooms()->pluck('classrooms.id')->sort()->values()->all();
        $this->assertEquals([$c1->id, $c2->id, $c3->id], $pivotIds);

        // 教室切替の候補 (pivot ∪ classroom_id)
        $switchable = $account->switchableClassroomIds();
        sort($switchable);
        $this->assertEquals([$c1->id, $c2->id, $c3->id], $switchable);
    }

    /**
     * classroom_ids に主教室を含めなくても、主教室は必ずピボットに含まれる
     */
    public function test_primary_classroom_always_included_in_pivot(): void
    {
        ['companyAdmin' => $admin, 'c1' => $c1, 'c2' => $c2] = $this->fixture();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id'  => $c1->id,
                'classroom_ids' => [$c2->id], // 主教室 c1 を含めていない
                'username'      => 'tablet_mc_primary',
                'password'      => 'pass1234',
                'full_name'     => 'MC 主教室タブレット',
            ])->assertStatus(201);

        $account = User::where('username', 'tablet_mc_primary')->firstOrFail();
        $pivotIds = $account->classrooms()->pluck('classrooms.id')->all();
        $this->assertContains($c1->id, $pivotIds);
        $this->assertContains($c2->id, $pivotIds);
    }

    /**
     * 既存アカウントの update で classroom_ids[] を渡すとピボットが置き換わる
     */
    public function test_update_syncs_classroom_ids(): void
    {
        ['companyAdmin' => $admin, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3] = $this->fixture();

        $account = User::create([
            'username'     => 'tablet_mc_update_' . uniqid(),
            'password'     => Hash::make('p'),
            'full_name'    => 'MC 更新タブレット',
            'user_type'    => 'tablet',
            'classroom_id' => $c1->id,
            'is_active'    => true,
        ]);
        $account->classrooms()->sync([$c1->id, $c2->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/tablet-accounts/{$account->id}", [
                'classroom_id'  => $c1->id,
                'classroom_ids' => [$c3->id], // c2 を外し c3 を追加
            ]);

        $response->assertStatus(200);

        $pivotIds = $account->fresh()->classrooms()->pluck('classrooms.id')->sort()->values()->all();
        $this->assertEquals([$c1->id, $c3->id], $pivotIds);
    }

    /**
     * 範囲外 (別企業) の教室を classroom_ids に含めると 403、ピボットは変更されない
     */
    public function test_out_of_range_classroom_id_is_rejected(): void
    {
        ['companyAdmin' => $admin, 'c1' => $c1, 'c2' => $c2, 'b1' => $b1] = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tablet-accounts', [
                'classroom_id'  => $c1->id,
                'classroom_ids' => [$c2->id, $b1->id], // b1 は別企業 → 範囲外
                'username'      => 'tablet_mc_reject',
                'password'      => 'pass1234',
                'full_name'     => 'MC 範囲外タブレット',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['username' => 'tablet_mc_reject']);
    }
}
