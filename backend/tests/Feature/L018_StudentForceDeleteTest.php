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
 * L-018: 生徒の完全削除 (/admin/students)。退所(soft)とは別に、誤登録・重複・テストデータを
 * 関連データごと削除できる。FK の CASCADE/SET NULL で関連レコードが処理される。
 *
 * 差分カテゴリ: screen
 */
class L018_StudentForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_admin_can_permanently_delete_student_and_cascades(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $admin = User::create([
            'username' => 'admin_del_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '管理者',
            'user_type' => 'admin', 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_del_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者',
            'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '削除対象児', 'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id,
            'status' => 'active', 'is_active' => true,
        ]);
        $room = ChatRoom::create(['student_id' => $student->id, 'guardian_id' => $guardian->id]);

        $res = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/students/{$student->id}/permanent");
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);

        // 生徒レコードが消える
        $this->assertNull(Student::find($student->id));
        // 関連 chat_room も CASCADE で消える
        $this->assertNull(ChatRoom::find($room->id));
        // 保護者ユーザー自体は残る (生徒の従属ではない)
        $this->assertNotNull(User::find($guardian->id));
    }

    public function test_staff_cannot_permanently_delete_student(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_del_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒', 'classroom_id' => $classroom->id, 'status' => 'active', 'is_active' => true,
        ]);

        // /admin 配下は user_type:admin のみ。スタッフは到達不可。
        $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/admin/students/{$student->id}/permanent")
            ->assertStatus(403);
        $this->assertNotNull(Student::find($student->id));
    }
}
