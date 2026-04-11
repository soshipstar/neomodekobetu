<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU013: マスター管理者が所属教室・所属企業に縛られず全データを閲覧できることの確認
 *
 * 差分カテゴリ: logic
 * 背景:
 *  - User::accessibleClassroomIds() に is_master チェックがなく、master でも
 *    自身が classroom_user に登録されている教室しか取得できなかった
 *  - WaitingListController が $user->classroom_id で一律スコープしていた
 *  - ClassroomSwitchController::myClassrooms が master でも pivot 経由のみだった
 *
 * 通常管理者は従来通りの挙動を維持していることも合わせて検証する。
 */
class AU013_MasterAdminCrossCompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用に 2 企業 + 各 2 教室 + 各 1 待機生徒のセットを作る
     *
     * @return array{
     *   master: User,
     *   normalAdmin: User,
     *   companyA: Company, companyB: Company,
     *   classA1: Classroom, classA2: Classroom,
     *   classB1: Classroom, classB2: Classroom,
     *   waitA1: Student, waitA2: Student, waitB1: Student, waitB2: Student
     * }
     */
    private function fixture(): array
    {
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);

        $classA1 = Classroom::create(['classroom_name' => 'A1', 'company_id' => $companyA->id, 'is_active' => true]);
        $classA2 = Classroom::create(['classroom_name' => 'A2', 'company_id' => $companyA->id, 'is_active' => true]);
        $classB1 = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);
        $classB2 = Classroom::create(['classroom_name' => 'B2', 'company_id' => $companyB->id, 'is_active' => true]);

        $master = User::create([
            'username' => 'master_au013',
            'password' => bcrypt('pass'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);

        $normalAdmin = User::create([
            'username' => 'normal_au013',
            'password' => bcrypt('pass'),
            'full_name' => 'NormalAdmin',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $classA1->id,
            'is_active' => true,
        ]);

        // 各教室に1名ずつ待機生徒を作成
        $mkWait = fn(Classroom $c, string $name) => Student::create([
            'classroom_id' => $c->id,
            'student_name' => $name,
            'status' => 'waiting',
            'is_active' => true,
        ]);

        return [
            'master' => $master,
            'normalAdmin' => $normalAdmin,
            'companyA' => $companyA, 'companyB' => $companyB,
            'classA1' => $classA1, 'classA2' => $classA2,
            'classB1' => $classB1, 'classB2' => $classB2,
            'waitA1' => $mkWait($classA1, '待機A1'),
            'waitA2' => $mkWait($classA2, '待機A2'),
            'waitB1' => $mkWait($classB1, '待機B1'),
            'waitB2' => $mkWait($classB2, '待機B2'),
        ];
    }

    public function test_master_accessible_classroom_ids_returns_all_classrooms(): void
    {
        $f = $this->fixture();
        $allIds = Classroom::pluck('id')->all();
        $masterIds = $f['master']->accessibleClassroomIds();
        sort($allIds);
        sort($masterIds);
        $this->assertEquals($allIds, $masterIds);
    }

    public function test_normal_admin_accessible_classroom_ids_is_scoped(): void
    {
        $f = $this->fixture();
        $ids = $f['normalAdmin']->accessibleClassroomIds();
        // normalAdmin は classroom A1 のみ
        $this->assertEquals([$f['classA1']->id], $ids);
    }

    public function test_master_waiting_list_returns_all_classrooms(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['master'], 'sanctum')
            ->getJson('/api/admin/waiting-list');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('student_name')->all();
        sort($names);
        $this->assertEquals(['待機A1', '待機A2', '待機B1', '待機B2'], $names);
    }

    public function test_normal_admin_waiting_list_is_scoped_to_own_classroom(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['normalAdmin'], 'sanctum')
            ->getJson('/api/admin/waiting-list');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('student_name')->all();
        $this->assertEquals(['待機A1'], $names);
    }

    public function test_master_waiting_list_can_filter_by_classroom(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['master'], 'sanctum')
            ->getJson('/api/admin/waiting-list?classroom_id=' . $f['classB1']->id);

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('student_name')->all();
        $this->assertEquals(['待機B1'], $names);
    }

    public function test_master_my_classrooms_returns_all_classrooms(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['master'], 'sanctum')
            ->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->sort()->values()->all();
        $expected = [$f['classA1']->id, $f['classA2']->id, $f['classB1']->id, $f['classB2']->id];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }

    public function test_normal_admin_my_classrooms_is_scoped(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['normalAdmin'], 'sanctum')
            ->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->all();
        $this->assertEquals([$f['classA1']->id], $ids);
    }
}
