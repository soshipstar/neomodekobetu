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
 * ST012: 児童を別教室に複製する API のテスト
 *
 * 差分カテゴリ: api
 * 背景: 1 児童 = 1 Student = 1 教室のモデルで、同じ物理的な子どもが
 *       複数教室に在籍する場合に、氏名・学年・保護者・スケジュールなどを
 *       引き継いだ新 Student を生成する複製エンドポイントを追加した。
 */
class ST012_StudentCopyToClassroomTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::create([
            'username' => 'master_st012',
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

        $guardian = User::create([
            'username' => 'guardian_st012',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);

        $source = Student::create([
            'classroom_id' => $c1->id,
            'student_name' => 'コピー太郎',
            'username' => 'copy_taro_a1',
            'password_hash' => Hash::make('origpass'),
            'birth_date' => '2018-04-01',
            'grade_level' => 'elementary_1',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
            'scheduled_monday' => true,
            'scheduled_tuesday' => true,
            'scheduled_wednesday' => false,
            'scheduled_thursday' => false,
            'scheduled_friday' => true,
            'notes' => '引き継ぎメモ',
        ]);

        return compact('company', 'c1', 'c2', 'guardian', 'source');
    }

    public function test_master_can_copy_student_to_another_classroom(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'copy_taro_a2',
                'password' => 'newpass123',
            ]);

        $response->assertStatus(201);
        $copyId = $response->json('data.id');
        $this->assertNotEquals($f['source']->id, $copyId);

        $copy = Student::find($copyId);
        // コピーされるべき項目
        $this->assertEquals($f['source']->student_name, $copy->student_name);
        $this->assertEquals($f['source']->birth_date?->format('Y-m-d'), $copy->birth_date?->format('Y-m-d'));
        $this->assertEquals($f['source']->grade_level, $copy->grade_level);
        $this->assertEquals($f['source']->guardian_id, $copy->guardian_id);
        $this->assertEquals($f['source']->notes, $copy->notes);
        $this->assertEquals($f['source']->scheduled_monday, $copy->scheduled_monday);
        $this->assertEquals($f['source']->scheduled_tuesday, $copy->scheduled_tuesday);
        $this->assertEquals($f['source']->scheduled_friday, $copy->scheduled_friday);

        // 差し替えられる項目
        $this->assertEquals($f['c2']->id, $copy->classroom_id);
        $this->assertEquals('copy_taro_a2', $copy->username);
        $this->assertEquals('active', $copy->status);
        $this->assertTrue((bool) $copy->is_active);

        // source は変更されていない
        $this->assertEquals($f['c1']->id, $f['source']->fresh()->classroom_id);
    }

    public function test_cannot_copy_to_same_classroom(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c1']->id,
                'username' => 'dup_a1',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_cannot_copy_to_classroom_in_another_company(): void
    {
        $f = $this->fixture();
        $companyB = Company::create(['name' => '企業B']);
        $otherClassroom = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $otherClassroom->id,
                'username' => 'cross_company',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_cannot_copy_with_duplicate_username(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        // 既存の username を指定
        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => $f['source']->username,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('username');
    }

    public function test_copy_clears_withdrawal_and_login_history(): void
    {
        $f = $this->fixture();
        // source に退所履歴と login 履歴を設定
        $f['source']->update([
            'withdrawal_date' => '2026-01-15',
            'withdrawal_reason' => '卒業',
            'last_login_at' => '2026-03-01 10:00:00',
        ]);

        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'fresh_user',
            ]);

        $response->assertStatus(201);
        $copy = Student::find($response->json('data.id'));
        $this->assertNull($copy->withdrawal_date);
        $this->assertNull($copy->withdrawal_reason);
        $this->assertNull($copy->last_login_at);
    }

    public function test_copy_can_be_retrieved_by_guardian_in_both_classrooms(): void
    {
        $f = $this->fixture();
        $master = $this->master();

        // 複製
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'copy_taro_a2',
            ])->assertStatus(201);

        // 保護者の /api/my-classrooms に両方の教室が現れる
        $response = $this->actingAs($f['guardian'], 'sanctum')
            ->getJson('/api/my-classrooms');

        $response->assertStatus(200);
        $ids = collect($response->json('data.classrooms'))->pluck('id')->sort()->values()->all();
        $expected = [$f['c1']->id, $f['c2']->id];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }

    public function test_non_master_admin_cannot_copy_to_inaccessible_classroom(): void
    {
        $f = $this->fixture();
        $c3 = Classroom::create(['classroom_name' => 'A3', 'company_id' => $f['company']->id, 'is_active' => true]);

        $normalAdmin = User::create([
            'username' => 'normal_st012',
            'password' => bcrypt('pass'),
            'full_name' => 'NormalAdmin',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $f['c1']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($normalAdmin, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $c3->id, // normalAdmin は c3 にアクセス権なし
                'username' => 'taro_a3',
            ]);

        $response->assertStatus(403);
    }
}
