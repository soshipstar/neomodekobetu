<?php

namespace Tests\Feature;

use App\Models\AbilityScore;
use App\Models\AbilitySubjectiveScore;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Services\AbilitySummaryService;
use App\Services\OutcomeService;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AI学習基盤 S6: 成果(outcome) A スコアΔ / B モニタリング達成度 / C 主観×客観の一致。
 *
 * 差分カテゴリ: logic
 */
class OutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    private OutcomeService $svc;
    private Classroom $room;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AbilityEvalMasterSeeder::class);
        $this->svc = new OutcomeService(new AbilitySummaryService());

        $company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true, 'ability_assessment_enabled' => true]);
        $this->student = Student::create(['student_name' => '児A', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5', 'status' => 'active', 'is_active' => true]);
    }

    private function score(string $itemId, int $score, ?int $change): void
    {
        AbilityScore::create([
            'student_id' => $this->student->id, 'item_id' => $itemId, 'axis_id' => 'S3',
            'score' => $score, 'change' => $change, 'method' => 'rule_engine', 'evaluated_on' => Carbon::now()->toDateString(),
        ]);
    }

    public function test_objective_delta(): void
    {
        $this->score('DEV-1-1', 8, 2);  // 向上
        $this->score('DEV-3-1', 4, -1); // 低下

        $a = $this->svc->forStudent($this->student)['objective_delta'];
        $this->assertTrue($a['has']);
        $this->assertSame(2, $a['scored_items']);
        $this->assertSame(1, $a['improved']);
        $this->assertSame(1, $a['declined']);
        $this->assertSame(0.5, $a['avg_change']); // (2 + -1)/2
    }

    public function test_monitoring_achievement(): void
    {
        $plan = IndividualSupportPlan::create(['student_id' => $this->student->id, 'classroom_id' => $this->room->id, 'status' => 'official']);
        $m = MonitoringRecord::create([
            'plan_id' => $plan->id, 'student_id' => $this->student->id, 'classroom_id' => $this->room->id,
            'monitoring_date' => '2026-06-01', 'is_draft' => false,
        ]);
        MonitoringDetail::create(['monitoring_id' => $m->id, 'domain' => '健康・生活', 'achievement_level' => '4', 'sort_order' => 0]);
        MonitoringDetail::create(['monitoring_id' => $m->id, 'domain' => '認知・行動', 'achievement_level' => '2', 'sort_order' => 1]);

        $b = $this->svc->forStudent($this->student)['monitoring'];
        $this->assertTrue($b['has']);
        $this->assertSame(3.0, $b['avg_level']);  // (4+2)/2
        $this->assertSame(50, $b['pct']);          // (3-1)/4*100
        $this->assertSame(2, $b['count']);
    }

    public function test_subjective_objective_agreement(): void
    {
        $this->score('DEV-1-1', 8, null); // 客観8(健康・生活)
        AbilitySubjectiveScore::create(['student_id' => $this->student->id, 'item_id' => 'DEV-1-1', 'response_value' => 5, 'source' => 'mynameis']); // 主観5→正規化10

        $c = $this->svc->forStudent($this->student)['agreement'];
        $this->assertTrue($c['has']);
        // 一致度 = 1 - |8 - 10|/10 = 0.8 → 80%
        $this->assertSame(80, $c['overall']);
        $health = collect($c['domains'])->firstWhere('domain', '健康・生活');
        $this->assertSame(80, $health['agreement']);
    }

    public function test_empty_when_no_data(): void
    {
        $out = $this->svc->forStudent($this->student);
        $this->assertFalse($out['objective_delta']['has']);
        $this->assertFalse($out['monitoring']['has']);
        $this->assertFalse($out['agreement']['has']);
    }
}
