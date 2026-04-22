<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\MeetingRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * バグ報告 #28: 保護者からの【面談日程の再調整】に対しスタッフが回答するボタンが表示されない
 *
 * 差分カテゴリ: logic
 * 背景: Guardian\MeetingController::update が ChatMessage 作成時に
 *       meeting_request_id を保存していなかったため、ChatBubble 側の
 *       `message.meeting_request_id && ...` ガードでボタンが描画されなかった。
 */
class BugReport28_GuardianMeetingCounterLinkTest extends TestCase
{
    use RefreshDatabase;

    private function setupParties(): array
    {
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $staff = User::create([
            'username' => 's_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'staff', 'user_type' => 'staff',
            'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'guardian', 'user_type' => 'guardian', 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => 'stu', 'classroom_id' => $c->id, 'guardian_id' => $guardian->id,
        ]);
        ChatRoom::create(['student_id' => $student->id, 'guardian_id' => $guardian->id]);
        $meeting = MeetingRequest::create([
            'classroom_id' => $c->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'staff_id' => $staff->id,
            'purpose' => 'test',
            'candidate_dates' => ['2026-05-01T14:00', '2026-05-02T14:00'],
            'status' => 'pending',
        ]);
        return [$c, $staff, $guardian, $student, $meeting];
    }

    public function test_guardian_counter_chat_message_links_to_meeting(): void
    {
        [$c, $staff, $guardian, $student, $meeting] = $this->setupParties();

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/meetings/{$meeting->id}/respond", [
                'action' => 'counter',
                'counter_date1' => '2026-05-03T14:00',
                'counter_message' => 'すみません',
            ]);

        $response->assertStatus(200);

        $chat = ChatMessage::where('message_type', 'meeting_counter')->latest('id')->first();
        $this->assertNotNull($chat);
        $this->assertEquals($meeting->id, $chat->meeting_request_id,
            'meeting_counter chat message must carry meeting_request_id for the "面談予約を確認" button to render');
    }

    public function test_guardian_select_confirmed_chat_message_links_to_meeting(): void
    {
        [$c, $staff, $guardian, $student, $meeting] = $this->setupParties();

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/meetings/{$meeting->id}/respond", [
                'action' => 'select',
                'selected_date' => '2026-05-01T14:00',
            ]);

        $response->assertStatus(200);

        $chat = ChatMessage::where('message_type', 'meeting_confirmed')->latest('id')->first();
        $this->assertNotNull($chat);
        $this->assertEquals($meeting->id, $chat->meeting_request_id);
    }
}
