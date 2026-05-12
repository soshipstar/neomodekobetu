<?php

namespace Tests\Feature;

use App\Models\AbsenceNotification;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R7 / B134: 欠席連絡へのスタッフアドバイスを保護者チャットへ自動投稿する
 *
 * 差分カテゴリ: logic
 *
 * 報告内容: スタッフが欠席連絡画面にアドバイスを入力しても保護者がそのページを
 * 開かないと気付けない。保護者チャットに反映してほしい。
 *
 * 修正: AttendanceController::updateAdvice() で advice 保存後、対象生徒の
 * 保護者チャットルームにスタッフ送信のメッセージとして自動投稿する。
 */
class B134_AbsenceAdviceChatPostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   classroom:Classroom, staff:User, guardian:User, student:Student,
     *   absence:AbsenceNotification
     * }
     */
    private function fixture(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室B134', 'is_active' => true]);

        $staff = User::create([
            'username'     => 'staff_b134_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'B134スタッフ',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $guardian = User::create([
            'username'     => 'guardian_b134_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'B134保護者',
            'user_type'    => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'guardian_id'  => $guardian->id,
            'student_name' => 'B134児童',
            'is_active'    => true,
            'status'       => 'active',
        ]);

        $absence = AbsenceNotification::create([
            'student_id'   => $student->id,
            'absence_date' => '2026-05-15',
            'reason'       => '発熱',
        ]);

        return compact('classroom', 'staff', 'guardian', 'student', 'absence');
    }

    /**
     * アドバイス入力時に保護者チャットへ自動投稿される
     */
    public function test_advice_is_posted_to_guardian_chat(): void
    {
        ['staff' => $staff, 'guardian' => $guardian, 'student' => $student, 'absence' => $absence] = $this->fixture();

        $adviceText = '咳が気になる場合は水分補給と安静を心がけてください。';

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => $adviceText,
            ]);

        $response->assertStatus(200);

        // 保護者と児童に紐づく ChatRoom が作成 (またはあれば再利用) されている
        $room = ChatRoom::where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->first();
        $this->assertNotNull($room, 'ChatRoom should be created');

        // メッセージが投稿されている (スタッフ送信)
        $msg = ChatMessage::where('room_id', $room->id)
            ->where('sender_type', 'staff')
            ->where('sender_id', $staff->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($msg, 'ChatMessage should be created');
        $this->assertStringContainsString($adviceText, $msg->message);
        $this->assertStringContainsString('5月15日', $msg->message);
        $this->assertStringContainsString('アドバイス', $msg->message);
    }

    /**
     * 同じアドバイス内容を再保存しても重複投稿されない (previousAdvice === advice 判定)
     */
    public function test_resaving_same_advice_does_not_repost(): void
    {
        ['staff' => $staff, 'student' => $student, 'guardian' => $guardian, 'absence' => $absence] = $this->fixture();

        $adviceText = '最初のアドバイス';

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", ['advice' => $adviceText])
            ->assertStatus(200);

        // 同じ内容で再保存
        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", ['advice' => $adviceText])
            ->assertStatus(200);

        $room = ChatRoom::where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->first();
        $count = ChatMessage::where('room_id', $room->id)
            ->where('sender_id', $staff->id)
            ->count();
        $this->assertSame(1, $count, 'Identical advice should not duplicate chat posts');
    }

    /**
     * 内容変更時は再投稿される
     */
    public function test_changed_advice_creates_new_chat_post(): void
    {
        ['staff' => $staff, 'student' => $student, 'guardian' => $guardian, 'absence' => $absence] = $this->fixture();

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", ['advice' => '最初のアドバイス'])
            ->assertStatus(200);

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", ['advice' => '更新版のアドバイス'])
            ->assertStatus(200);

        $room = ChatRoom::where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->first();
        $count = ChatMessage::where('room_id', $room->id)
            ->where('sender_id', $staff->id)
            ->count();
        $this->assertSame(2, $count, 'Changed advice should create a second chat post');
    }

    /**
     * クリア (空文字保存) は投稿しない
     */
    public function test_clearing_advice_does_not_post_to_chat(): void
    {
        ['staff' => $staff, 'student' => $student, 'guardian' => $guardian, 'absence' => $absence] = $this->fixture();

        // クリア (advice=null)
        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", ['advice' => null])
            ->assertStatus(200);

        $room = ChatRoom::where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->first();
        // ChatRoom 自体作成されない可能性もあるが、作成されてもメッセージはゼロ
        if ($room) {
            $count = ChatMessage::where('room_id', $room->id)->count();
            $this->assertSame(0, $count);
        }
    }
}
