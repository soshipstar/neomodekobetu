<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-012: チャット添付の容量集計を attachment_path 単位で重複排除する。
 *
 * 背景: 一斉送信(broadcast)は 1 つの物理ファイルを複数ルームのメッセージにリンクする。
 *       メッセージ行ごとに attachment_size を合算するとリンク数 N 倍に過大計上され、
 *       実ディスク使用量と大きく乖離して 200MB 上限に達しやすくなっていた。
 *       attachment_path 単位で重複排除して実使用量に一致させる。
 *
 * 差分カテゴリ: logic
 */
class L012_ChatAttachmentStorageDedupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_broadcast_shared_file_counted_once(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_dedup_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'スタッフA', 'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);

        // 一斉送信を模す: 3 ルームの 3 メッセージが同一 attachment_path を共有 (物理 1 ファイル)
        for ($i = 0; $i < 3; $i++) {
            $student = Student::create(['student_name' => "生徒{$i}", 'classroom_id' => $classroom->id]);
            $room = ChatRoom::create(['student_id' => $student->id]);
            ChatMessage::create([
                'room_id' => $room->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => '一斉送信',
                'message_type' => 'broadcast',
                'attachment_path' => 'chat_attachments/shared.pdf', 'attachment_name' => 'shared.pdf',
                'attachment_size' => 1000, 'attachment_mime' => 'application/pdf',
            ]);
        }

        // 別の通常添付 (ユニークなファイル)
        $s2 = Student::create(['student_name' => '個別生徒', 'classroom_id' => $classroom->id]);
        $room2 = ChatRoom::create(['student_id' => $s2->id]);
        ChatMessage::create([
            'room_id' => $room2->id, 'sender_id' => $staff->id, 'sender_type' => 'staff', 'message' => '個別',
            'attachment_path' => 'chat_attachments/unique.png', 'attachment_name' => 'unique.png',
            'attachment_size' => 500, 'attachment_mime' => 'image/png',
        ]);

        $used = app(ChatAttachmentStorage::class)->classroomUsed($classroom->id);

        // 共有ファイルは 1 回だけ (1000) + ユニーク (500) = 1500。
        // 重複排除しないと 3*1000 + 500 = 3500 になる。
        $this->assertSame(1500, $used);

        $summary = app(ChatAttachmentStorage::class)->summary($classroom->id);
        $this->assertSame(1500, $summary['used_bytes']);
    }
}
