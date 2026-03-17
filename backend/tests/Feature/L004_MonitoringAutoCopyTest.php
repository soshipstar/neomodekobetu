<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\SupportPlanDetail;
use App\Models\User;
use App\Services\KakehashiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class L004_MonitoringAutoCopyTest extends TestCase
{
    use RefreshDatabase;

    private KakehashiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KakehashiService();
    }

    /**
     * Test that creating a monitoring record auto-copies support plan details
     * and sets plan_detail_id on each monitoring_detail.
     */
    public function test_monitoring_auto_copies_support_plan_details(): void
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'support_start_date' => '2025-08-17',
            'is_active' => true,
        ]);

        // Create a support plan with 3 details
        $plan = IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'created_date' => '2025-08-17',
            'status' => 'published',
        ]);

        $detail1 = SupportPlanDetail::create([
            'plan_id' => $plan->id,
            'domain' => '健康・生活',
            'goal' => 'Goal 1',
            'support_content' => 'Support 1',
            'sort_order' => 0,
        ]);

        $detail2 = SupportPlanDetail::create([
            'plan_id' => $plan->id,
            'domain' => '運動・感覚',
            'goal' => 'Goal 2',
            'support_content' => 'Support 2',
            'sort_order' => 1,
        ]);

        $detail3 = SupportPlanDetail::create([
            'plan_id' => $plan->id,
            'domain' => '認知・行動',
            'goal' => 'Goal 3',
            'support_content' => 'Support 3',
            'sort_order' => 2,
        ]);

        // Trigger monitoring creation
        $this->service->createMonitoringForPeriod($student->id, '2025-08-16');

        // Assert monitoring record was created
        $monitoring = MonitoringRecord::where('student_id', $student->id)
            ->where('monitoring_date', '2025-08-16')
            ->first();

        $this->assertNotNull($monitoring);
        $this->assertEquals($plan->id, $monitoring->plan_id);

        // Assert 3 monitoring details were auto-created
        $monitoringDetails = MonitoringDetail::where('monitoring_id', $monitoring->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $monitoringDetails);

        // Assert each monitoring_detail.plan_detail_id matches the source
        $this->assertEquals($detail1->id, $monitoringDetails[0]->plan_detail_id);
        $this->assertEquals($detail2->id, $monitoringDetails[1]->plan_detail_id);
        $this->assertEquals($detail3->id, $monitoringDetails[2]->plan_detail_id);

        // Assert domain is copied from plan detail
        $this->assertEquals('健康・生活', $monitoringDetails[0]->domain);
        $this->assertEquals('運動・感覚', $monitoringDetails[1]->domain);
        $this->assertEquals('認知・行動', $monitoringDetails[2]->domain);
    }

    /**
     * Test that monitoring is not duplicated for the same date.
     */
    public function test_monitoring_not_duplicated(): void
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'support_start_date' => '2025-08-17',
            'is_active' => true,
        ]);

        $plan = IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'created_date' => '2025-08-17',
            'status' => 'published',
        ]);

        SupportPlanDetail::create([
            'plan_id' => $plan->id,
            'domain' => '健康・生活',
            'goal' => 'Goal 1',
            'sort_order' => 0,
        ]);

        // Create twice
        $this->service->createMonitoringForPeriod($student->id, '2025-08-16');
        $this->service->createMonitoringForPeriod($student->id, '2025-08-16');

        $count = MonitoringRecord::where('student_id', $student->id)
            ->where('monitoring_date', '2025-08-16')
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test monitoring creation skipped when no support plan exists.
     */
    public function test_monitoring_skipped_without_plan(): void
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'support_start_date' => '2025-08-17',
            'is_active' => true,
        ]);

        $this->service->createMonitoringForPeriod($student->id, '2025-08-16');

        $count = MonitoringRecord::where('student_id', $student->id)->count();
        $this->assertEquals(0, $count);
    }
}
