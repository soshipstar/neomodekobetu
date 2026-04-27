<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\MasterAdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-114: マスター操作履歴 (master_admin_audit_logs) 閲覧APIの権限とフィルタ
 */
class B114_MasterAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeMaster(): User
    {
        return User::create([
            'username' => 'master_b114_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'M',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        $c = Classroom::create(['classroom_name' => 'b114', 'is_active' => true]);
        return User::create([
            'username' => 'admin_b114_'.uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'A',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $c->id,
            'is_active' => true,
        ]);
    }

    public function test_normal_admin_cannot_access_audit_logs(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->getJson('/api/admin/master/audit-logs')
            ->assertStatus(403);
    }

    public function test_master_can_list_audit_logs_newest_first(): void
    {
        $master = $this->makeMaster();
        $company = Company::create(['name' => 'X社']);

        // 古い順に作成
        $log1 = MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => $company->id,
            'action' => 'update_display_settings', 'after' => ['x' => 1],
            'context' => ['ip' => '127.0.0.1'],
        ]);
        $log2 = MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => null,
            'action' => 'create_agent', 'after' => ['agent_id' => 99],
            'context' => ['ip' => '127.0.0.2'],
        ]);

        $res = $this->actingAs($master)
            ->getJson('/api/admin/master/audit-logs')
            ->assertStatus(200);

        $logs = $res->json('data.logs');
        $this->assertCount(2, $logs);
        // 新しい順 = log2 が先
        $this->assertSame($log2->id, $logs[0]['id']);
        $this->assertSame($log1->id, $logs[1]['id']);
        // available_actions
        $actions = $res->json('data.available_actions');
        $this->assertContains('update_display_settings', $actions);
        $this->assertContains('create_agent', $actions);
    }

    public function test_action_filter_works(): void
    {
        $master = $this->makeMaster();
        MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => null,
            'action' => 'create_agent', 'after' => ['x' => 1],
        ]);
        MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => null,
            'action' => 'update_display_settings', 'after' => ['y' => 2],
        ]);

        $res = $this->actingAs($master)
            ->getJson('/api/admin/master/audit-logs?action=create_agent')
            ->assertStatus(200);
        $logs = $res->json('data.logs');
        $this->assertCount(1, $logs);
        $this->assertSame('create_agent', $logs[0]['action']);
    }

    public function test_company_id_filter_works(): void
    {
        $master = $this->makeMaster();
        $a = Company::create(['name' => 'A']);
        $b = Company::create(['name' => 'B']);

        MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => $a->id, 'action' => 'update_price',
        ]);
        MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => $b->id, 'action' => 'update_price',
        ]);

        $res = $this->actingAs($master)
            ->getJson('/api/admin/master/audit-logs?company_id='.$a->id)
            ->assertStatus(200);
        $logs = $res->json('data.logs');
        $this->assertCount(1, $logs);
        $this->assertSame($a->id, $logs[0]['company_id']);
    }

    public function test_show_returns_log_with_relations(): void
    {
        $master = $this->makeMaster();
        $company = Company::create(['name' => '対象社']);
        $log = MasterAdminAuditLog::create([
            'master_user_id' => $master->id, 'company_id' => $company->id,
            'action' => 'subscribe', 'after' => ['price_id' => 'price_xxx'],
        ]);

        $res = $this->actingAs($master)
            ->getJson('/api/admin/master/audit-logs/'.$log->id)
            ->assertStatus(200);
        $res->assertJsonPath('data.action', 'subscribe');
        $res->assertJsonPath('data.master_user.id', $master->id);
        $res->assertJsonPath('data.company.id', $company->id);
    }
}
