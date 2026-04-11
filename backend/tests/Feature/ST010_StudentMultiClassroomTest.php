<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ST010: 児童の複数教室対応
 *
 * 差分カテゴリ: logic / api
 * 背景: 旧アプリでは児童は単一教室だったが、新アプリでは 1 名の児童が
 *       複数教室に在籍するケース（きょうだい配慮や支援内容の棲み分け）に
 *       対応する。classroom_student ピボットを導入し、
 *       StudentClassroomController::sync で管理する。
 */
class ST010_StudentMultiClassroomTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::create([
            'username' => 'master_st010',
            'password' => bcrypt('pass'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'A2', 'company_id' => $company->id, 'is_active' => true]);
        $c3 = Classroom::create(['classroom_name' => 'A3', 'company_id' => $company->id, 'is_active' => true]);

        $student = Student::create([
            'classroom_id' => $c1->id,
            'student_name' => 'テスト太郎',
            'status' => 'active',
            'is_active' => true,
        ]);
        // 主教室を pivot にも登録
        $student->classrooms()->syncWithoutDetaching([$c1->id]);

        return compact('company', 'c1', 'c2', 'c3', 'student');
    }

    public function test_student_accessible_classroom_ids_includes_primary(): void
    {
        $f = $this->fixture();
        $ids = $f['student']->accessibleClassroomIds();
        $this->assertEquals([$f['c1']->id], $ids);
    }

    public function test_student_can_be_assigned_to_multiple_classrooms(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/students/{$f['student']->id}/classrooms", [
                'classroom_ids' => [$f['c1']->id, $f['c2']->id, $f['c3']->id],
            ]);

        $response->assertStatus(200);
        $expected = [$f['c1']->id, $f['c2']->id, $f['c3']->id];
        sort($expected);
        $actual = $f['student']->classrooms()->pluck('classrooms.id')->sort()->values()->all();
        $this->assertEquals($expected, $actual);
    }

    public function test_sync_rejects_classroom_from_another_company(): void
    {
        $f = $this->fixture();
        $companyB = Company::create(['name' => '企業B']);
        $otherClassroom = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/students/{$f['student']->id}/classrooms", [
                'classroom_ids' => [$f['c1']->id, $otherClassroom->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_ids');
    }

    public function test_sync_rejects_dropping_primary_classroom(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->putJson("/api/admin/students/{$f['student']->id}/classrooms", [
                'classroom_ids' => [$f['c2']->id, $f['c3']->id], // c1 (主教室) を外している
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_ids');
    }

    public function test_store_auto_inserts_primary_into_pivot(): void
    {
        $master = $this->master();
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/students', [
                'classroom_id' => $classroom->id,
                'student_name' => 'テスト次郎',
                'username' => 'test_jiro',
                'password' => 'pass1234',
                'status' => 'active',
            ]);

        $response->assertStatus(201);
        $studentId = $response->json('data.id');
        $student = Student::find($studentId);
        $this->assertEquals(
            [$classroom->id],
            $student->classrooms()->pluck('classrooms.id')->all()
        );
    }

    public function test_index_endpoint_returns_company_id(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson("/api/admin/students/{$f['student']->id}/classrooms");

        $response->assertStatus(200);
        $response->assertJsonPath('data.company_id', $f['company']->id);
        $response->assertJsonPath('data.primary_classroom_id', $f['c1']->id);
    }
}
