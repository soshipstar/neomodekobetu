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
 * L012: 法定帳票サイクルの修正テスト (LOGIC-02, LOGIC-03)
 *
 * 差分カテゴリ: logic
 *
 * 放デイ業務リスク監査で検出:
 *  LOGIC-02 MonitoringController::store() が (student_id, plan_id) のみで
 *           重複判定し、1 計画につきモニタリングを 1 回しか作成できなかった。
 *           障害福祉サービスは 6 ヶ月毎に複数回モニタリングするため、
 *           2 回目以降が記録不能 = 法定義務未達リスク。
 *  LOGIC-03 SupportPlanController::sign() が status を無検査で受理し、
 *           draft 計画への署名で is_official=true 化でき状態機械を飛ばせた。
 */
class L012_MonitoringCycleAndSignFixTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $classroom = Classroom::create(['classroom_name' => 'L012教室', 'is_active' => true]);
        $staff = User::create([
            'username'     => 'staff_l012',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフL012',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $student = Student::create([
            'classroom_id'       => $classroom->id,
            'student_name'       => '生徒L012',
            'support_start_date' => '2026-01-01',
            'is_active'          => true,
        ]);
        $plan = IndividualSupportPlan::create([
            'student_id'   => $student->id,
            'classroom_id' => $classroom->id,
            'student_name' => '生徒L012',
            'created_date' => '2026-01-01',
            'status'       => 'official',
            'is_official'  => true,
        ]);

        return compact('classroom', 'staff', 'student', 'plan');
    }

    // =========================================================================
    // LOGIC-02: 1 計画に複数回モニタリングを作成できること
    // =========================================================================

    public function test_logic02_allows_multiple_monitorings_per_plan_on_different_dates(): void
    {
        $f = $this->fixture();

        // 1 回目のモニタリング (6 ヶ月後)
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/students/' . $f['student']->id . '/monitoring', [
                'plan_id'         => $f['plan']->id,
                'monitoring_date' => '2026-07-01',
            ])
            ->assertStatus(201);

        // 2 回目のモニタリング (12 ヶ月後) — 旧実装では 422 で弾かれていた
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/students/' . $f['student']->id . '/monitoring', [
                'plan_id'         => $f['plan']->id,
                'monitoring_date' => '2027-01-01',
            ])
            ->assertStatus(201);

        // 同一計画に 2 件のモニタリングが作成されていること
        $this->assertSame(2, MonitoringRecord::where('plan_id', $f['plan']->id)->count());
    }

    public function test_logic02_blocks_duplicate_monitoring_same_date(): void
    {
        $f = $this->fixture();

        // 同一日付の二重登録は誤操作として弾く
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/students/' . $f['student']->id . '/monitoring', [
                'plan_id'         => $f['plan']->id,
                'monitoring_date' => '2026-07-01',
            ])
            ->assertStatus(201);

        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/students/' . $f['student']->id . '/monitoring', [
                'plan_id'         => $f['plan']->id,
                'monitoring_date' => '2026-07-01',
            ])
            ->assertStatus(422);
    }

    // =========================================================================
    // LOGIC-03: draft 計画への署名を拒否
    // =========================================================================

    public function test_logic03_blocks_signing_draft_plan(): void
    {
        $f = $this->fixture();

        // draft 状態の計画を別途作成
        $draftPlan = IndividualSupportPlan::create([
            'student_id'   => $f['student']->id,
            'classroom_id' => $f['classroom']->id,
            'student_name' => '生徒L012',
            'created_date' => '2026-02-01',
            'status'       => 'draft',
            'is_official'  => false,
        ]);

        // draft への署名 → 422
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/support-plans/' . $draftPlan->id . '/sign', [
                'staff_signature'   => 'data:image/png;base64,iVBORw0KGgo=',
                'staff_signer_name' => 'スタッフL012',
            ])
            ->assertStatus(422);

        // draft のまま official 化されていないこと
        $fresh = $draftPlan->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertFalse((bool) $fresh->is_official);
    }

    public function test_logic03_allows_signing_submitted_plan(): void
    {
        $f = $this->fixture();

        $submittedPlan = IndividualSupportPlan::create([
            'student_id'   => $f['student']->id,
            'classroom_id' => $f['classroom']->id,
            'student_name' => '生徒L012',
            'created_date' => '2026-03-01',
            'status'       => 'submitted',
            'is_official'  => false,
        ]);

        // submitted への署名 → 200 (status ガードを通過する)
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/support-plans/' . $submittedPlan->id . '/sign', [
                'staff_signature'   => 'data:image/png;base64,iVBORw0KGgo=',
                'staff_signer_name' => 'スタッフL012',
            ])
            ->assertStatus(200);

        $this->assertTrue((bool) $submittedPlan->fresh()->is_official);
    }
}
