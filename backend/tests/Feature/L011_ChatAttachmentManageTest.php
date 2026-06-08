<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\ClassroomPhoto;
use App\Models\Company;
use App\Models\StaffChatMessage;
use App\Models\StaffChatRoom;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\StudentChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L-011: チャット添付ファイル管理 (一覧 + 選択削除)。
 *
 * 背景: チャット添付は教室 200MB 上限。既存の deleteMessage はソフト削除のみで
 *       容量が解放されない。本機能は物理ファイルを実削除して容量を解放する。
 *
 * 差分カテゴリ: screen
 */
class L011_ChatAttachmentManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // テストDBに telescope_entries が無い環境でトランザクションが汚染されるのを防ぐ。
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        Storage::fake('public');
    }

    /** @return array{0:User,1:Classroom,2:Student} */
    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_att_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'スタッフA', 'user_type' => 'staff',
            'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_att_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => '保護者A', 'user_type' => 'guardian',
            'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A', 'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id,
        ]);

        return [$staff, $classroom, $student];
    }

    /** 3経路すべてに添付付きメッセージを作る。 */
    private function seedAttachments(User $staff, Classroom $classroom, Student $student): array
    {
        Storage::disk('public')->put('chat_attachments/g1.pdf', 'AAAA');
        Storage::disk('public')->put('chat_attachments/s1.pdf', 'BBBB');
        Storage::disk('public')->put('chat_attachments/st1.pdf', 'CCCC');

        $room = ChatRoom::create(['student_id' => $student->id, 'guardian_id' => $student->guardian_id]);
        $g = ChatMessage::create([
            'room_id' => $room->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => '',
            'attachment_path' => 'chat_attachments/g1.pdf', 'attachment_name' => 'g1.pdf',
            'attachment_size' => 1000, 'attachment_mime' => 'application/pdf',
        ]);

        $sroom = StudentChatRoom::create(['student_id' => $student->id]);
        $s = StudentChatMessage::create([
            'room_id' => $sroom->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => 'メモあり',
            'attachment_path' => 'chat_attachments/s1.pdf', 'attachment_original_name' => 's1.pdf', 'attachment_size' => 2000,
        ]);

        $stroom = StaffChatRoom::create([
            'classroom_id' => $classroom->id, 'room_type' => 'group', 'room_name' => 'スタッフ室', 'created_by' => $staff->id,
        ]);
        $st = StaffChatMessage::create([
            'room_id' => $stroom->id, 'sender_id' => $staff->id, 'message' => '',
            'attachment_path' => 'chat_attachments/st1.pdf', 'attachment_original_name' => 'st1.pdf', 'attachment_size' => 3000,
        ]);

        return ['guardian' => $g, 'student' => $s, 'staff' => $st];
    }

    public function test_index_lists_all_sources_and_excludes_other_classroom(): void
    {
        [$staff, $classroom, $student] = $this->setupContext();
        $this->seedAttachments($staff, $classroom, $student);

        // 別教室の添付 (一覧に出ないこと)
        $other = Classroom::create(['classroom_name' => '別教室', 'company_id' => $classroom->company_id, 'is_active' => true]);
        $otherStudent = Student::create(['student_name' => '別生徒', 'classroom_id' => $other->id]);
        $otherRoom = ChatRoom::create(['student_id' => $otherStudent->id]);
        ChatMessage::create([
            'room_id' => $otherRoom->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => '',
            'attachment_path' => 'chat_attachments/x.pdf', 'attachment_name' => 'x.pdf', 'attachment_size' => 9999,
        ]);

        $res = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/chat/attachments?classroom_id=' . $classroom->id);

        $res->assertStatus(200);
        $atts = collect($res->json('data.attachments'));
        $this->assertCount(3, $atts);
        $this->assertEqualsCanonicalizing(['guardian', 'student', 'staff'], $atts->pluck('source')->all());
        $this->assertSame(6000, $res->json('data.summary.used_bytes'));
        // サイズ降順
        $this->assertSame([3000, 2000, 1000], $atts->pluck('size')->all());
    }

    public function test_bulk_delete_frees_space_and_keeps_message_text(): void
    {
        [$staff, $classroom, $student] = $this->setupContext();
        $msgs = $this->seedAttachments($staff, $classroom, $student);

        $res = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/chat/attachments/delete', [
                'classroom_id' => $classroom->id,
                'items' => [
                    ['source' => 'guardian', 'id' => $msgs['guardian']->id], // 本文空
                    ['source' => 'student', 'id' => $msgs['student']->id],   // 本文あり
                ],
            ]);

        $res->assertStatus(200);
        $this->assertSame(2, $res->json('data.deleted_count'));
        $this->assertSame(3000, $res->json('data.freed_bytes'));
        $this->assertSame(3000, $res->json('data.summary.used_bytes')); // staff 分のみ残る

        // 物理ファイル削除
        Storage::disk('public')->assertMissing('chat_attachments/g1.pdf');
        Storage::disk('public')->assertMissing('chat_attachments/s1.pdf');
        Storage::disk('public')->assertExists('chat_attachments/st1.pdf');

        // 添付情報 null 化 + 本文の扱い
        $g = $msgs['guardian']->fresh();
        $this->assertNull($g->attachment_path);
        $this->assertNull($g->attachment_size);
        $this->assertSame('（添付ファイルは削除されました）', $g->message); // 空→プレースホルダ

        $s = $msgs['student']->fresh();
        $this->assertNull($s->attachment_path);
        $this->assertSame('メモあり', $s->message); // 本文は保持
    }

    public function test_shared_photo_file_is_not_physically_deleted(): void
    {
        [$staff, $classroom, $student] = $this->setupContext();

        // 写真ライブラリと共有する実体 (chat_attachments/ 配下でない)
        Storage::disk('public')->put('photos/shared.jpg', 'IMG');
        ClassroomPhoto::create([
            'classroom_id' => $classroom->id, 'uploader_id' => $staff->id,
            'file_path' => 'photos/shared.jpg', 'file_size' => 500, 'mime' => 'image/jpeg',
        ]);
        $room = ChatRoom::create(['student_id' => $student->id, 'guardian_id' => $student->guardian_id]);
        $msg = ChatMessage::create([
            'room_id' => $room->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => '写真共有',
            'attachment_path' => 'photos/shared.jpg', 'attachment_name' => 'shared.jpg',
            'attachment_size' => 500, 'attachment_mime' => 'image/jpeg',
        ]);

        $res = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/chat/attachments/delete', [
                'classroom_id' => $classroom->id,
                'items' => [['source' => 'guardian', 'id' => $msg->id]],
            ]);

        $res->assertStatus(200);
        // 共有実体は物理削除しない
        Storage::disk('public')->assertExists('photos/shared.jpg');
        // ただしチャット側の参照は外れ容量は解放される
        $this->assertNull($msg->fresh()->attachment_path);
    }

    public function test_access_denied_for_other_classroom(): void
    {
        [$staff, $classroom] = $this->setupContext();
        $other = Classroom::create(['classroom_name' => '権限外', 'company_id' => $classroom->company_id, 'is_active' => true]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/chat/attachments?classroom_id=' . $other->id)
            ->assertStatus(403);
    }
}
