<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テナント分離(rank6): audit_logs の company_id 自動補完と閲覧の法人スコープ。
 *
 * 差分カテゴリ: logic / auth
 */
class AuditLogTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function admin(Classroom $room, array $attrs = []): User
    {
        return User::create(array_merge([
            'username' => 'admin_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $room->id, 'is_active' => true,
        ], $attrs));
    }

    public function test_create_autofills_company_id_from_actor(): void
    {
        $company = Company::create(['name' => 'A法人']);
        $room = Classroom::create(['classroom_name' => 'A', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->admin($room);

        $this->actingAs($admin); // 既定ガード → Auth::user() が解決される

        $log = AuditLog::create(['user_id' => $admin->id, 'action' => 'update', 'target_table' => 'students', 'target_id' => 1]);

        $this->assertSame($company->id, $log->company_id, '実行者の所属施設が自動補完される');
    }

    public function test_index_scopes_company_admin_to_own_company(): void
    {
        $companyA = Company::create(['name' => 'A法人']);
        $roomA = Classroom::create(['classroom_name' => 'A', 'company_id' => $companyA->id, 'is_active' => true]);
        $companyB = Company::create(['name' => 'B法人']);
        $roomB = Classroom::create(['classroom_name' => 'B', 'company_id' => $companyB->id, 'is_active' => true]);
        $adminA = $this->admin($roomA);

        // 明示 company_id でA・Bの監査ログを用意(auto-fillより明示が優先)
        AuditLog::create(['user_id' => $adminA->id, 'company_id' => $companyA->id, 'action' => 'update', 'target_table' => 'students', 'target_id' => 1]);
        AuditLog::create(['user_id' => $adminA->id, 'company_id' => $companyB->id, 'action' => 'update', 'target_table' => 'students', 'target_id' => 2]);

        $res = $this->actingAs($adminA, 'sanctum')->getJson('/api/admin/audit-logs')->assertStatus(200);
        $logs = collect($res->json('data.data'));
        $this->assertGreaterThan(0, $logs->count());
        $this->assertTrue($logs->every(fn ($l) => $l['company_id'] === $companyA->id), '自施設の監査ログのみ');
    }

    public function test_index_filters_by_target_table(): void
    {
        $company = Company::create(['name' => 'A法人']);
        $room = Classroom::create(['classroom_name' => 'A', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->admin($room);

        AuditLog::create(['user_id' => $admin->id, 'company_id' => $company->id, 'action' => 'update', 'target_table' => 'students', 'target_id' => 1]);
        AuditLog::create(['user_id' => $admin->id, 'company_id' => $company->id, 'action' => 'update', 'target_table' => 'classrooms', 'target_id' => 2]);

        // request キー target_table で target_table カラムをフィルタ
        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs?target_table=students')->assertStatus(200);
        $logs = collect($res->json('data.data'));
        $this->assertGreaterThan(0, $logs->count(), 'students の監査ログが返る');
        $this->assertTrue($logs->every(fn ($l) => $l['target_table'] === 'students'), 'target_table=students のみに絞られる');

        // 後方互換: request キー table_name でも同じ target_table カラムでフィルタ
        $res2 = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs?table_name=classrooms')->assertStatus(200);
        $logs2 = collect($res2->json('data.data'));
        $this->assertGreaterThan(0, $logs2->count(), 'classrooms の監査ログが返る');
        $this->assertTrue($logs2->every(fn ($l) => $l['target_table'] === 'classrooms'), 'table_name キーでも target_table カラムで絞られる');
    }

    public function test_index_master_sees_all_companies(): void
    {
        $companyA = Company::create(['name' => 'A法人']);
        $roomA = Classroom::create(['classroom_name' => 'A', 'company_id' => $companyA->id, 'is_active' => true]);
        $companyB = Company::create(['name' => 'B法人']);
        $roomB = Classroom::create(['classroom_name' => 'B', 'company_id' => $companyB->id, 'is_active' => true]);
        $someUser = $this->admin($roomA);
        $master = User::create([
            'username' => 'master_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'マスター',
            'user_type' => 'admin', 'is_master' => true, 'classroom_id' => null, 'is_active' => true,
        ]);

        AuditLog::create(['user_id' => $someUser->id, 'company_id' => $companyA->id, 'action' => 'update', 'target_table' => 'students', 'target_id' => 1]);
        AuditLog::create(['user_id' => $someUser->id, 'company_id' => $companyB->id, 'action' => 'update', 'target_table' => 'students', 'target_id' => 2]);

        $res = $this->actingAs($master, 'sanctum')->getJson('/api/admin/audit-logs')->assertStatus(200);
        $companyIds = collect($res->json('data.data'))->pluck('company_id')->unique();
        $this->assertTrue($companyIds->contains($companyA->id) && $companyIds->contains($companyB->id), 'マスターは全社の監査ログを閲覧できる');
    }
}
