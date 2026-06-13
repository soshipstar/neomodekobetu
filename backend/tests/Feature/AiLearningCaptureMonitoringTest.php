<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\IndividualSupportPlan;
use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\ConsentService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 配管(S3): モニタリングの update 経由で人間修正イベントが
 * セクション単位で蓄積されること(学習同意=AND・散文のみ)を検証する。
 *
 * 差分カテゴリ: logic
 */
class AiLearningCaptureMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;
    private MonitoringRecord $monitoring;

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
            'username' => 'staff_mon_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);

        $plan = IndividualSupportPlan::create([
            'student_id' => $this->student->id, 'classroom_id' => $this->room->id, 'status' => 'official',
        ]);
        $this->monitoring = MonitoringRecord::create([
            'plan_id' => $plan->id, 'student_id' => $this->student->id, 'classroom_id' => $this->room->id,
            'monitoring_date' => '2026-06-01', 'overall_comment' => '初期の総合所見', 'is_draft' => true,
        ]);
        MonitoringDetail::create([
            'monitoring_id' => $this->monitoring->id, 'domain' => '健康・生活',
            'achievement_level' => '進行中', 'comment' => '初期のコメント', 'sort_order' => 0,
        ]);
    }

    private function grantFullConsent(): void
    {
        $svc = new ConsentService();
        $svc->recordCompanyConsent($this->company, true);
        $svc->recordStudentConsent($this->student, true);
    }

    public function test_update_endpoint_captures_monitoring_revisions(): void
    {
        $this->grantFullConsent();

        $res = $this->actingAs($this->staff, 'sanctum')->putJson("/api/staff/monitoring/{$this->monitoring->id}", [
            'overall_comment' => '改訂した総合所見(集団活動で役割を担えた)',
            'is_draft' => true,
            'details' => [
                ['domain' => '健康・生活', 'achievement_level' => '達成', 'comment' => '改訂したコメント(自分で着替えられた)'],
            ],
        ]);
        $res->assertStatus(200);

        // 本体所見の修正が記録される
        $overall = AiRevisionEvent::where('document_type', 'monitoring')->where('document_id', $this->monitoring->id)
            ->where('section_key', 'overall_comment')->first();
        $this->assertNotNull($overall);
        $this->assertSame('初期の総合所見', $overall->before_text);
        $this->assertSame('改訂した総合所見(集団活動で役割を担えた)', $overall->after_text);
        $this->assertSame('save_draft', $overall->edit_kind);

        // 明細(domain別)のコメント修正が記録される(domainは5領域コードへ正規化される)
        $detail = AiRevisionEvent::where('document_id', $this->monitoring->id)
            ->where('section_key', 'detail:health_life:comment')->first();
        $this->assertNotNull($detail);
        $this->assertSame('初期のコメント', $detail->before_text);
        $this->assertSame('改訂したコメント(自分で着替えられた)', $detail->after_text);
    }

    public function test_no_capture_without_consent(): void
    {
        // 同意なし: 更新は通るが蓄積はされない
        $this->actingAs($this->staff, 'sanctum')->putJson("/api/staff/monitoring/{$this->monitoring->id}", [
            'overall_comment' => '変更後', 'is_draft' => true,
        ])->assertStatus(200);

        $this->assertSame(0, AiRevisionEvent::count());
    }
}
