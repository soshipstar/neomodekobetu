<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\MeetingRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-016: 確定済み面談をキャンセルできること。確定済みのキャンセルは保護者へチャット通知する。
 *
 * 背景(要望): 一度確定した面談を、急な変更・ミスでキャンセルしたい。確定済みは保護者が
 * 認識しているため、キャンセル時にチャットで通知する。
 *
 * 差分カテゴリ: screen
 */
class L016_ConfirmedMeetingCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    /** @return array{0:User,1:Student,2:User,3:ChatRoom} */
    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_c_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_c_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者',
            'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A', 'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id,
            'status' => 'active', 'is_active' => true,
        ]);
        $room = ChatRoom::create(['student_id' => $student->id, 'guardian_id' => $guardian->id]);

        return [$staff, $student, $guardian, $room];
    }

    public function test_confirmed_meeting_can_be_cancelled_and_notifies_guardian(): void
    {
        [$staff, $student, $guardian, $room] = $this->setupContext();
        $meeting = MeetingRequest::create([
            'classroom_id' => $staff->classroom_id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
            'staff_id' => $staff->id, 'purpose' => '個別面談',
            'confirmed_date' => '2026-07-01 10:00:00', 'confirmed_by' => 'staff', 'confirmed_at' => now(),
            'status' => 'confirmed',
        ]);

        $before = ChatMessage::where('room_id', $room->id)->count();

        $res = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/meetings/{$meeting->id}", ['action' => 'cancel']);
        $res->assertStatus(200);

        $this->assertSame('cancelled', $meeting->fresh()->status);
        // 保護者へキャンセル通知のチャットが1件追加される
        $msgs = ChatMessage::where('room_id', $room->id)->get();
        $this->assertCount($before + 1, $msgs);
        $this->assertStringContainsString('面談がキャンセルされました', $msgs->last()->message);
    }

    public function test_cancelling_pending_meeting_does_not_notify(): void
    {
        [$staff, $student, $guardian, $room] = $this->setupContext();
        $meeting = MeetingRequest::create([
            'classroom_id' => $staff->classroom_id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
            'staff_id' => $staff->id, 'purpose' => '個別面談',
            'candidate_dates' => ['2026-07-01 10:00:00'], 'status' => 'pending',
        ]);

        $before = ChatMessage::where('room_id', $room->id)->count();

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/meetings/{$meeting->id}", ['action' => 'cancel'])
            ->assertStatus(200);

        $this->assertSame('cancelled', $meeting->fresh()->status);
        // 未確定のキャンセルはチャット通知しない
        $this->assertSame($before, ChatMessage::where('room_id', $room->id)->count());
    }
}
