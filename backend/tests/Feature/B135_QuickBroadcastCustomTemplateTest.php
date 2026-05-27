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
 * R1 / B135: クイック通知 (これから帰ります / 到着しました) を編集可能にする
 *
 * 差分カテゴリ: logic
 *
 * 報告内容: 「到着しました（入室時）」の自動メッセージ「対応ありがとうございました」が
 * 不要なので、送信ON/OFF + 定型メッセージ編集できるようにしたい。
 *
 * 修正:
 * - quickBroadcast が custom_body を受け付けるようになり、リクエスト毎に内容を変更可能
 * - quickBroadcastTemplates / updateQuickBroadcastTemplates で教室毎の既定値を保存可能
 *   (classrooms.settings.quick_broadcast_templates に格納)
 * - enabled=false で保存された場合は 422 で送信拒否 (ON/OFF)
 */
class B135_QuickBroadcastCustomTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{classroom:Classroom, staff:User, guardian:User, student:Student, room:ChatRoom}
     */
    private function fixture(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室B135', 'is_active' => true]);

        $staff = User::create([
            'username'     => 'staff_b135_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'B135スタッフ',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $guardian = User::create([
            'username'     => 'guardian_b135_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'B135保護者',
            'user_type'    => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'guardian_id'  => $guardian->id,
            'student_name' => 'B135児童',
            'is_active'    => true,
            'status'       => 'active',
        ]);

        $room = ChatRoom::create([
            'student_id'  => $student->id,
            'guardian_id' => $guardian->id,
        ]);

        return compact('classroom', 'staff', 'guardian', 'student', 'room');
    }

    /**
     * 既定値が GET で取得できる (教室未保存時)
     *
     * 注: arrival の body は Bug #47 対応で「【到着しました】」のみに簡素化済
     * (ChatController::defaultQuickTemplate のコメント参照)。
     * 旧文言「【到着しました】\n\nご対応ありがとうございました。」を期待するアサーションは
     * 現実装と乖離していたためここで更新。
     */
    public function test_get_returns_system_defaults_when_no_classroom_settings(): void
    {
        ['staff' => $staff] = $this->fixture();

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/chat/quick-broadcast-templates');

        $response->assertStatus(200);
        $response->assertJsonPath('data.arrival.body', '【到着しました】');
        $response->assertJsonPath('data.arrival.enabled', true);
        $response->assertJsonPath('data.departure.body', "【これから帰ります】\n\nこれより帰路につきます。無事の帰宅をご確認ください。");
        $response->assertJsonPath('data.departure.enabled', true);
    }

    /**
     * テンプレートを更新して GET すると新しい値が返る
     */
    public function test_update_then_get_returns_saved_template(): void
    {
        ['staff' => $staff] = $this->fixture();

        $newBody = "【お預かりしました】\nご利用ありがとうございます。";
        $this->actingAs($staff, 'sanctum')
            ->putJson('/api/staff/chat/quick-broadcast-templates', [
                'arrival' => ['body' => $newBody, 'enabled' => true],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.arrival.body', $newBody);

        // 別リクエストでも保持されている
        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/chat/quick-broadcast-templates');
        $response->assertJsonPath('data.arrival.body', $newBody);
    }

    /**
     * custom_body を指定して送信できる
     */
    public function test_quick_broadcast_uses_custom_body(): void
    {
        ['staff' => $staff, 'room' => $room] = $this->fixture();

        $customText = "本日もありがとうございました（カスタム）";
        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action'      => 'arrival',
                'room_ids'    => [$room->id],
                'custom_body' => $customText,
            ]);

        $response->assertStatus(200);
        $msg = ChatMessage::where('room_id', $room->id)
            ->where('message_type', 'quick_arrival')
            ->latest('id')
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame($customText, $msg->message);
    }

    /**
     * 保存テンプレートが custom_body の指定がない場合に使われる
     */
    public function test_quick_broadcast_uses_saved_template_when_no_custom_body(): void
    {
        ['staff' => $staff, 'classroom' => $classroom, 'room' => $room] = $this->fixture();

        $classroom->settings = [
            'quick_broadcast_templates' => [
                'arrival' => ['body' => '保存テンプレ本文', 'enabled' => true],
            ],
        ];
        $classroom->save();

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action'   => 'arrival',
                'room_ids' => [$room->id],
            ]);

        $response->assertStatus(200);
        $msg = ChatMessage::where('room_id', $room->id)->latest('id')->first();
        $this->assertSame('保存テンプレ本文', $msg->message);
    }

    /**
     * enabled=false の場合は送信拒否 (422)
     */
    public function test_quick_broadcast_rejects_when_disabled(): void
    {
        ['staff' => $staff, 'classroom' => $classroom, 'room' => $room] = $this->fixture();

        $classroom->settings = [
            'quick_broadcast_templates' => [
                'arrival' => ['body' => 'なんでも', 'enabled' => false],
            ],
        ];
        $classroom->save();

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/chat/quick-broadcast', [
                'action'   => 'arrival',
                'room_ids' => [$room->id],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, ChatMessage::where('room_id', $room->id)->count());
    }
}
