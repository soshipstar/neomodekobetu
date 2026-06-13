<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\User;
use App\Services\ConsentService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 配管(S3): 連絡帳の統合文(integrated_note)について、AI下書きに対する
 * 人間の修正(途中保存)と送信時の最終文がセクション単位で蓄積されることを検証する。
 *
 * 差分カテゴリ: logic
 */
class AiLearningCaptureRenrakuchoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;
    private DailyRecord $record;

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
            'username' => 'staff_rk_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->record = DailyRecord::create([
            'classroom_id' => $this->room->id, 'record_date' => '2026-06-10',
            'activity_name' => '公園遊び', 'staff_id' => $this->staff->id,
        ]);
    }

    private function grantFullConsent(): void
    {
        $svc = new ConsentService();
        $svc->recordCompanyConsent($this->company, true);
        $svc->recordStudentConsent($this->student, true);
    }

    public function test_save_draft_then_send_captures_revisions(): void
    {
        $this->grantFullConsent();

        // AI生成済みの下書きを模す(generateIntegrated 相当の状態)
        IntegratedNote::create([
            'daily_record_id' => $this->record->id, 'student_id' => $this->student->id,
            'integrated_content' => 'AIが生成した下書き文', 'is_sent' => false,
        ]);

        // 途中保存: AI下書き → 人間が修正
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/renrakucho/{$this->record->id}/save-draft", [
            'notes' => [['student_id' => $this->student->id, 'content' => '職員が直した連絡帳文(本人は友だちと協力できた)']],
        ])->assertStatus(200);

        $draftRev = AiRevisionEvent::where('document_type', 'integrated_note')
            ->where('section_key', 'integrated_content')->where('edit_kind', 'save_draft')->first();
        $this->assertNotNull($draftRev);
        $this->assertSame('AIが生成した下書き文', $draftRev->before_text);
        $this->assertSame('職員が直した連絡帳文(本人は友だちと協力できた)', $draftRev->after_text);

        // 送信: さらに最終調整して送信(publish)
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/renrakucho/{$this->record->id}/send-to-guardians", [
            'notes' => [['student_id' => $this->student->id, 'content' => '送信時に最終調整した連絡帳文']],
        ])->assertStatus(200);

        $sentRev = AiRevisionEvent::where('document_type', 'integrated_note')
            ->where('edit_kind', 'publish')->first();
        $this->assertNotNull($sentRev);
        $this->assertSame('職員が直した連絡帳文(本人は友だちと協力できた)', $sentRev->before_text);
        $this->assertSame('送信時に最終調整した連絡帳文', $sentRev->after_text);
    }

    public function test_no_capture_without_consent(): void
    {
        IntegratedNote::create([
            'daily_record_id' => $this->record->id, 'student_id' => $this->student->id,
            'integrated_content' => 'AI下書き', 'is_sent' => false,
        ]);

        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/renrakucho/{$this->record->id}/save-draft", [
            'notes' => [['student_id' => $this->student->id, 'content' => '直した文']],
        ])->assertStatus(200);

        $this->assertSame(0, AiRevisionEvent::count());
    }
}
