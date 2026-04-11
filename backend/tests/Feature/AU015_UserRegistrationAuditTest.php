<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU015: 4 種類のユーザー/児童の新規登録フロー網羅テスト
 *
 * 差分カテゴリ: api
 * 背景: スタッフ管理で POST /api/admin/staff が 405 で落ちていたため、
 *       他の新規登録フロー（企業管理者・通常管理者・保護者・児童）も
 *       同様の不整合（ルート未登録 / store 未実装 / 必須項目の不一致）が
 *       無いか、実際に API を叩いて全件通ることを確認する。
 *
 * 対象:
 *  - 企業管理者 (user_type=admin, is_company_admin=true)
 *  - 通常管理者 (user_type=admin, is_master=false, is_company_admin=false)
 *  - 保護者 (user_type=guardian)
 *  - 児童 (Student)
 */
class AU015_UserRegistrationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $master = User::create([
            'username' => 'master_au015',
            'password' => bcrypt('pass'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        return compact('company', 'classroom', 'master');
    }

    // =========================================================================
    // 企業管理者 (is_company_admin=true) の新規登録
    // =========================================================================

    public function test_company_admin_creation_succeeds(): void
    {
        $s = $this->scaffold();

        $response = $this->actingAs($s['master'], 'sanctum')
            ->postJson('/api/admin/admin-accounts', [
                'username' => 'company_admin_1',
                'password' => 'pass123',
                'full_name' => '企業管理者',
                'is_master' => false,
                'is_company_admin' => true,
                'classroom_id' => $s['classroom']->id,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('admin', $data['user_type']);
        $this->assertFalse((bool) $data['is_master']);
        $this->assertTrue((bool) $data['is_company_admin']);
        $this->assertEquals($s['classroom']->id, $data['classroom_id']);
    }

    // =========================================================================
    // 通常管理者 (normal admin) の新規登録
    // =========================================================================

    public function test_normal_admin_creation_succeeds(): void
    {
        $s = $this->scaffold();

        $response = $this->actingAs($s['master'], 'sanctum')
            ->postJson('/api/admin/admin-accounts', [
                'username' => 'normal_admin_1',
                'password' => 'pass123',
                'full_name' => '通常管理者',
                'is_master' => false,
                'is_company_admin' => false,
                'classroom_id' => $s['classroom']->id,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('admin', $data['user_type']);
        $this->assertFalse((bool) $data['is_master']);
        $this->assertFalse((bool) $data['is_company_admin']);
    }

    // =========================================================================
    // 保護者 (guardian) の新規登録
    // =========================================================================

    public function test_guardian_creation_uses_auth_user_classroom_as_default(): void
    {
        // /admin/guardians のフォームは classroom_id を送らない。
        // 認証ユーザーの classroom_id がデフォルトで使われる。
        $s = $this->scaffold();

        $response = $this->actingAs($s['master'], 'sanctum')
            ->postJson('/api/admin/guardians', [
                'username' => 'guardian_user_1',
                'password' => 'pass123',
                'full_name' => '保護者',
                'email' => 'guardian@example.com',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('guardian', $data['user_type']);
        $this->assertEquals($s['classroom']->id, $data['classroom_id']);
    }

    public function test_guardian_creation_with_explicit_classroom(): void
    {
        $s = $this->scaffold();
        $otherClassroom = Classroom::create([
            'classroom_name' => '分校',
            'company_id' => $s['company']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($s['master'], 'sanctum')
            ->postJson('/api/admin/guardians', [
                'classroom_id' => $otherClassroom->id,
                'username' => 'guardian_user_2',
                'password' => 'pass123',
                'full_name' => '分校の保護者',
            ]);

        $response->assertStatus(201);
        $this->assertEquals($otherClassroom->id, $response->json('data.classroom_id'));
    }

    public function test_guardian_creation_rejects_classroom_without_company(): void
    {
        $orphan = Classroom::create([
            'classroom_name' => '企業なし教室',
            'company_id' => null,
            'is_active' => true,
        ]);
        $master = User::create([
            'username' => 'master_au015_orphan',
            'password' => bcrypt('pass'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'classroom_id' => $orphan->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/guardians', [
                'username' => 'guardian_orphan',
                'password' => 'pass123',
                'full_name' => '企業なし保護者',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    public function test_guardian_creation_rejects_when_no_classroom_at_all(): void
    {
        $master = User::create([
            'username' => 'master_au015_nothing',
            'password' => bcrypt('pass'),
            'full_name' => 'Master (no classroom)',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/guardians', [
                'username' => 'guardian_nowhere',
                'password' => 'pass123',
                'full_name' => '行き場なし',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classroom_id');
    }

    // =========================================================================
    // 児童 (Student) の新規登録
    // =========================================================================

    public function test_student_creation_via_admin_endpoint_succeeds(): void
    {
        $s = $this->scaffold();
        $guardian = User::create([
            'username' => 'guardian_for_student',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'classroom_id' => $s['classroom']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($s['master'], 'sanctum')
            ->postJson('/api/admin/students', [
                'classroom_id' => $s['classroom']->id,
                'student_name' => 'テスト児童',
                'username' => 'test_student_admin',
                'password' => 'pass',
                'birth_date' => '2018-04-01',
                'grade_level' => 'elementary_1',
                'guardian_id' => $guardian->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('テスト児童', $data['student_name']);
        $this->assertEquals($s['classroom']->id, $data['classroom_id']);
        $this->assertEquals($guardian->id, $data['guardian_id']);
    }

    public function test_student_creation_via_staff_endpoint_uses_auth_user_classroom(): void
    {
        $s = $this->scaffold();
        $staff = User::create([
            'username' => 'staff_au015',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $s['classroom']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/students', [
                'student_name' => 'スタッフ登録児童',
                'birth_date' => '2019-05-15',
                'status' => 'active',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals($s['classroom']->id, $data['classroom_id']);
    }
}
