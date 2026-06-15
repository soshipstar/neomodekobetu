<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0: モニタリング確定版(is_official=true)の不変化ガード。
 *
 * 介入↔成果連結(S9)の相関・効果分析は「成果スナップショットが確定後に改変されない」ことが
 * 前提。MonitoringController::update が確定済み記録の内容更新を許していたため、確定後の
 * 編集を 409 で拒否する。確定(draft→official)への遷移は許可し、訂正は新規作成で行う。
 *
 * 分類: logic
 */
class MonitoringImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $classroom = Classroom::create(['classroom_name' => 'Test教室', 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_mon_' . uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'テスト児童',
            'is_active' => true,
        ]);
        $plan = IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'student_name' => 'テスト児童',
            'created_date' => '2025-08-17',
            'status' => 'published',
        ]);

        return [$staff, $student, $plan, $classroom];
    }

    public function test_confirmed_monitoring_cannot_be_edited(): void
    {
        [$staff, $student, $plan] = $this->context();

        $monitoring = MonitoringRecord::create([
            'student_id'      => $student->id,
            'plan_id'         => $plan->id,
            'monitoring_date' => '2025-09-01',
            'is_draft'        => false,
            'is_official'     => true,
            'overall_comment' => '確定済みの所見',
        ]);

        $res = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/monitoring/{$monitoring->id}", [
                'overall_comment' => '後から改ざんした所見',
            ]);

        $res->assertStatus(409);
        $res->assertJsonPath('success', false);

        // 内容は変わっていないこと(成果スナップショットの整合性)
        $this->assertSame('確定済みの所見', $monitoring->fresh()->overall_comment);
    }

    public function test_draft_monitoring_can_be_confirmed_and_edited(): void
    {
        [$staff, $student, $plan] = $this->context();

        $monitoring = MonitoringRecord::create([
            'student_id'      => $student->id,
            'plan_id'         => $plan->id,
            'monitoring_date' => '2025-09-01',
            'is_draft'        => true,
            'is_official'     => false,
            'overall_comment' => '下書き',
        ]);

        // ドラフトは編集可能、かつ確定(is_draft=false)への遷移も通る
        $res = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/monitoring/{$monitoring->id}", [
                'overall_comment' => '確定する所見',
                'is_draft'        => false,
            ]);

        $res->assertStatus(200);
        $fresh = $monitoring->fresh();
        $this->assertTrue((bool) $fresh->is_official, '確定へ遷移できる');
        $this->assertSame('確定する所見', $fresh->overall_comment);
    }
}
