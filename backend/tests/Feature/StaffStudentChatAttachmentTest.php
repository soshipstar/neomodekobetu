<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\StudentChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 生徒チャットでスタッフが送る画像添付が保存・表示される回帰テスト。
 *
 * 差分カテゴリ: api
 * 背景: StaffStudentChatController::sendMessage が message しか保存せず、
 *       添付ファイルを無視していたため、画像を添付してもファイル名テキストだけ
 *       送られていた。attachment を受付・保存し、一覧で attachment_url を返す。
 */
class StaffStudentChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{classroom: Classroom, student: Student, staff: User, room: StudentChatRoom} */
    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'テスト生徒',
            'is_active'    => true,
            'status'       => 'active',
        ]);
        $staff = User::create([
            'username'     => 'staff_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフ',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $room = StudentChatRoom::create([
            'student_id'   => $student->id,
            'classroom_id' => $classroom->id,
        ]);

        return compact('classroom', 'student', 'staff', 'room');
    }

    public function test_staff_image_attachment_is_persisted(): void
    {
        Storage::fake('public');
        $f = $this->fixture();

        $file = UploadedFile::fake()->image('shot.png', 100, 100)->size(346); // 346KB (報告のサイズ)

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->post("/api/staff/student-chats/{$f['room']->id}/messages", [
                'attachment' => $file, // 本文なし・添付のみ
            ]);

        $response->assertStatus(201);

        $msg = StudentChatMessage::first();
        $this->assertNotNull($msg);
        $this->assertNotNull($msg->attachment_path, '添付が保存されていない (無視されている)');
        $this->assertSame('shot.png', $msg->attachment_original_name);
        // 本文にファイル名が混入していないこと
        $this->assertSame('', (string) $msg->message);
        Storage::disk('public')->assertExists($msg->attachment_path);
    }

    public function test_messages_endpoint_returns_attachment_url(): void
    {
        Storage::fake('public');
        $f = $this->fixture();

        $file = UploadedFile::fake()->image('p.jpg', 50, 50)->size(50);
        $this->actingAs($f['staff'], 'sanctum')
            ->post("/api/staff/student-chats/{$f['room']->id}/messages", ['attachment' => $file])
            ->assertStatus(201);

        $list = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/student-chats/{$f['room']->id}/messages");

        $list->assertStatus(200);
        $list->assertJsonPath('data.0.attachment_original_name', 'p.jpg');
        $url = $list->json('data.0.attachment_url');
        $this->assertNotNull($url, 'attachment_url がレスポンスに含まれていない');
    }

    public function test_text_only_still_works(): void
    {
        Storage::fake('public');
        $f = $this->fixture();

        $this->actingAs($f['staff'], 'sanctum')
            ->postJson("/api/staff/student-chats/{$f['room']->id}/messages", ['message' => 'こんにちは'])
            ->assertStatus(201);

        $msg = StudentChatMessage::first();
        $this->assertSame('こんにちは', $msg->message);
        $this->assertNull($msg->attachment_path);
    }
}
