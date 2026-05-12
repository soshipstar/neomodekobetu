<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L009: 生徒チャットの添付ファイル保存 (path / size / original_name)
 *
 * 差分カテゴリ: logic
 * 背景:
 *   - student_chat_messages テーブルの添付ファイル名カラムが `original_name` で
 *     作成されていたが、staff_chat_messages / submission_requests / フロントエンド
 *     はすべて `attachment_original_name` を使用しており、student 側だけ外れ値だった。
 *   - Student\ChatController::sendMessage は `attachment_original_name` キーで
 *     mass assign しており、カラム名不一致で SQL エラーになるはず...だが、
 *     さらに StudentChatMessage モデルの $fillable に attachment 系カラムが
 *     列挙されていなかったため Eloquent が silently drop してエラーにすらならず、
 *     添付ファイルはディスクに保存されつつ DB レコードには path/size/name が
 *     一切記録されない状態だった。結果、画面には添付なしのバブルが表示される。
 *
 *   修正:
 *     1. マイグレーションで `original_name` を `attachment_original_name` にリネーム
 *     2. StudentChatMessage の $fillable に attachment 系カラムを追加
 */
class L009_StudentChatAttachmentSaveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{classroom: Classroom, student: Student, studentUser: User}
     */
    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '教室A',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'テスト生徒',
            'is_active'    => true,
            'status'       => 'active',
        ]);
        $studentUser = User::create([
            'username'     => 'student_' . $student->id,
            'password'     => bcrypt('p'),
            'full_name'    => $student->student_name,
            'user_type'    => 'student',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        return compact('classroom', 'student', 'studentUser');
    }

    /**
     * 添付付きで POST すると DB に path / size / original_name が保存される。
     */
    public function test_attachment_is_persisted_to_db(): void
    {
        Storage::fake('public');
        $f = $this->fixture();

        $file = UploadedFile::fake()->image('hello.jpg', 100, 100)->size(200); // 200KB

        $response = $this->actingAs($f['studentUser'], 'sanctum')
            ->post('/api/student/chat/messages', [
                'message'    => 'これ見て',
                'attachment' => $file,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $msg = StudentChatMessage::first();
        $this->assertNotNull($msg, 'StudentChatMessage row should be created');
        $this->assertNotNull($msg->attachment_path, 'attachment_path must not be silently dropped');
        $this->assertSame('hello.jpg', $msg->attachment_original_name);
        $this->assertSame(200 * 1024, (int) $msg->attachment_size);

        // ディスクにも保存されていること
        Storage::disk('public')->assertExists($msg->attachment_path);
    }

    /**
     * 添付なしのテキストメッセージは attachment 系が null のまま保存される (退行防止)。
     */
    public function test_text_only_message_has_null_attachment(): void
    {
        Storage::fake('public');
        $f = $this->fixture();

        $response = $this->actingAs($f['studentUser'], 'sanctum')
            ->postJson('/api/student/chat/messages', [
                'message' => 'テキストだけ',
            ]);

        $response->assertStatus(201);

        $msg = StudentChatMessage::first();
        $this->assertSame('テキストだけ', $msg->message);
        $this->assertNull($msg->attachment_path);
        $this->assertNull($msg->attachment_original_name);
        $this->assertNull($msg->attachment_size);
    }

    /**
     * レスポンス (JSON) と messages 取得 API でも `attachment_original_name` キーで
     * 返ること (フロントエンドが参照しているキー名)。
     */
    public function test_messages_endpoint_returns_attachment_original_name_key(): void
    {
        Storage::fake('public');
        $f = $this->fixture();

        $file = UploadedFile::fake()->image('photo.png', 50, 50)->size(50);
        $this->actingAs($f['studentUser'], 'sanctum')
            ->post('/api/student/chat/messages', [
                'message'    => '',
                'attachment' => $file,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attachment_original_name', 'photo.png');

        $list = $this->actingAs($f['studentUser'], 'sanctum')
            ->getJson('/api/student/chat/messages');

        $list->assertStatus(200);
        $list->assertJsonPath('data.messages.0.attachment_original_name', 'photo.png');
        $list->assertJsonPath('data.messages.0.attachment_size', 50 * 1024);
    }

    /**
     * StudentChatMessage モデルの $fillable に attachment 系カラムが含まれること。
     * (Eloquent の silently drop を防ぐ静的チェック)
     */
    public function test_fillable_contains_attachment_columns(): void
    {
        $fillable = (new StudentChatMessage())->getFillable();

        $this->assertContains('attachment_path', $fillable);
        $this->assertContains('attachment_original_name', $fillable);
        $this->assertContains('attachment_size', $fillable);
    }
}
