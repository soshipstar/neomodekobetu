<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\ConsentRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\ConsentService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 同意UI: 児童の学習同意をスタッフが代理記録するAPI。
 * 学習可否は施設の集計同意とのANDで決まること、越境は403、履歴が追記されることを検証。
 *
 * 差分カテゴリ: api
 */
class AiConsentStudentApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ConsentDefinitionSeeder::class);
        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->student = Student::create([
            'student_name' => '児A', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true,
        ]);
        $this->staff = User::create([
            'username' => 'staff_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    public function test_staff_records_student_consent_and_and_condition(): void
    {
        // 初期: 未同意・施設も未同意 → 学習不可
        $this->actingAs($this->staff, 'sanctum')->getJson("/api/staff/students/{$this->student->id}/ai-consent")
            ->assertStatus(200)
            ->assertJsonPath('data.ai_consent_learning', false)
            ->assertJsonPath('data.company_aggregate', false)
            ->assertJsonPath('data.can_use_for_learning', false);

        // スタッフが代理記録(紙・メモ付き)
        $this->actingAs($this->staff, 'sanctum')->putJson("/api/staff/students/{$this->student->id}/ai-consent", [
            'granted' => true, 'acquisition_method' => 'paper', 'note' => '2026-06-14 紙面同意を受領',
        ])->assertStatus(200)
            ->assertJsonPath('data.ai_consent_learning', true)
            // 施設が未同意なのでANDは不成立
            ->assertJsonPath('data.can_use_for_learning', false);

        $this->assertTrue($this->student->fresh()->ai_consent_learning);
        $rec = ConsentRecord::where('subject_type', 'student')->where('subject_id', $this->student->id)->latest('id')->first();
        $this->assertSame('model_learning', $rec->consent_key);
        $this->assertSame('staff_proxy', $rec->granted_by_role);
        $this->assertSame('paper', $rec->acquisition_method);
        $this->assertSame('2026-06-14 紙面同意を受領', $rec->note);
        $this->assertSame($this->staff->id, $rec->granted_by_user_id);

        // 施設も同意 → ANDが成立し学習可
        (new ConsentService())->recordCompanyConsent($this->company, true);
        $this->actingAs($this->staff, 'sanctum')->getJson("/api/staff/students/{$this->student->id}/ai-consent")
            ->assertStatus(200)
            ->assertJsonPath('data.company_aggregate', true)
            ->assertJsonPath('data.can_use_for_learning', true);
    }

    public function test_revoke_appends_record(): void
    {
        $this->actingAs($this->staff, 'sanctum')->putJson("/api/staff/students/{$this->student->id}/ai-consent", ['granted' => true])->assertStatus(200);
        $this->actingAs($this->staff, 'sanctum')->putJson("/api/staff/students/{$this->student->id}/ai-consent", ['granted' => false])->assertStatus(200);

        $this->assertFalse($this->student->fresh()->ai_consent_learning);
        $this->assertNull($this->student->fresh()->ai_consent_learning_version);
        $records = ConsentRecord::where('subject_type', 'student')->where('subject_id', $this->student->id)->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame('granted', $records[0]->state);
        $this->assertSame('revoked', $records[1]->state);
    }

    public function test_cross_classroom_staff_is_forbidden(): void
    {
        $otherRoom = Classroom::create(['classroom_name' => '別事業所', 'company_id' => $this->company->id, 'is_active' => true]);
        $otherStaff = User::create([
            'username' => 'staff2_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '別スタッフ',
            'user_type' => 'staff', 'classroom_id' => $otherRoom->id, 'is_active' => true,
        ]);

        $this->actingAs($otherStaff, 'sanctum')->getJson("/api/staff/students/{$this->student->id}/ai-consent")->assertStatus(403);
        $this->actingAs($otherStaff, 'sanctum')->putJson("/api/staff/students/{$this->student->id}/ai-consent", ['granted' => true])->assertStatus(403);
        $this->assertFalse($this->student->fresh()->ai_consent_learning);
    }

    public function test_validation_rejects_bad_method(): void
    {
        $this->actingAs($this->staff, 'sanctum')->putJson("/api/staff/students/{$this->student->id}/ai-consent", [
            'granted' => true, 'acquisition_method' => 'telepathy',
        ])->assertStatus(422);
    }
}
