<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 要望: 生徒一覧を「あいうえお順」「学年順」で並び替え、保護者一覧を在籍生徒の有無で
 * 絞り込めるようにする。
 *
 * 差分カテゴリ: screen (一覧の並び替え/絞り込み)
 *
 * - /api/admin/students は sort=kana(既定=ふりがな) / grade(学年) で並ぶ。
 * - /api/staff/guardians の students に status が含まれ、FE で在籍判定できる。
 */
class StudentGuardianSortFilterTest extends TestCase
{
    use RefreshDatabase;

    private function master(Classroom $c): User
    {
        return User::create([
            'username'  => 'master_sort_' . uniqid(),
            'password'  => Hash::make('p'),
            'full_name' => 'マスター',
            'user_type' => 'admin',
            'is_master' => true,
            'classroom_id' => $c->id,
            'is_active' => true,
        ]);
    }

    private function student(Classroom $c, string $name, string $kana, string $grade, string $status = 'active'): Student
    {
        return Student::create([
            'classroom_id'      => $c->id,
            'student_name'      => $name,
            'student_name_kana' => $kana,
            'grade_level'       => $grade,
            'status'            => $status,
            'is_active'         => $status !== 'withdrawn',
        ]);
    }

    public function test_admin_students_sort_by_kana_default(): void
    {
        $c = Classroom::create(['classroom_name' => 'ソート教室', 'is_active' => true]);
        $admin = $this->master($c);
        $this->student($c, '上田', 'うえだ', 'high_school_2');
        $this->student($c, '青木', 'あおき', 'junior_high_1');
        $this->student($c, '伊藤', 'いとう', 'elementary_1');

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/students');
        $res->assertStatus(200);
        $kana = collect($res->json('data.data'))->pluck('student_name_kana')->all();
        $this->assertSame(['あおき', 'いとう', 'うえだ'], $kana);
    }

    public function test_admin_students_sort_by_grade(): void
    {
        $c = Classroom::create(['classroom_name' => 'ソート教室2', 'is_active' => true]);
        $admin = $this->master($c);
        $this->student($c, '上田', 'うえだ', 'high_school_2');   // 高2 = 11
        $this->student($c, '青木', 'あおき', 'junior_high_1');   // 中1 = 7
        $this->student($c, '伊藤', 'いとう', 'elementary_1');    // 小1 = 1

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/students?sort=grade&dir=asc');
        $res->assertStatus(200);
        $grades = collect($res->json('data.data'))->pluck('grade_level')->all();
        $this->assertSame(['elementary_1', 'junior_high_1', 'high_school_2'], $grades);
    }

    public function test_staff_guardians_index_includes_student_status(): void
    {
        $c = Classroom::create(['classroom_name' => '保護者教室', 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_g_' . uniqid(), 'password' => Hash::make('p'),
            'full_name' => 'スタッフ', 'user_type' => 'staff', 'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'guardian_g_' . uniqid(), 'password' => Hash::make('p'),
            'full_name' => '保護者太郎', 'full_name_kana' => 'ほごしゃたろう',
            'user_type' => 'guardian', 'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $s = $this->student($c, '退所児', 'たいしょじ', 'elementary_1', 'withdrawn');
        $s->guardian_id = $guardian->id;
        $s->save();

        $res = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/guardians');
        $res->assertStatus(200);
        $g = collect($res->json('data'))->firstWhere('id', $guardian->id);
        $this->assertNotNull($g);
        $this->assertSame('withdrawn', $g['students'][0]['status']);
    }
}
