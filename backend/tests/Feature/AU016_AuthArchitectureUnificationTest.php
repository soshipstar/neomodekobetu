<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU016: 認可アーキテクチャ統一の回帰テスト (ARCH-AUTH-01〜06)
 *
 * 差分カテゴリ: auth
 *
 * アーキテクチャ監査で検出した構造的リスクの修正を検証する:
 *  - バグパターンB (classroom_id=null で認可スキップ) を 44 箇所一掃し、
 *    基底 Controller::authorizeClassroomId() に統一
 *  - パターンC (accessibleClassroomIds 誤用) / パターンD (完全一致比較) も統一
 *  - ARCH-AUTH-05: 特権フラグ (is_master/is_company_admin) の整合性ガード
 */
class AU016_AuthArchitectureUnificationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $classA = Classroom::create(['classroom_name' => 'A1', 'company_id' => $companyA->id, 'is_active' => true]);
        $classB = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);

        $studentB = Student::create([
            'classroom_id' => $classB->id,
            'student_name' => '生徒B',
            'is_active'    => true,
        ]);

        return compact('companyA', 'companyB', 'classA', 'classB', 'studentB');
    }

    // =========================================================================
    // バグパターンB: classroom_id=null のユーザーが全件アクセスできない
    // =========================================================================

    public function test_null_classroom_user_is_denied_not_bypassed(): void
    {
        $f = $this->fixture();

        // classroom_id=null のスタッフ (旧実装ではガードが完全スキップされていた)
        $nullStaff = User::create([
            'username'     => 'null_staff_au016',
            'password'     => bcrypt('pass'),
            'full_name'    => 'classroom無スタッフ',
            'user_type'    => 'staff',
            'classroom_id' => null,
            'is_active'    => true,
        ]);

        // FaceSheet show (バグパターンB だった代表例) → null=全権限ではなく 403
        $this->actingAs($nullStaff, 'sanctum')
            ->getJson('/api/staff/students/' . $f['studentB']->id . '/face-sheet')
            ->assertStatus(403);
    }

    // =========================================================================
    // ARCH-AUTH-05: 特権フラグの整合性ガード
    // =========================================================================

    public function test_non_admin_cannot_hold_master_flag(): void
    {
        // staff ロールで is_master=true を立てて保存しようとしても false に補正される
        $staff = User::create([
            'username'         => 'fake_master_au016',
            'password'         => bcrypt('pass'),
            'full_name'        => '偽マスター',
            'user_type'        => 'staff',
            'is_master'        => true,        // 不正な昇格
            'is_company_admin' => true,        // 不正な昇格
            'is_active'        => true,
        ]);

        $fresh = $staff->fresh();
        $this->assertFalse((bool) $fresh->is_master, 'staff に is_master が立ったまま保存されています (ARCH-AUTH-05)。');
        $this->assertFalse((bool) $fresh->is_company_admin, 'staff に is_company_admin が立ったまま保存されています。');
    }

    public function test_admin_master_flag_is_preserved(): void
    {
        // 正規フロー: admin + is_master=true は保持される (ガードが正規を妨げない)
        $admin = User::create([
            'username'   => 'real_master_au016',
            'password'   => bcrypt('pass'),
            'full_name'  => '本物マスター',
            'user_type'  => 'admin',
            'is_master'  => true,
            'is_active'  => true,
        ]);

        $this->assertTrue((bool) $admin->fresh()->is_master, '正規の admin マスターフラグが消えています。');
    }

    public function test_staff_promoted_to_master_is_corrected_on_save(): void
    {
        $staff = User::create([
            'username'   => 'promote_au016',
            'password'   => bcrypt('pass'),
            'full_name'  => '昇格テスト',
            'user_type'  => 'staff',
            'is_active'  => true,
        ]);

        // 後から is_master を直接立てて保存 → saving ガードで補正
        $staff->is_master = true;
        $staff->save();

        $this->assertFalse((bool) $staff->fresh()->is_master);
    }
}
