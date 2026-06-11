<?php

namespace Tests\Feature;

use App\Models\AbilityScore;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AbilitySummaryService;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * 能力評価 P4: 評価状況の全体像(サマリ)サービス・API・別添PDFビュー・計画AI反映。
 *
 * 差分カテゴリ: logic
 */
class AbilitySummaryTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AbilityEvalMasterSeeder::class);

        $company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $this->student = Student::create([
            'student_name' => '児A', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ]);
    }

    private function score(string $itemId, string $axisId, int $score, bool $review = false): void
    {
        AbilityScore::create([
            'student_id' => $this->student->id, 'item_id' => $itemId, 'axis_id' => $axisId,
            'score' => $score, 'needs_review' => $review, 'method' => 'rule_engine',
            'evaluated_on' => Carbon::now()->toDateString(),
        ]);
    }

    public function test_summary_groups_by_domain_with_radar_and_guardian_words(): void
    {
        // 同一領域(健康・生活)の2項目 + 別領域(認知・行動)
        $this->score('DEV-1-1', 'S3', 8);
        $this->score('DEV-1-2', 'S3', 6, true);
        $this->score('DEV-3-1', 'S3', 4);

        $summary = (new AbilitySummaryService())->forStudent($this->student);

        $this->assertTrue($summary['has_data']);
        $this->assertSame(3, $summary['counts']['scored']);
        $this->assertSame(1, $summary['counts']['needs_review']);

        $health = collect($summary['domains'])->firstWhere('domain', '健康・生活');
        $this->assertNotNull($health);
        $this->assertSame(7.0, $health['average']); // (8+6)/2
        $this->assertCount(2, $health['items']);
        // 保護者向けのことばが点数から補完される
        $item = collect($health['items'])->firstWhere('item_id', 'DEV-1-1');
        $this->assertNotEmpty($item['guardian_words']);

        // レーダー用の領域平均が含まれる
        $this->assertNotEmpty($summary['radar']);
    }

    public function test_prompt_text_includes_scores(): void
    {
        $this->score('DEV-1-1', 'S3', 8);
        $svc = new AbilitySummaryService();
        $text = $svc->toPromptText($svc->forStudent($this->student));

        $this->assertStringContainsString('能力評価', $text);
        $this->assertStringContainsString('8点', $text);

        // データが無ければ空文字
        $empty = Student::create(['student_name' => '空', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);
        $this->assertSame('', $svc->toPromptText($svc->forStudent($empty)));
    }

    public function test_summary_endpoint_and_gating(): void
    {
        $staff = User::create([
            'username' => 'staff_sm_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->score('DEV-1-1', 'S3', 8);

        $res = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$this->student->id}/summary");
        $res->assertStatus(200);
        $res->assertJsonPath('data.has_data', true);

        // OFF教室は409
        $this->room->update(['ability_assessment_enabled' => false]);
        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$this->student->id}/summary")
            ->assertStatus(409);
    }

    public function test_pdf_blade_renders_table(): void
    {
        $this->score('DEV-1-1', 'S3', 8);
        $summary = (new AbilitySummaryService())->forStudent($this->student);

        $html = View::make('pdf.ability-summary', [
            'student' => $this->student,
            'classroom' => $this->room,
            'summary' => $summary,
            'generatedOn' => Carbon::now()->toDateString(),
        ])->render();

        $this->assertStringContainsString('評価状況の全体像', $html);
        $this->assertStringContainsString('健康・生活', $html);
        $this->assertStringContainsString('児A', $html);
    }
}
