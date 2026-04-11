<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CH010: チャットクイック通知エンドポイントのテスト
 *
 * 差分カテゴリ: api
 * 背景: 帰宅・到着などの定型メッセージを 1 タップで送れる機能を追加した。
 *  - スタッフ側: /api/staff/chat/quick-broadcast (選択した複数ルームへ)
 *  - 保護者側: /api/guardian/chat/rooms/{room}/quick-action (1 ルームへ)
 */
class CH010_ChatQuickNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $staff = User::create([
            'username' => 'staff_ch010',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $guardian1 = User::create([
            'username' => 'guardian1_ch010',
            'password' => bcrypt('pass'),
            'full_name' => '保護者1',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
        $guardian2 = User::create([
            'username' => 'guardian2_ch010',
            'password' => bcrypt('pass'),
            'full_name' => '保護者2',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => '児童1',
            'guardian_id' => $guardian1->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $student2 = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => '児童2',
            'guardian_id' => $guardian2->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $room1 = ChatRoom::create([
            'student_id' => $student1->id,
            'guardian_id' => $guardian1->id,
            'last_message_at' => now(),
        ]);
        $room2 = ChatRoom::create([
            'student_id' => $student2->id,
            'guardian_id' => $guardian2->id,
            'last_message_at' => now(),
        ]);

        return compact('staff', 'guardian1', 'guardian2', 'student1', 'student2', 'room1', 'room2');
    }

    public function test_staff_quick_broadcast_departure_to_selected_rooms(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action' => 'departure',
                'room_ids' => [$f['room1']->id],
            ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('sent_count'));
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $f['room1']->id,
            'message_type' => 'quick_departure',
        ]);
        // room2 には送られない
        $this->assertDatabaseMissing('chat_messages', [
            'room_id' => $f['room2']->id,
            'message_type' => 'quick_departure',
        ]);
    }

    public function test_staff_quick_broadcast_arrival_to_multiple_rooms(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action' => 'arrival',
                'room_ids' => [$f['room1']->id, $f['room2']->id],
            ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('sent_count'));
        $this->assertDatabaseCount('chat_messages', 2);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $f['room1']->id,
            'message_type' => 'quick_arrival',
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $f['room2']->id,
            'message_type' => 'quick_arrival',
        ]);
    }

    public function test_staff_quick_broadcast_requires_room_ids(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action' => 'departure',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('room_ids');
    }

    public function test_staff_quick_broadcast_rejects_invalid_action(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action' => 'invalid',
                'room_ids' => [$f['room1']->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('action');
    }

    public function test_guardian_quick_action_departed(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['guardian1'], 'sanctum')
            ->postJson("/api/guardian/chat/rooms/{$f['room1']->id}/quick-action", [
                'action' => 'departed',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $f['room1']->id,
            'sender_id' => $f['guardian1']->id,
            'sender_type' => 'guardian',
            'message_type' => 'quick_departed',
        ]);
    }

    public function test_guardian_quick_action_arrived_home(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['guardian1'], 'sanctum')
            ->postJson("/api/guardian/chat/rooms/{$f['room1']->id}/quick-action", [
                'action' => 'arrived_home',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'room_id' => $f['room1']->id,
            'message_type' => 'quick_home_arrival',
        ]);
    }

    public function test_guardian_cannot_use_quick_action_on_another_room(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['guardian1'], 'sanctum')
            ->postJson("/api/guardian/chat/rooms/{$f['room2']->id}/quick-action", [
                'action' => 'departed',
            ]);

        $response->assertStatus(403);
    }

    public function test_guardian_quick_action_rejects_invalid_action(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['guardian1'], 'sanctum')
            ->postJson("/api/guardian/chat/rooms/{$f['room1']->id}/quick-action", [
                'action' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('action');
    }
}
