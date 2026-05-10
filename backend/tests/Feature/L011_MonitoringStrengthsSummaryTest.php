<?php

namespace Tests\Feature;

use App\Http\Controllers\Staff\MonitoringController;
use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\ServiceTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Tests\TestCase;

/**
 * L011 MonitoringController::buildStrengthsSummary
 *
 * 差分カテゴリ: logic
 * 背景: モニタリング作成/更新時に対象期間の強み(才能)集計をスナップショット
 *       として保存する。期間の起点は次の優先順:
 *         1. 同一計画で前回モニタリングが存在 → その翌日
 *         2. 計画 created_date           → created_date
 *         3. 上記なし                       → モニタリング日の 3 ヶ月前
 *       逆転が発生したら起点を 1 ヶ月前にフォールバック。
 *       期間集計が空 (record_count = 0) なら null を返してカラム未設定。
 */
class L011_MonitoringStrengthsSummaryTest extends TestCase
{
    use RefreshDatabase;

    private MonitoringController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MonitoringController();
    }

    private function invoke(int $studentId, int $planId, ?IndividualSupportPlan $plan, Carbon $monitoringDate): ?array
    {
        $method = (new ReflectionClass($this->controller))->getMethod('buildStrengthsSummary');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $studentId, $planId, $plan, $monitoringDate);
    }

    private function setupStudent(string $serviceType = ServiceTypeRegistry::AFTER_SCHOOL): Student
    {
        $classroom = Classroom::create([
            'classroom_name' => 'C',
            'service_type'   => $serviceType,
            'is_active'      => true,
        ]);
        return Student::create([
            'classroom_id'       => $classroom->id,
            'student_name'       => 'S',
            'support_start_date' => '2026-01-01',
            'is_active'          => true,
        ]);
    }

    private function makePlan(Student $student, ?string $createdDate): IndividualSupportPlan
    {
        return IndividualSupportPlan::create([
            'student_id'   => $student->id,
            'classroom_id' => $student->classroom_id,
            'student_name' => $student->student_name,
            'created_date' => $createdDate,
            'status'       => 'active',
            'is_draft'     => false,
            'is_official'  => true,
        ]);
    }

    private function addRecord(Student $student, string $date, array $strengths): void
    {
        $staff = User::create([
            'username'     => 'st_'.uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'St',
            'user_type'    => 'staff',
            'classroom_id' => $student->classroom_id,
            'is_active'    => true,
        ]);

        $daily = DailyRecord::create([
            'classroom_id' => $student->classroom_id,
            'record_date'  => $date,
            'staff_id'     => $staff->id,
        ]);

        StudentRecord::create([
            'daily_record_id' => $daily->id,
            'student_id'      => $student->id,
            'strengths'       => $strengths,
        ]);
    }

    // =========================================================================
    // 起点決定ロジック
    // =========================================================================

    public function test_returns_null_when_no_records_in_period(): void
    {
        $student = $this->setupStudent();
        $plan    = $this->makePlan($student, '2026-01-01');

        // strengths レコードなし
        $result = $this->invoke($student->id, $plan->id, $plan, Carbon::parse('2026-04-01'));

        $this->assertNull($result);
    }

    public function test_uses_plan_created_date_as_period_start_when_no_previous_monitoring(): void
    {
        $student = $this->setupStudent();
        $plan    = $this->makePlan($student, '2026-02-01');

        // 期間内 (2026-02-01〜2026-04-01)
        $this->addRecord($student, '2026-03-15', ['集中力' => 6]);
        // 期間外 (起点 2026-02-01 より前) → 含まれないことを期待
        $this->addRecord($student, '2026-01-10', ['集中力' => 9]);

        $result = $this->invoke($student->id, $plan->id, $plan, Carbon::parse('2026-04-01'));

        $this->assertNotNull($result);
        $this->assertSame('2026-02-01', $result['from']);
        $this->assertSame('2026-04-01', $result['to']);
        $this->assertSame(1, $result['record_count']); // 1月の記録は除外される
    }

    public function test_uses_previous_monitoring_date_plus_one_day_as_period_start(): void
    {
        $student = $this->setupStudent();
        $plan    = $this->makePlan($student, '2026-01-01');

        // 前回モニタリングを 2026-03-01 に作成
        MonitoringRecord::create([
            'plan_id'         => $plan->id,
            'student_id'      => $student->id,
            'classroom_id'    => $student->classroom_id,
            'monitoring_date' => '2026-03-01',
            'is_official'     => true,
            'is_draft'        => false,
        ]);

        // 期間内 (2026-03-02〜2026-06-01)
        $this->addRecord($student, '2026-04-15', ['集中力' => 7]);
        // 起点ぴったりの日 (起点は 2026-03-02 なので 03-01 は除外)
        $this->addRecord($student, '2026-03-01', ['集中力' => 9]);

        $result = $this->invoke($student->id, $plan->id, $plan, Carbon::parse('2026-06-01'));

        $this->assertNotNull($result);
        $this->assertSame('2026-03-02', $result['from']); // 前回モニタリング日 + 1
        $this->assertSame(1, $result['record_count']);
    }

    public function test_falls_back_to_three_months_before_when_no_plan_and_no_previous(): void
    {
        $student = $this->setupStudent();
        // 計画 created_date が null
        $plan = $this->makePlan($student, null);

        // モニタリング日 2026-04-01 → 3 ヶ月前 = 2026-01-01
        $this->addRecord($student, '2026-02-15', ['集中力' => 6]);  // 含む
        $this->addRecord($student, '2025-12-25', ['集中力' => 9]);  // 範囲外 → 含まない

        $result = $this->invoke($student->id, $plan->id, $plan, Carbon::parse('2026-04-01'));

        $this->assertNotNull($result);
        $this->assertSame('2026-01-01', $result['from']);
        $this->assertSame('2026-04-01', $result['to']);
        $this->assertSame(1, $result['record_count']);
    }

    public function test_falls_back_to_one_month_before_when_period_would_invert(): void
    {
        $student = $this->setupStudent();
        // 計画 created_date がモニタリング日より未来に置かれている異常系
        $plan = $this->makePlan($student, '2026-08-01');

        // フォールバック期間: 2026-03-01 〜 2026-04-01
        $this->addRecord($student, '2026-03-15', ['集中力' => 5]);

        $result = $this->invoke($student->id, $plan->id, $plan, Carbon::parse('2026-04-01'));

        $this->assertNotNull($result);
        $this->assertSame('2026-03-01', $result['from']); // 1 ヶ月前にフォールバック
        $this->assertSame('2026-04-01', $result['to']);
        $this->assertSame(1, $result['record_count']);
    }

    public function test_returns_summary_with_trends_for_after_school(): void
    {
        $student = $this->setupStudent();
        $plan    = $this->makePlan($student, '2026-02-01');

        $this->addRecord($student, '2026-02-10', ['集中力' => 4, '持続力' => 5]);
        $this->addRecord($student, '2026-03-10', ['集中力' => 6, '持続力' => 6]);

        $result = $this->invoke($student->id, $plan->id, $plan, Carbon::parse('2026-04-01'));

        $this->assertNotNull($result);
        $this->assertSame(2, $result['record_count']);
        $this->assertNotEmpty($result['trends']);
        $this->assertSame(ServiceTypeRegistry::AFTER_SCHOOL, $result['service_type']);
    }
}
