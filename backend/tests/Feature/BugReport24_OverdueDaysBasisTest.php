<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * バグ報告 #24: pending-tasks の期限切れ日数が「初回個別支援計画基準」になる
 *
 * 差分カテゴリ: logic
 * 背景: auto-generated な draft 個別支援計画は created_date = support_start_date
 *       で生成される。getPeriodFromPlanDate がそれを逆算すると期間 0（初回期間）
 *       になり、その終了日から今日までの日数（= 初回計画終了日からの日数）が
 *       「期限切れ日数」として表示されていた。
 *       現在日が属する期間で再計算することで、「現在期限切れのもの基準の日数」に補正。
 */
class BugReport24_OverdueDaysBasisTest extends TestCase
{
    use RefreshDatabase;

    private function setup_student_with_old_support_start(int $yearsAgo = 3): array
    {
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $guardian = User::create([
            'username' => 'g_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'guardian', 'user_type' => 'guardian',
            'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $master = User::create([
            'username' => 'm_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'master', 'user_type' => 'admin',
            'is_master' => true, 'is_active' => true,
        ]);

        $supportStart = Carbon::now()->subYears($yearsAgo)->startOfMonth();
        $student = Student::create([
            'student_name' => 'テスト生徒',
            'classroom_id' => $c->id,
            'guardian_id' => $guardian->id,
            'support_start_date' => $supportStart->format('Y-m-d'),
            'is_active' => true,
        ]);

        // auto-generated 相当の draft: created_date = support_start_date
        $draft = IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $c->id,
            'student_name' => $student->student_name,
            'created_date' => $supportStart->format('Y-m-d'),
            'status' => 'draft',
            'is_draft' => true,
            'is_official' => false,
            'is_hidden' => false,
        ]);

        return [$master, $student, $supportStart, $draft];
    }

    public function test_days_since_plan_uses_current_period_not_initial_period(): void
    {
        [$master, $student, $supportStart, $draft] = $this->setup_student_with_old_support_start(3);

        $response = $this->actingAs($master, 'sanctum')->getJson('/api/staff/pending-tasks');
        $response->assertStatus(200);

        $planTask = collect($response->json('data.plans'))
            ->firstWhere('student_id', $student->id);
        $this->assertNotNull($planTask);

        // days_since_plan は今日が属する期間終了日から計算されるべき。
        // 今日が属する期間終了日は未来なので期限切れは発生せず、null になる（or 期間内 < 200 日）
        // 初回期間（support_start + 6ヶ月）からの日数（= 2.5年 = 約900日）であってはならない。
        $daysSincePlan = $planTask['days_since_plan'] ?? null;
        $this->assertTrue(
            $daysSincePlan === null || $daysSincePlan < 200,
            'days_since_plan must not be based on the initial plan period (got: ' . var_export($daysSincePlan, true) . ')'
        );
    }

    public function test_days_since_plan_uses_draft_own_period_when_still_valid(): void
    {
        // 期間内の draft（期間終了が未来）は従来通り draft 自身の期間を使う
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $guardian = User::create([
            'username' => 'g_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'guardian', 'user_type' => 'guardian',
            'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $master = User::create([
            'username' => 'm_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'master', 'user_type' => 'admin',
            'is_master' => true, 'is_active' => true,
        ]);

        $supportStart = Carbon::now()->subMonths(3)->startOfMonth();
        $student = Student::create([
            'student_name' => '新生徒',
            'classroom_id' => $c->id,
            'guardian_id' => $guardian->id,
            'support_start_date' => $supportStart->format('Y-m-d'),
            'is_active' => true,
        ]);

        IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $c->id,
            'student_name' => $student->student_name,
            'created_date' => $supportStart->format('Y-m-d'),
            'status' => 'draft',
            'is_draft' => true,
            'is_official' => false,
            'is_hidden' => false,
        ]);

        $response = $this->actingAs($master, 'sanctum')->getJson('/api/staff/pending-tasks');
        $response->assertStatus(200);

        $planTask = collect($response->json('data.plans'))
            ->firstWhere('student_id', $student->id);
        $this->assertNotNull($planTask);

        // 期間内のため days_since_plan は null（期限切れではない）
        $this->assertNull($planTask['days_since_plan']);
    }
}
