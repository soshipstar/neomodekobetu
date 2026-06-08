<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * B131: チャット添付ファイルに教室別 200MB 容量上限を導入
 *
 * 差分カテゴリ: api
 * 背景: 写真ライブラリには既に 100MB の上限があったが、チャット添付
 *       (chat_messages / student_chat_messages / staff_chat_messages) には
 *       上限がなく、無圧縮のままディスクを食い続ける問題があった。
 *       3 テーブルを合算して 200MB / 教室 で上限を切り、超過時は 422 を返す。
 *       また写真ライブラリと同じ要領で GET /chat/storage-usage を新設する。
 */
class B131_ChatAttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $otherClassroom = Classroom::create(['classroom_name' => '教室B', 'is_active' => true]);

        $staff = User::create([
            'username' => 'staff_b131_' . uniqid(),
            'password' => bcrypt('p'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $guardian = User::create([
            'username' => 'guardian_b131_' . uniqid(),
            'password' => bcrypt('p'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'guardian_id' => $guardian->id,
            'student_name' => 'テスト生徒',
            'is_active' => true,
            'status' => 'active',
        ]);

        return compact('classroom', 'otherClassroom', 'staff', 'guardian', 'student');
    }

    /**
     * 3 テーブル合算: chat_messages / student_chat_messages / staff_chat_messages
     */
    public function test_classroom_used_sums_across_three_tables(): void
    {
        ['classroom' => $classroom, 'student' => $student, 'staff' => $staff, 'guardian' => $guardian] = $this->fixture();

        // chat_rooms / chat_messages
        $chatRoomId = DB::table('chat_rooms')->insertGetId([
            'student_id'  => $student->id,
            'guardian_id' => $guardian->id,
            'created_at'  => now(),
        ]);
        DB::table('chat_messages')->insert([
            'room_id'         => $chatRoomId,
            'sender_id'       => $guardian->id,
            'sender_type'     => 'guardian',
            'message_type'    => 'text',
            'message'         => 'a',
            'attachment_path' => 'chat_attachments/b131_g.bin',
            'attachment_size' => 1_000_000, // 1MB
            'created_at'      => now(),
        ]);

        // student_chat_rooms / student_chat_messages
        $studentRoomId = DB::table('student_chat_rooms')->insertGetId([
            'student_id' => $student->id,
            'created_at' => now(),
        ]);
        DB::table('student_chat_messages')->insert([
            'room_id'         => $studentRoomId,
            'sender_type'     => 'student',
            'sender_id'       => $student->id,
            'message_type'    => 'text',
            'message'         => 'b',
            'attachment_path' => 'student_chat_attachments/b131_s.bin',
            'attachment_size' => 2_000_000, // 2MB
            'created_at'      => now(),
        ]);

        // staff_chat_rooms / staff_chat_messages
        $expected = 3_000_000;
        if (DB::getSchemaBuilder()->hasTable('staff_chat_rooms')) {
            $staffRoomId = DB::table('staff_chat_rooms')->insertGetId([
                'classroom_id' => $classroom->id,
                'room_type'    => 'group',
                'created_by'   => $staff->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            DB::table('staff_chat_messages')->insert([
                'room_id'         => $staffRoomId,
                'sender_id'       => $staff->id,
                'message'         => 'c',
                'attachment_path' => 'chat_attachments/b131_st.bin',
                'attachment_size' => 3_000_000, // 3MB
                'created_at'      => now(),
            ]);
            $expected = 6_000_000;
        }

        $service = new ChatAttachmentStorage();
        $this->assertSame($expected, $service->classroomUsed($classroom->id));
    }

    /**
     * 他教室の添付サイズは集計に含まれない
     */
    public function test_classroom_used_isolates_other_classrooms(): void
    {
        ['classroom' => $classroom, 'otherClassroom' => $otherClassroom] = $this->fixture();

        // 別教室の生徒・保護者
        $otherGuardian = User::create([
            'username'     => 'g_other_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => '別保護者',
            'user_type'    => 'guardian',
            'classroom_id' => $otherClassroom->id,
            'is_active'    => true,
        ]);
        $otherStudent = Student::create([
            'classroom_id' => $otherClassroom->id,
            'guardian_id'  => $otherGuardian->id,
            'student_name' => '別生徒',
            'is_active'    => true,
            'status'       => 'active',
        ]);
        $otherRoomId = DB::table('chat_rooms')->insertGetId([
            'student_id'  => $otherStudent->id,
            'guardian_id' => $otherGuardian->id,
            'created_at'  => now(),
        ]);
        DB::table('chat_messages')->insert([
            'room_id'         => $otherRoomId,
            'sender_id'       => $otherGuardian->id,
            'sender_type'     => 'guardian',
            'message_type'    => 'text',
            'message'         => 'x',
            'attachment_path' => 'chat_attachments/b131_other.bin',
            'attachment_size' => 100_000_000, // 100MB
            'created_at'      => now(),
        ]);

        $service = new ChatAttachmentStorage();
        // 教室A は他教室の 100MB を含まない
        $this->assertSame(0, $service->classroomUsed($classroom->id));
        $this->assertSame(100_000_000, $service->classroomUsed($otherClassroom->id));
    }

    /**
     * 上限ちょうど・超過の境界
     */
    public function test_can_upload_boundary(): void
    {
        ['classroom' => $classroom, 'student' => $student] = $this->fixture();

        $service = new ChatAttachmentStorage();
        // 200MB - 1MB = 199MB の使用にしておく
        $roomId = DB::table('student_chat_rooms')->insertGetId([
            'student_id' => $student->id,
            'created_at' => now(),
        ]);
        DB::table('student_chat_messages')->insert([
            'room_id'         => $roomId,
            'sender_type'     => 'student',
            'sender_id'       => $student->id,
            'message_type'    => 'text',
            'message'         => 'a',
            'attachment_path' => 'student_chat_attachments/b131_boundary.bin',
            'attachment_size' => ChatAttachmentStorage::STORAGE_LIMIT_BYTES - 1_000_000,
            'created_at'      => now(),
        ]);

        // ちょうど 1MB なら可
        $this->assertTrue($service->canUpload($classroom->id, 1_000_000));
        // 1MB + 1B はオーバー
        $this->assertFalse($service->canUpload($classroom->id, 1_000_001));
    }

    /**
     * 容量超過時にAPIが 422 を返す (Student chat)
     */
    public function test_student_chat_returns_422_when_over_quota(): void
    {
        Storage::fake('public');
        ['classroom' => $classroom, 'student' => $student] = $this->fixture();

        // 生徒の User アカウントを作成 (チャットで使用)
        $studentUser = User::create([
            'username'     => 'student_' . $student->id,
            'password'     => bcrypt('p'),
            'full_name'    => $student->student_name,
            'user_type'    => 'student',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        // 教室Aを 200MB ちょうどまで埋める
        $roomId = DB::table('student_chat_rooms')->insertGetId([
            'student_id' => $student->id,
            'created_at' => now(),
        ]);
        DB::table('student_chat_messages')->insert([
            'room_id'         => $roomId,
            'sender_type'     => 'student',
            'sender_id'       => $student->id,
            'message_type'    => 'text',
            'message'         => 'fill',
            'attachment_path' => 'student_chat_attachments/b131_fill.bin',
            'attachment_size' => ChatAttachmentStorage::STORAGE_LIMIT_BYTES,
            'created_at'      => now(),
        ]);

        $file = UploadedFile::fake()->image('over.jpg', 100, 100)->size(1024); // 1MB
        $response = $this->actingAs($studentUser, 'sanctum')
            ->post('/api/student/chat/messages', [
                'message'    => 'over quota test',
                'attachment' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    /**
     * 容量上限内なら API は通常通り 201 (Guardian chat)
     */
    public function test_guardian_chat_allows_under_quota(): void
    {
        Storage::fake('public');
        ['student' => $student, 'guardian' => $guardian] = $this->fixture();

        // chat_room を作って guardian が送信できるようにする
        $roomId = DB::table('chat_rooms')->insertGetId([
            'student_id'  => $student->id,
            'guardian_id' => $guardian->id,
            'created_at'  => now(),
        ]);

        $file = UploadedFile::fake()->image('ok.jpg', 100, 100)->size(100); // 100KB
        $response = $this->actingAs($guardian, 'sanctum')
            ->post("/api/guardian/chat/rooms/{$roomId}/messages", [
                'message'    => 'hi',
                'attachment' => $file,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    /**
     * GET /api/student/chat/storage-usage が summary を返す
     */
    public function test_storage_usage_endpoint_returns_summary(): void
    {
        ['classroom' => $classroom, 'student' => $student] = $this->fixture();
        $studentUser = User::create([
            'username'     => 'student_' . $student->id,
            'password'     => bcrypt('p'),
            'full_name'    => $student->student_name,
            'user_type'    => 'student',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $roomId = DB::table('student_chat_rooms')->insertGetId([
            'student_id' => $student->id,
            'created_at' => now(),
        ]);
        DB::table('student_chat_messages')->insert([
            'room_id'         => $roomId,
            'sender_type'     => 'student',
            'sender_id'       => $student->id,
            'message_type'    => 'text',
            'message'         => 'a',
            'attachment_path' => 'student_chat_attachments/b131_usage.bin',
            'attachment_size' => 50_000_000, // 50MB
            'created_at'      => now(),
        ]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/student/chat/storage-usage');

        $response->assertStatus(200);
        $response->assertJsonPath('data.classroom_id', $classroom->id);
        $response->assertJsonPath('data.used_bytes', 50_000_000);
        $response->assertJsonPath('data.limit_bytes', ChatAttachmentStorage::STORAGE_LIMIT_BYTES);
        $response->assertJsonPath('data.is_full', false);
    }

    /**
     * Staff の storage-usage エンドポイントは権限外教室を拒否する
     */
    public function test_staff_storage_usage_rejects_unauthorized_classroom(): void
    {
        ['staff' => $staff, 'otherClassroom' => $otherClassroom] = $this->fixture();

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/chat/storage-usage?classroom_id=' . $otherClassroom->id);

        $response->assertStatus(403);
    }
}
