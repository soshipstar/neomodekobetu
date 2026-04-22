<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * バグ報告 #20: 保護者ページからチャットを見ると送信者名が表示されない
 *
 * 差分カテゴリ: api
 * 背景: Guardian\ChatController::messages / archivedMessages が
 *       $msg->sender_name（文字列）を返していたが、フロント ChatBubble は
 *       message.sender.full_name（ネストオブジェクト）を参照していた。
 *       Staff 側と同じ sender オブジェクト形式に揃える。
 */
class BugReport20_GuardianChatSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_messages_return_sender_object(): void
    {
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $staff = User::create([
            'username' => 's_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => '田中スタッフ', 'user_type' => 'staff',
            'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => '鈴木保護者', 'user_type' => 'guardian', 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => 'stu', 'classroom_id' => $c->id, 'guardian_id' => $guardian->id,
        ]);
        $room = ChatRoom::create([
            'student_id' => $student->id, 'guardian_id' => $guardian->id,
        ]);

        ChatMessage::create([
            'room_id' => $room->id, 'sender_type' => 'staff', 'sender_id' => $staff->id,
            'message' => 'こんにちは', 'message_type' => 'normal',
        ]);
        ChatMessage::create([
            'room_id' => $room->id, 'sender_type' => 'guardian', 'sender_id' => $guardian->id,
            'message' => 'お世話になります', 'message_type' => 'normal',
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->getJson("/api/guardian/chat/rooms/{$room->id}/messages");

        $response->assertStatus(200);
        $messages = $response->json('data');
        $this->assertCount(2, $messages);

        // Staff message: sender should be an object with id & full_name
        $staffMsg = collect($messages)->firstWhere('sender_type', 'staff');
        $this->assertIsArray($staffMsg['sender']);
        $this->assertEquals($staff->id, $staffMsg['sender']['id']);
        $this->assertEquals('田中スタッフ', $staffMsg['sender']['full_name']);

        $guardianMsg = collect($messages)->firstWhere('sender_type', 'guardian');
        $this->assertIsArray($guardianMsg['sender']);
        $this->assertEquals('鈴木保護者', $guardianMsg['sender']['full_name']);
    }
}
