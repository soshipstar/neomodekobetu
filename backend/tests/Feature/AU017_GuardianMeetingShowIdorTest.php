<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\MeetingRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S-007: 保護者の面談詳細取得 (GET /api/guardian/meetings/{meeting}) は
 * 自分宛ての面談のみ閲覧可。
 *
 * 背景(不具合/IDOR): Guardian/MeetingController::show が一切の所有者チェックを
 * しておらず、任意の meeting ID を指定すれば他家庭の面談詳細(目的・記録・児童名)を
 * 取得できた。guardian_id 一致を必須にして 403 を返すよう修正。
 *
 * 差分カテゴリ: auth
 */
class AU017_GuardianMeetingShowIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function makeGuardian(Classroom $room, string $tag): User
    {
        return User::create([
            'username' => "g_{$tag}_" . uniqid(),
            'password' => bcrypt('p'),
            'full_name' => "保護者{$tag}",
            'user_type' => 'guardian',
            'classroom_id' => $room->id,
            'is_active' => true,
        ]);
    }

    public function test_guardian_cannot_view_other_guardians_meeting(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_m_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $room->id, 'is_active' => true,
        ]);

        $guardianA = $this->makeGuardian($room, 'A');
        $guardianB = $this->makeGuardian($room, 'B');

        $studentB = Student::create([
            'student_name' => '児B', 'classroom_id' => $room->id, 'guardian_id' => $guardianB->id,
            'status' => 'active', 'is_active' => true,
        ]);

        // 保護者B宛ての面談
        $meetingB = MeetingRequest::create([
            'classroom_id' => $room->id,
            'student_id' => $studentB->id,
            'guardian_id' => $guardianB->id,
            'staff_id' => $staff->id,
            'purpose' => '個別面談(Bのみ)',
            'status' => 'pending',
        ]);

        // 保護者A が B の面談詳細を覗こうとする → 403
        $resA = $this->actingAs($guardianA, 'sanctum')
            ->getJson("/api/guardian/meetings/{$meetingB->id}");
        $resA->assertStatus(403);

        // 保護者B 自身は閲覧可 → 200
        $resB = $this->actingAs($guardianB, 'sanctum')
            ->getJson("/api/guardian/meetings/{$meetingB->id}");
        $resB->assertStatus(200);
        $resB->assertJsonPath('data.id', $meetingB->id);
    }
}
