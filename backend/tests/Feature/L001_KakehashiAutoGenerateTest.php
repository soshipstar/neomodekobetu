<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\KakehashiPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\KakehashiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class L001_KakehashiAutoGenerateTest extends TestCase
{
    use RefreshDatabase;

    private KakehashiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KakehashiService();
    }

    /**
     * Test date calculation for period 1.
     * Period 1: start=2025-08-17, end=2026-02-16, deadline=2025-08-16
     */
    public function test_calculate_dates_period_1(): void
    {
        $supportStart = Carbon::parse('2025-08-17');

        $dates = $this->service->calculateKakehashiDates($supportStart, 1);

        $this->assertEquals('2025-08-17', $dates['start_date']->toDateString());
        $this->assertEquals('2026-02-16', $dates['end_date']->toDateString());
        $this->assertEquals('2025-08-16', $dates['submission_deadline']->toDateString());
    }

    /**
     * Test date calculation for period 2.
     * Period 2: start=2026-02-17, end=2026-08-16, deadline=2026-01-17
     */
    public function test_calculate_dates_period_2(): void
    {
        $supportStart = Carbon::parse('2025-08-17');
        $prevEndDate = Carbon::parse('2026-02-16');

        $dates = $this->service->calculateKakehashiDates($supportStart, 2, $prevEndDate);

        $this->assertEquals('2026-02-17', $dates['start_date']->toDateString());
        $this->assertEquals('2026-08-16', $dates['end_date']->toDateString());
        $this->assertEquals('2026-01-17', $dates['submission_deadline']->toDateString());
    }

    /**
     * Test date calculation for period 3.
     * Period 3: start=2026-08-17, end=2027-02-16, deadline=2026-07-17
     */
    public function test_calculate_dates_period_3(): void
    {
        $supportStart = Carbon::parse('2025-08-17');
        $prevEndDate = Carbon::parse('2026-08-16');

        $dates = $this->service->calculateKakehashiDates($supportStart, 3, $prevEndDate);

        $this->assertEquals('2026-08-17', $dates['start_date']->toDateString());
        $this->assertEquals('2027-02-16', $dates['end_date']->toDateString());
        $this->assertEquals('2026-07-17', $dates['submission_deadline']->toDateString());
    }

    /**
     * Test full auto-generation: given a student with support_start_date=2025-08-17,
     * 3 kakehashi periods should be generated with correct dates.
     */
    public function test_generates_three_periods_for_student(): void
    {
        // Freeze time so that 3 periods' deadlines are within generation limit (today + 1 month)
        // Period 3 deadline is 2026-07-17, so we set today to 2026-07-01
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        $classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $guardian = User::create([
            'username' => 'guardian_test',
            'password' => bcrypt('password'),
            'full_name' => 'Test Guardian',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'support_start_date' => '2025-08-17',
            'guardian_id' => $guardian->id,
            'is_active' => true,
        ]);

        $periods = $this->service->generateKakehashiPeriodsForStudent($student->id, '2025-08-17');

        $this->assertCount(3, $periods);

        // Verify from database
        $dbPeriods = KakehashiPeriod::where('student_id', $student->id)
            ->orderBy('start_date')
            ->get();

        $this->assertCount(3, $dbPeriods);

        // Period 1
        $this->assertEquals('2025-08-17', $dbPeriods[0]->start_date->toDateString());
        $this->assertEquals('2026-02-16', $dbPeriods[0]->end_date->toDateString());
        $this->assertEquals('2025-08-16', $dbPeriods[0]->submission_deadline->toDateString());

        // Period 2
        $this->assertEquals('2026-02-17', $dbPeriods[1]->start_date->toDateString());
        $this->assertEquals('2026-08-16', $dbPeriods[1]->end_date->toDateString());
        $this->assertEquals('2026-01-17', $dbPeriods[1]->submission_deadline->toDateString());

        // Period 3
        $this->assertEquals('2026-08-17', $dbPeriods[2]->start_date->toDateString());
        $this->assertEquals('2027-02-16', $dbPeriods[2]->end_date->toDateString());
        $this->assertEquals('2026-07-17', $dbPeriods[2]->submission_deadline->toDateString());

        Carbon::setTestNow();
    }

    /**
     * Test that the job calls the correct method on KakehashiService.
     */
    public function test_job_calls_auto_generate_method(): void
    {
        $job = new \App\Jobs\AutoGenerateKakehashiPeriodJob();

        $mock = $this->mock(KakehashiService::class);
        $mock->shouldReceive('autoGenerateNextKakehashiPeriods')
            ->once()
            ->andReturn([]);

        $job->handle($mock);
    }

    /**
     * Test that periods with is_auto_generated flag are set correctly.
     */
    public function test_periods_marked_as_auto_generated(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-08-20'));

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

        $this->service->generateKakehashiPeriodsForStudent($student->id, '2025-08-17');

        $period = KakehashiPeriod::where('student_id', $student->id)->first();
        $this->assertTrue($period->is_auto_generated);
        $this->assertTrue($period->is_active);

        Carbon::setTestNow();
    }

    /**
     * Test that duplicate generation is skipped.
     */
    public function test_skips_if_periods_already_exist(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-08-20'));

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

        // First generation
        $this->service->generateKakehashiPeriodsForStudent($student->id, '2025-08-17');
        $firstCount = KakehashiPeriod::where('student_id', $student->id)->count();

        // Second generation should skip
        $result = $this->service->generateKakehashiPeriodsForStudent($student->id, '2025-08-17');
        $secondCount = KakehashiPeriod::where('student_id', $student->id)->count();

        $this->assertEmpty($result);
        $this->assertEquals($firstCount, $secondCount);

        Carbon::setTestNow();
    }

    /**
     * Test support plan deadline calculation.
     */
    public function test_support_plan_deadline_period_1(): void
    {
        $kakehashiDeadline = Carbon::parse('2025-08-16');
        $deadline = $this->service->calculateSupportPlanDeadline($kakehashiDeadline, 1);
        $this->assertEquals('2025-08-16', $deadline->toDateString());
    }

    public function test_support_plan_deadline_period_2(): void
    {
        $kakehashiDeadline = Carbon::parse('2026-01-17');
        $deadline = $this->service->calculateSupportPlanDeadline($kakehashiDeadline, 2);
        $this->assertEquals('2026-02-17', $deadline->toDateString());
    }

    /**
     * Test monitoring deadline calculation.
     */
    public function test_monitoring_deadline(): void
    {
        $supportPlanDeadline = Carbon::parse('2025-08-16');
        $deadline = $this->service->calculateMonitoringDeadline($supportPlanDeadline);
        $this->assertEquals('2026-01-16', $deadline->toDateString());
    }
}
