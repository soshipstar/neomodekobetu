<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * R6 / B133: 別教室への複製機能で「同一企業内に複製可能な教室がありません」と
 * 出る不具合の修正
 *
 * 差分カテゴリ: logic
 *
 * 報告内容: 生徒情報画面の「別教室へ複製」モーダルで、同一企業内の別教室が
 * 一覧に出ず、複製できない。
 *
 * 原因: /api/admin/classrooms が「通常管理者」には自身の所属教室 (1個) しか
 * 返さない権限フィルタを持っており、同企業の他教室を取得できなかった。
 *
 * 修正:
 * - GET /api/admin/students/{student}/copy-targets を新設。複製専用に source の
 *   company_id と一致する全教室 (source を除く) を返す。
 * - copyToClassroom() の権限判定を switchableClassroomIds() から「自教室の
 *   company_id == source 教室の company_id」に変更。
 */
class B133_StudentCopyTargetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 同企業 2 教室 + 通常管理者 (is_master=false, is_company_admin=false) のシナリオ。
     * このとき従来は switchableClassroomIds() が自教室1個のみ返すため、
     * 「同企業の別教室への複製」ができなかった。
     *
     * @return array{company:Company, c1:Classroom, c2:Classroom, admin:User, student:Student}
     */
    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => 'てらこやプラス', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'てらこやわくわく', 'company_id' => $company->id, 'is_active' => true]);

        // 通常管理者 (c2 所属、is_company_admin=false)
        $admin = User::create([
            'username' => 'admin_b133_' . uniqid(),
            'password' => bcrypt('p'),
            'full_name' => '淡田由貴',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $c2->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id'  => $c1->id,
            'student_name'  => '白砂 澪',
            'username'      => 'shirasuna_b133',
            'password_hash' => Hash::make('pass'),
            'birth_date'    => '2018-04-01',
            'grade_level'   => 'elementary_1',
            'status'        => 'active',
            'is_active'     => true,
        ]);

        return compact('company', 'c1', 'c2', 'admin', 'student');
    }

    /**
     * 同企業の通常管理者で、複製先候補に他教室が含まれること
     */
    public function test_copy_targets_returns_same_company_classrooms_for_regular_admin(): void
    {
        ['admin' => $admin, 'student' => $student, 'c2' => $c2] = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/students/{$student->id}/copy-targets");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        // source (c1) は除外、c2 は含まれる
        $this->assertContains($c2->id, $ids);
        $this->assertNotContains($student->classroom_id, $ids);
    }

    /**
     * 別企業の教室は除外される
     */
    public function test_copy_targets_excludes_other_company_classrooms(): void
    {
        ['admin' => $admin, 'student' => $student] = $this->fixture();

        // 別企業の教室
        $otherCompany = Company::create(['name' => '別企業']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '無関係教室',
            'company_id'     => $otherCompany->id,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/students/{$student->id}/copy-targets");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($otherClassroom->id, $ids);
    }

    /**
     * 別企業の管理者は 403
     */
    public function test_copy_targets_rejects_different_company_admin(): void
    {
        ['student' => $student] = $this->fixture();

        $otherCompany = Company::create(['name' => '別企業']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '別企業教室',
            'company_id'     => $otherCompany->id,
            'is_active'      => true,
        ]);
        $otherAdmin = User::create([
            'username'         => 'admin_other_' . uniqid(),
            'password'         => bcrypt('p'),
            'full_name'        => '別企業管理者',
            'user_type'        => 'admin',
            'is_master'        => false,
            'is_company_admin' => false,
            'classroom_id'     => $otherClassroom->id,
            'is_active'        => true,
        ]);

        $response = $this->actingAs($otherAdmin, 'sanctum')
            ->getJson("/api/admin/students/{$student->id}/copy-targets");

        $response->assertStatus(403);
    }

    /**
     * 同企業の通常管理者が実際に複製を実行できる (POST /copy-to-classroom)
     */
    public function test_regular_admin_can_copy_within_same_company(): void
    {
        ['admin' => $admin, 'student' => $student, 'c2' => $c2] = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/students/{$student->id}/copy-to-classroom", [
                'classroom_id' => $c2->id,
                'username'     => 'shirasuna_b133_c2',
                'password'     => 'pass1234',
            ]);

        $response->assertStatus(201);
        $copy = Student::find($response->json('data.id'));
        $this->assertSame($c2->id, $copy->classroom_id);
        $this->assertSame($student->student_name, $copy->student_name);
        // 元の児童は変更されない
        $this->assertSame($student->classroom_id, $student->fresh()->classroom_id);
    }

    /**
     * 別企業の管理者は複製実行も 403
     */
    public function test_other_company_admin_cannot_copy(): void
    {
        ['student' => $student, 'c2' => $c2] = $this->fixture();

        $otherCompany = Company::create(['name' => '別企業']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '別企業教室',
            'company_id'     => $otherCompany->id,
            'is_active'      => true,
        ]);
        $otherAdmin = User::create([
            'username'         => 'admin_other2_' . uniqid(),
            'password'         => bcrypt('p'),
            'full_name'        => '別企業管理者',
            'user_type'        => 'admin',
            'is_master'        => false,
            'is_company_admin' => false,
            'classroom_id'     => $otherClassroom->id,
            'is_active'        => true,
        ]);

        $response = $this->actingAs($otherAdmin, 'sanctum')
            ->postJson("/api/admin/students/{$student->id}/copy-to-classroom", [
                'classroom_id' => $c2->id,
                'username'     => 'cross_company_' . uniqid(),
            ]);

        $response->assertStatus(403);
    }
}
