<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * AU020: 管理者/スタッフアカウント管理の所属企業・所属教室による絞り込み検索
 *
 * 差分カテゴリ: screen
 * 背景: /admin/admin-accounts ・ /admin/staff-accounts では従来 氏名・ユーザー名の
 *       テキスト検索しか出来ず、所属企業・所属教室での絞り込みが出来なかった (現場要望)。
 *       index に company_id / classroom_id フィルタを追加する。
 *       企業は users.classroom.company_id 経由で一意に決まる (users に company_id 列は無い)。
 */
class AU020_AccountSearchByCompanyClassroomTest extends TestCase
{
    use DatabaseMigrations;

    private function master(): User
    {
        return User::create([
            'username'         => 'master_search',
            'password'         => bcrypt('pass123'),
            'full_name'        => 'Master',
            'user_type'        => 'admin',
            'is_master'        => true,
            'is_company_admin' => false,
            'is_active'        => true,
        ]);
    }

    /**
     * 2企業 × 各1教室 を作り、各教室に admin/staff を1人ずつ配置する。
     *
     * @return array{companyA: Company, companyB: Company, classroomA: Classroom, classroomB: Classroom}
     */
    private function seedAccounts(): array
    {
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $classroomA = Classroom::create(['classroom_name' => '教室A', 'company_id' => $companyA->id, 'is_active' => true]);
        $classroomB = Classroom::create(['classroom_name' => '教室B', 'company_id' => $companyB->id, 'is_active' => true]);

        foreach (['admin', 'staff'] as $type) {
            User::create([
                'username'  => "{$type}_a",
                'password'  => bcrypt('p'),
                'full_name' => "{$type} 企業A",
                'user_type' => $type,
                'is_active' => true,
                'classroom_id' => $classroomA->id,
            ]);
            User::create([
                'username'  => "{$type}_b",
                'password'  => bcrypt('p'),
                'full_name' => "{$type} 企業B",
                'user_type' => $type,
                'is_active' => true,
                'classroom_id' => $classroomB->id,
            ]);
        }

        return compact('companyA', 'companyB', 'classroomA', 'classroomB');
    }

    public function test_admin_accounts_filtered_by_company(): void
    {
        $master = $this->master();
        ['companyA' => $companyA] = $this->seedAccounts();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson('/api/admin/admin-accounts?company_id=' . $companyA->id);

        $response->assertStatus(200);
        $usernames = collect($response->json('data.data'))->pluck('username')->all();

        $this->assertContains('admin_a', $usernames);
        $this->assertNotContains('admin_b', $usernames);
        // マスター(教室なし=企業なし)も企業フィルタ時は除外される
        $this->assertNotContains('master_search', $usernames);
    }

    public function test_admin_accounts_filtered_by_classroom(): void
    {
        $master = $this->master();
        ['classroomB' => $classroomB] = $this->seedAccounts();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson('/api/admin/admin-accounts?classroom_id=' . $classroomB->id);

        $response->assertStatus(200);
        $usernames = collect($response->json('data.data'))->pluck('username')->all();

        $this->assertSame(['admin_b'], $usernames);
    }

    public function test_staff_accounts_filtered_by_company(): void
    {
        $master = $this->master();
        ['companyB' => $companyB] = $this->seedAccounts();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson('/api/admin/staff-accounts?company_id=' . $companyB->id);

        $response->assertStatus(200);
        $usernames = collect($response->json('data.data'))->pluck('username')->all();

        $this->assertContains('staff_b', $usernames);
        $this->assertNotContains('staff_a', $usernames);
    }

    public function test_staff_accounts_filtered_by_company_and_classroom_combined(): void
    {
        $master = $this->master();
        ['companyA' => $companyA, 'classroomA' => $classroomA] = $this->seedAccounts();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson("/api/admin/staff-accounts?company_id={$companyA->id}&classroom_id={$classroomA->id}");

        $response->assertStatus(200);
        $usernames = collect($response->json('data.data'))->pluck('username')->all();

        $this->assertSame(['staff_a'], $usernames);
    }

    public function test_no_filter_returns_all_admins(): void
    {
        $master = $this->master();
        $this->seedAccounts();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson('/api/admin/admin-accounts');

        $response->assertStatus(200);
        $usernames = collect($response->json('data.data'))->pluck('username')->all();

        // フィルタなしでは master 含む全管理者が返る
        $this->assertContains('admin_a', $usernames);
        $this->assertContains('admin_b', $usernames);
        $this->assertContains('master_search', $usernames);
    }
}
