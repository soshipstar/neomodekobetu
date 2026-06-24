<?php

namespace Tests\Feature;

use App\Models\AbsenceResponseRecord;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 欠席時に作る書類(欠席時対応記録)への施設設定の反映。
 * POST /api/staff/absence-response
 *
 * 差分カテゴリ: logic
 * - 事業所が欠席時対応加算を算定しない設定(absence_addition_enabled=false)のとき
 *   記録は作成不可(422)。算定する設定(=既定 true)のときは作成できる。
 */
class AbsenceAdditionGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function staff(Classroom $room): User
    {
        return User::create([
            'username' => 'staff_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $room->id, 'is_active' => true,
        ]);
    }

    private function student(Classroom $room): Student
    {
        return Student::create([
            'student_name' => '児A', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true,
        ]);
    }

    public function test_can_create_absence_response_when_enabled(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        // 既定 true（算定する）
        $staff = $this->staff($room);
        $student = $this->student($room);

        $res = $this->actingAs($staff, 'sanctum')->postJson('/api/staff/absence-response', [
            'student_id' => $student->id,
            'absence_date' => '2026-05-10',
            'response_content' => '電話にて保護者へ状況確認を行った。',
            'contact_method' => '電話',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $this->assertDatabaseHas('absence_response_records', [
            'student_id' => $student->id,
            'classroom_id' => $room->id,
            'absence_date' => '2026-05-10',
        ]);
    }

    public function test_cannot_create_absence_response_when_disabled(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true,
            'absence_addition_enabled' => false,
        ]);
        $staff = $this->staff($room);
        $student = $this->student($room);

        $res = $this->actingAs($staff, 'sanctum')->postJson('/api/staff/absence-response', [
            'student_id' => $student->id,
            'absence_date' => '2026-05-10',
            'response_content' => '電話にて保護者へ状況確認を行った。',
            'contact_method' => '電話',
        ]);

        $res->assertStatus(422);
        $this->assertSame(0, AbsenceResponseRecord::count());
    }
}
