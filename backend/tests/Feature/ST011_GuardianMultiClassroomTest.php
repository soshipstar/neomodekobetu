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
 * 背景: 1 名の保護者が複数の子どもを持つ場合、および 1 名の子どもが複数教室に
 *       在籍する場合に、保護者のアクセス範囲を子どもたちの在籍教室の和集合と
 *       して扱う。User::accessibleClassroomIds() を user_type=guardian に
 *       対応させる。
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

        // 子1: 主教室 c1 のみ
        $child1 = Student::create([
            'classroom_id' => $c1->id,
            'student_name' => '子1',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $child1->classrooms()->syncWithoutDetaching([$c1->id]);

        // 子2: 主教室 c2 + pivot で c3 にも在籍
        $child2 = Student::create([
            'classroom_id' => $c2->id,
            'student_name' => '子2',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $child2->classrooms()->syncWithoutDetaching([$c2->id, $c3->id]);

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

        $student = Student::create([
            'classroom_id' => $c1->id,
            'student_name' => '子',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $student->classrooms()->syncWithoutDetaching([$c1->id, $c2->id]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->sort()->values()->all();
        $expected = [$c1->id, $c2->id];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }
}
