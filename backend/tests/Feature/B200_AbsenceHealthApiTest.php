<?php

namespace Tests\Feature;

use App\Models\AbsenceNotification;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LR-007 Phase 2 (api): 欠席連絡の体調情報・スタッフアドバイス API
 *
 * 差分カテゴリ: api
 * 背景: 淡田さん要望対応の Phase 2。
 *  - Guardian/AbsenceController::store の validation を体温/症状/通院/困りごとに対応
 *  - Staff/AttendanceController::updateAdvice (新規) でスタッフがアドバイスを保存
 */
class B200_AbsenceHealthApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupFamily(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $guardian = User::create([
            'username'  => 'g_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '保護者A',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
            'guardian_id'  => $guardian->id,
        ]);
        $staff = User::create([
            'username'     => 's_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        return [$guardian, $student, $staff, $classroom];
    }

    public function test_guardian_can_post_absence_with_health_fields(): void
    {
        [$guardian, $student] = $this->setupFamily();

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson('/api/guardian/absence', [
                'student_id'             => $student->id,
                'absence_date'           => now()->addDay()->toDateString(),
                'reason'                 => '発熱のため',
                'body_temperature'       => 38.5,
                'hospital_visit'         => true,
                'symptom_headache'       => true,
                'symptom_cough'          => true,
                'symptom_runny_nose'     => true,
                'other_concerns'         => '夜中ぐずっていました',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $absence = AbsenceNotification::first();
        $this->assertEquals('38.5', (string) $absence->body_temperature);
        $this->assertTrue($absence->hospital_visit);
        $this->assertTrue($absence->symptom_headache);
        $this->assertTrue($absence->symptom_cough);
        $this->assertFalse($absence->symptom_abdominal_pain);
        $this->assertEquals('夜中ぐずっていました', $absence->other_concerns);
    }

    public function test_guardian_can_post_absence_without_health_fields(): void
    {
        [$guardian, $student] = $this->setupFamily();

        // 体調情報を一切渡さなくても登録できる (後方互換)
        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson('/api/guardian/absence', [
                'student_id'   => $student->id,
                'absence_date' => now()->addDay()->toDateString(),
                'reason'       => '私用',
            ]);

        $response->assertStatus(201);
        $absence = AbsenceNotification::first();
        $this->assertNull($absence->body_temperature);
        $this->assertFalse($absence->hospital_visit);
        $this->assertFalse($absence->symptom_cough);
    }

    public function test_body_temperature_out_of_range_is_rejected(): void
    {
        [$guardian, $student] = $this->setupFamily();

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson('/api/guardian/absence', [
                'student_id'       => $student->id,
                'absence_date'     => now()->addDay()->toDateString(),
                'reason'           => 'テスト',
                'body_temperature' => 99.9, // 30〜45 の範囲外
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body_temperature');
    }

    public function test_staff_can_save_advice_and_records_author_and_time(): void
    {
        [, $student, $staff] = $this->setupFamily();

        $absence = AbsenceNotification::create([
            'student_id'   => $student->id,
            'absence_date' => '2026-05-09',
            'reason'       => '体調不良',
            'body_temperature' => 38.0,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => '十分な休養と水分補給をお願いします。',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $fresh = $absence->fresh();
        $this->assertEquals('十分な休養と水分補給をお願いします。', $fresh->advice);
        $this->assertEquals($staff->id, $fresh->advice_by);
        $this->assertNotNull($fresh->advice_at);
    }

    public function test_staff_clearing_advice_resets_author_and_time(): void
    {
        [, $student, $staff] = $this->setupFamily();

        $absence = AbsenceNotification::create([
            'student_id'   => $student->id,
            'absence_date' => '2026-05-09',
            'reason'       => '体調不良',
            'advice'       => '休養を',
            'advice_by'    => $staff->id,
            'advice_at'    => now(),
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => '',
            ]);

        $response->assertStatus(200);
        $fresh = $absence->fresh();
        $this->assertNull($fresh->advice);
        $this->assertNull($fresh->advice_by);
        $this->assertNull($fresh->advice_at);
    }

    public function test_advice_endpoint_rejects_other_classroom_access(): void
    {
        [, , $staff] = $this->setupFamily();

        // 別教室の生徒の欠席連絡
        $otherClassroom = Classroom::create(['classroom_name' => '教室B', 'is_active' => true]);
        $otherStudent = Student::create([
            'student_name' => '別教室の生徒',
            'classroom_id' => $otherClassroom->id,
        ]);
        $absence = AbsenceNotification::create([
            'student_id'   => $otherStudent->id,
            'absence_date' => '2026-05-09',
            'reason'       => '...',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => '不正アクセス試行',
            ]);

        $response->assertStatus(403);
        $this->assertNull($absence->fresh()->advice);
    }
}
