<?php

namespace Tests\Feature;

use App\Models\BugReport;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * バグ報告のステータス変更権限テスト
 *
 * 差分カテゴリ: auth
 * 背景: 報告者本人が「対応済み確認依頼中」になった自分の報告を「解決済み」に
 *       変更できるようにしたため、権限境界を回帰テストで固定する。
 */
class BugReport_ReporterResolvePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeClassroom(): Classroom
    {
        return Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
    }

    private function makeStaff(Classroom $c): User
    {
        return User::create([
            'username' => 'staff_' . uniqid(),
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $c->id,
            'is_active' => true,
        ]);
    }

    private function makeMaster(): User
    {
        return User::create([
            'username' => 'master_' . uniqid(),
            'password' => bcrypt('pass'),
            'full_name' => 'マスター',
            'user_type' => 'admin',
            'is_master' => true,
            'is_active' => true,
        ]);
    }

    public function test_reporter_can_resolve_in_progress_own_report(): void
    {
        $c = $this->makeClassroom();
        $reporter = $this->makeStaff($c);
        $report = BugReport::create([
            'reporter_id' => $reporter->id,
            'page_url' => 'https://kiduri.xyz/x',
            'description' => 'bug',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($reporter, 'sanctum')
            ->patchJson("/api/staff/bug-reports/{$report->id}/status", ['status' => 'resolved']);

        $response->assertStatus(200);
        $this->assertEquals('resolved', $report->fresh()->status);
    }

    public function test_reporter_cannot_resolve_open_own_report(): void
    {
        // 「対応済み確認依頼中」を経ずに open → resolved は不可
        $c = $this->makeClassroom();
        $reporter = $this->makeStaff($c);
        $report = BugReport::create([
            'reporter_id' => $reporter->id,
            'page_url' => 'https://kiduri.xyz/x',
            'description' => 'bug',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($reporter, 'sanctum')
            ->patchJson("/api/staff/bug-reports/{$report->id}/status", ['status' => 'resolved']);

        $response->assertStatus(403);
        $this->assertEquals('open', $report->fresh()->status);
    }

    public function test_reporter_cannot_reopen_own_report(): void
    {
        $c = $this->makeClassroom();
        $reporter = $this->makeStaff($c);
        $report = BugReport::create([
            'reporter_id' => $reporter->id,
            'page_url' => 'https://kiduri.xyz/x',
            'description' => 'bug',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($reporter, 'sanctum')
            ->patchJson("/api/staff/bug-reports/{$report->id}/status", ['status' => 'open']);

        $response->assertStatus(403);
        $this->assertEquals('in_progress', $report->fresh()->status);
    }

    public function test_non_reporter_staff_cannot_change_status(): void
    {
        $c = $this->makeClassroom();
        $reporter = $this->makeStaff($c);
        $other = $this->makeStaff($c);
        $report = BugReport::create([
            'reporter_id' => $reporter->id,
            'page_url' => 'https://kiduri.xyz/x',
            'description' => 'bug',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/staff/bug-reports/{$report->id}/status", ['status' => 'resolved']);

        $response->assertStatus(403);
    }

    public function test_master_can_change_any_status(): void
    {
        $c = $this->makeClassroom();
        $reporter = $this->makeStaff($c);
        $master = $this->makeMaster();
        $report = BugReport::create([
            'reporter_id' => $reporter->id,
            'page_url' => 'https://kiduri.xyz/x',
            'description' => 'bug',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->patchJson("/api/staff/bug-reports/{$report->id}/status", ['status' => 'in_progress']);

        $response->assertStatus(200);
        $this->assertEquals('in_progress', $report->fresh()->status);
    }
}
