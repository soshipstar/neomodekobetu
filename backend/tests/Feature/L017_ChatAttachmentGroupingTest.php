<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L-017: チャット添付一覧を attachment_path 単位に集約する(一斉送信=1ファイル表示)。
 *
 * 背景: 一斉送信は1つの物理ファイルを複数チャットにリンクするため、一覧で行が重複し
 *       容量バーと一致しなかった。path 単位で1行に集約し、削除は全参照+物理ファイルを
 *       まとめて消す。
 *
 * 差分カテゴリ: screen
 */
class L017_ChatAttachmentGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        Storage::fake('public');
    }

    public function test_broadcast_file_is_grouped_into_one_row_and_deletes_all_refs(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_grp_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_grp_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者',
            'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);

        // 一斉送信: 同一 attachment_path を3チャットにリンク (物理1ファイル)
        Storage::disk('public')->put('chat_attachments/broadcast.pdf', 'DATA');
        $msgs = [];
        for ($i = 0; $i < 3; $i++) {
            $student = Student::create(['student_name' => "生徒{$i}", 'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id]);
            $room = ChatRoom::create(['student_id' => $student->id, 'guardian_id' => $guardian->id]);
            $msgs[] = ChatMessage::create([
                'room_id' => $room->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => '一斉',
                'message_type' => 'broadcast',
                'attachment_path' => 'chat_attachments/broadcast.pdf', 'attachment_name' => 'broadcast.pdf',
                'attachment_size' => 1000, 'attachment_mime' => 'application/pdf',
            ]);
        }

        // 一覧: 3メッセージが1行に集約される
        $res = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/chat/attachments?classroom_id=' . $classroom->id);
        $res->assertStatus(200);
        $atts = collect($res->json('data.attachments'));
        $this->assertCount(1, $atts);
        $this->assertSame(3, $atts[0]['link_count']);
        $this->assertCount(3, $atts[0]['refs']);
        $this->assertSame(1000, $atts[0]['size']);
        $this->assertSame(1000, $res->json('data.summary.used_bytes')); // 容量も1回計上

        // 削除: 集約行の全参照を渡すと、全メッセージの添付が消え物理ファイルも削除
        $del = $this->actingAs($staff, 'sanctum')->postJson('/api/staff/chat/attachments/delete', [
            'classroom_id' => $classroom->id,
            'items' => $atts[0]['refs'],
        ]);
        $del->assertStatus(200);
        Storage::disk('public')->assertMissing('chat_attachments/broadcast.pdf');
        $this->assertSame(0, $res2 = $del->json('data.summary.used_bytes'));
        foreach ($msgs as $m) {
            $this->assertNull($m->fresh()->attachment_path);
        }
    }
}
