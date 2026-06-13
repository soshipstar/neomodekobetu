<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\AssessmentPeriod;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\ConsentService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 配管(S3): 職員アセスメントの store/update 経由で人間修正イベントが
 * セクション単位で蓄積されること(学習同意=AND)を検証する。document_idは期間(period)。
 *
 * 差分カテゴリ: logic
 */
class AiLearningCaptureAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;
    private AssessmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ConsentDefinitionSeeder::class);

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->student = Student::create(['student_name' => '児A', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_as_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->period = AssessmentPeriod::create([
            'student_id' => $this->student->id, 'period_name' => '2026前期',
            'start_date' => '2026-04-01', 'end_date' => '2026-09-30', 'is_active' => true,
        ]);
    }

    private function grantFullConsent(): void
    {
        $svc = new ConsentService();
        $svc->recordCompanyConsent($this->company, true);
        $svc->recordStudentConsent($this->student, true);
    }

    public function test_store_then_update_captures_assessment_revisions(): void
    {
        $this->grantFullConsent();

        // 初回保存(作成): before空 → after本文。生成イベントと紐づく学習素材。
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/assessment/{$this->period->id}", [
            'action' => 'save',
            'short_term_goal' => '短期目標の初期文',
            'health_life' => '健康・生活の初期所見',
        ])->assertStatus(200);

        $stg = AiRevisionEvent::where('document_type', 'assessment_staff')->where('document_id', $this->period->id)
            ->where('section_key', 'short_term_goal')->first();
        $this->assertNotNull($stg);
        $this->assertSame('短期目標の初期文', $stg->after_text);

        // 2回目(更新): short_term_goal のみ変更、health_life は据え置き
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/assessment/{$this->period->id}", [
            'action' => 'update',
            'short_term_goal' => '短期目標の改訂文(集団で役割を担う)',
            'health_life' => '健康・生活の初期所見',
        ])->assertStatus(200);

        $updated = AiRevisionEvent::where('document_id', $this->period->id)
            ->where('section_key', 'short_term_goal')->orderByDesc('id')->first();
        $this->assertSame('短期目標の初期文', $updated->before_text);
        $this->assertSame('短期目標の改訂文(集団で役割を担う)', $updated->after_text);
        $this->assertSame('submit', $updated->edit_kind);

        // health_life は2回目で変わっていないので、新たな行は積まれない(初回の1件のみ)
        $this->assertSame(1, AiRevisionEvent::where('document_id', $this->period->id)
            ->where('section_key', 'health_life')->count());
    }

    public function test_no_capture_without_consent(): void
    {
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/assessment/{$this->period->id}", [
            'action' => 'save', 'short_term_goal' => '初期',
        ])->assertStatus(200);

        $this->assertSame(0, AiRevisionEvent::count());
    }
}
