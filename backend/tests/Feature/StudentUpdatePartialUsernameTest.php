<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 生徒編集の保存不具合の回帰テスト。
 *
 * 事象: 生徒管理画面の編集モーダルでブラウザが操作者の認証情報を username 欄に自動入力し、
 * 利用日チェックだけ変えた保存で操作者IDが混入 → unique 違反(422)で保存全体が失敗した。
 * 対策(フロント): username は変更時のみ送る。よってバックは「username 未送信の部分更新」で
 * 既存 username を維持し、他項目だけ保存できる必要がある。
 *
 * 差分カテゴリ: logic
 */
class StudentUpdatePartialUsernameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    /** @return array{0: Classroom, 1: User} */
    private function context(): array
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => 'r', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $room->id, 'is_active' => true,
        ]);

        return [$room, $staff];
    }

    public function test_update_without_username_keeps_username_and_saves_other_fields(): void
    {
        [$room, $staff] = $this->context();
        $s = Student::create([
            'student_name' => 'テスト太郎', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true,
            'username' => 'foo', 'scheduled_monday' => false,
        ]);

        // username を送らず利用日だけ更新(フロント修正後の挙動)
        $res = $this->actingAs($staff, 'sanctum')->putJson("/api/staff/students/{$s->id}", [
            'scheduled_monday' => true,
        ]);

        $res->assertStatus(200);
        $s->refresh();
        $this->assertSame('foo', $s->username);          // username は維持(消えない)
        $this->assertTrue((bool) $s->scheduled_monday);   // 他項目は保存される
    }

    public function test_update_with_another_students_username_is_rejected(): void
    {
        [$room, $staff] = $this->context();
        Student::create(['student_name' => 'B', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true, 'username' => 'bar']);
        $a = Student::create(['student_name' => 'A', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true, 'username' => 'foo']);

        // 他生徒の username を割り当てようとすると 422(重複は正しく拒否)
        $res = $this->actingAs($staff, 'sanctum')->putJson("/api/staff/students/{$a->id}", [
            'username' => 'bar',
        ]);

        $res->assertStatus(422);
        $this->assertSame('foo', $a->fresh()->username); // 失敗時は元のまま
    }
}
