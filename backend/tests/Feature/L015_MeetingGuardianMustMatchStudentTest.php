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
 * L-015: 面談作成時、保護者は対象生徒の登録保護者と一致しなければならない。
 *
 * 背景(P0 越境防止): chat_rooms / meeting_requests は guardian_id を生徒とは独立に
 * 保存するため、面談作成フォームで別家庭の保護者を選んでしまうと、その保護者が別生徒の
 * 面談・チャットを閲覧できる越境が発生する(2026-06-01 鈴木直→三島木英宏の実事故)。
 * MeetingController::store で guardian_id が student.guardian_id と一致することを必須化する。
 *
 * 差分カテゴリ: auth
 */
class L015_MeetingGuardianMustMatchStudentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    /** @return array{0:User,1:Student,2:User,3:User} */
    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_m_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $guardianA = User::create([
            'username' => 'gA_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '鈴木 卓',
            'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $guardianB = User::create([
            'username' => 'gB_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '三島木 英宏',
            'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '鈴木 直', 'classroom_id' => $classroom->id, 'guardian_id' => $guardianA->id,
            'status' => 'active', 'is_active' => true,
        ]);

        return [$staff, $student, $guardianA, $guardianB];
    }

    private function payload(int $studentId, int $guardianId): array
    {
        return [
            'student_id'      => $studentId,
            'guardian_id'     => $guardianId,
            'purpose'         => '面談',
            'candidate_dates' => ['2026-07-01 10:00:00'],
        ];
    }

    public function test_rejects_guardian_not_belonging_to_student(): void
    {
        [$staff, $student, , $guardianB] = $this->setupContext();

        // 別家庭の保護者(三島木)を選択 → 拒否
        $res = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/meetings', $this->payload($student->id, $guardianB->id));

        $res->assertStatus(422);
        $res->assertJsonPath('success', false);
        $this->assertSame(0, MeetingRequest::count());
    }

    public function test_allows_matching_guardian(): void
    {
        [$staff, $student, $guardianA] = $this->setupContext();

        $res = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/meetings', $this->payload($student->id, $guardianA->id));

        $res->assertStatus(201);
        $res->assertJsonPath('success', true);
        $this->assertSame(1, MeetingRequest::count());
        $this->assertSame($guardianA->id, MeetingRequest::first()->guardian_id);
    }
}
