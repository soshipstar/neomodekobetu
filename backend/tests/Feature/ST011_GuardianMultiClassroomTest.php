<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ST011: 保護者のアクセス可能教室を子どもの在籍教室から導出する
 *
 * 差分カテゴリ: logic
 * 背景: 1 名の保護者が複数の子どもを持ち、子どもたちが別々の教室に在籍する
 *       ケースで、保護者のアクセス範囲を子どもたちの在籍教室の和集合として
 *       扱う。User::accessibleClassroomIds() を user_type=guardian に対応させる。
 *
 * 仕様: 1 児童 = 1 Student レコード = 1 教室。同じ物理的な子どもが複数教室に
 *       在籍する場合は guardian_id で紐づく別 Student レコードを作成する。
 */
class ST011_GuardianMultiClassroomTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_accessible_classrooms_union_from_children(): void
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'A2', 'company_id' => $company->id, 'is_active' => true]);
        $c3 = Classroom::create(['classroom_name' => 'A3', 'company_id' => $company->id, 'is_active' => true]);

        $guardian = User::create([
            'username' => 'guardian_st011',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);

        // 子1: 教室 A1
        Student::create([
            'classroom_id' => $c1->id,
            'student_name' => '子1',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        // 子2: 教室 A2（別の子ども）
        Student::create([
            'classroom_id' => $c2->id,
            'student_name' => '子2',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        // 子3: 同じ物理的な子どもが別の教室にも在籍する場合の表現
        // → guardian_id で同じ保護者に紐づく別 Student レコード
        Student::create([
            'classroom_id' => $c3->id,
            'student_name' => '子1(A3)',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $ids = $guardian->accessibleClassroomIds();
        sort($ids);
        $expected = [$c1->id, $c2->id, $c3->id];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }

    public function test_guardian_without_children_gets_empty_classrooms(): void
    {
        $guardian = User::create([
            'username' => 'lone_guardian_st011',
            'password' => bcrypt('pass'),
            'full_name' => '子なし保護者',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);

        $this->assertEquals([], $guardian->accessibleClassroomIds());
    }

    public function test_my_classrooms_endpoint_returns_children_classrooms_for_guardian(): void
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'A2', 'company_id' => $company->id, 'is_active' => true]);

        $guardian = User::create([
            'username' => 'guardian_me_st011',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);

        // 同じ物理的な子どもを 2 教室それぞれに Student レコードとして登録
        Student::create([
            'classroom_id' => $c1->id,
            'student_name' => '子',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        Student::create([
            'classroom_id' => $c2->id,
            'student_name' => '子',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->sort()->values()->all();
        $expected = [$c1->id, $c2->id];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }
}
