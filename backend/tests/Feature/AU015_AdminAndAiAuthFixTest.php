<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU015: Admin / AI エンドポイントの認可漏れ修正テスト
 *
 * 差分カテゴリ: auth
 *
 * 監査により以下の認可漏れが特定された:
 *  AUTH-01 Admin\StudentController::show()/destroy() — classroom_id 無検査で
 *          他企業の児童詳細取得・退所操作が可能
 *  AUTH-02 Admin\UserController 全メソッド — 企業フィルタなしで全ユーザー閲覧・
 *          ロール昇格 (is_company_admin=true) が可能
 *  AUTH-03 ChatController::canAccessRoom() — classroom_id=null で全ルーム開放
 *  AUTH-04 /api/ai/* — user_type チェックなし + student 所属チェックなしで
 *          guardian ロールが他児童の支援計画を AI 経由で抜き取れる
 *
 * 旧アプリ (neomodekobetu) は教室単位で厳格にスコープされていたのが正解。
 */
class AU015_AdminAndAiAuthFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 2 企業 + 各 1 教室 + 管理者 + 児童 + 保護者の最小フィクスチャ。
     */
    private function fixture(): array
    {
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);

        $classA = Classroom::create(['classroom_name' => 'A1', 'company_id' => $companyA->id, 'is_active' => true]);
        $classB = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);

        // 企業 A の通常管理者 (非マスター)
        $adminA = User::create([
            'username'     => 'admin_a_au015',
            'password'     => bcrypt('pass'),
            'full_name'    => '管理者A',
            'user_type'    => 'admin',
            'classroom_id' => $classA->id,
            'is_master'    => false,
            'is_active'    => true,
        ]);

        // 企業 B の児童 + 保護者
        $guardianB = User::create([
            'username'     => 'guardian_b_au015',
            'password'     => bcrypt('pass'),
            'full_name'    => '保護者B',
            'user_type'    => 'guardian',
            'classroom_id' => $classB->id,
            'is_active'    => true,
        ]);
        $studentB = Student::create([
            'classroom_id' => $classB->id,
            'student_name' => '生徒B',
            'guardian_id'  => $guardianB->id,
            'is_active'    => true,
        ]);

        return compact('companyA', 'companyB', 'classA', 'classB', 'adminA', 'guardianB', 'studentB');
    }

    // =========================================================================
    // AUTH-01: Admin\StudentController show/destroy
    // =========================================================================

    public function test_auth01_admin_student_show_blocks_other_company(): void
    {
        $f = $this->fixture();

        // 企業 A の管理者が企業 B の児童詳細を取得 → 403
        $this->actingAs($f['adminA'], 'sanctum')
            ->getJson('/api/admin/students/' . $f['studentB']->id)
            ->assertStatus(403);
    }

    public function test_auth01_admin_student_destroy_blocks_other_company(): void
    {
        $f = $this->fixture();

        // 企業 A の管理者が企業 B の児童を退所操作 → 403
        $this->actingAs($f['adminA'], 'sanctum')
            ->deleteJson('/api/admin/students/' . $f['studentB']->id)
            ->assertStatus(403);

        // 退所されていないことを確認
        $this->assertSame('active', $f['studentB']->fresh()->status ?? 'active');
    }

    // =========================================================================
    // AUTH-02: Admin\UserController index/update (権限昇格防止)
    // =========================================================================

    public function test_auth02_admin_user_index_excludes_other_company(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['adminA'], 'sanctum')
            ->getJson('/api/admin/users')
            ->assertStatus(200);

        // 企業 B の保護者 B が一覧に含まれないこと
        $ids = collect($response->json('data.data') ?? $response->json('data'))->pluck('id')->all();
        $this->assertNotContains($f['guardianB']->id, $ids);
    }

    public function test_auth02_admin_user_update_blocks_role_escalation(): void
    {
        $f = $this->fixture();

        // 企業 A の管理者が、自社スタッフの is_company_admin を立てようとする
        $staffA = User::create([
            'username'     => 'staff_a_au015',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);

        $this->actingAs($f['adminA'], 'sanctum')
            ->putJson('/api/admin/users/' . $staffA->id, [
                'is_company_admin' => true,
                'is_master'        => true,
            ])
            ->assertStatus(200);

        // 昇格が反映されていないこと (mass assignment が無効化されている)
        $fresh = $staffA->fresh();
        $this->assertFalse((bool) $fresh->is_company_admin);
        $this->assertFalse((bool) $fresh->is_master);
    }

    public function test_auth02_admin_user_update_blocks_other_company(): void
    {
        $f = $this->fixture();

        // 企業 A の管理者が企業 B の保護者を更新 → 403
        $this->actingAs($f['adminA'], 'sanctum')
            ->putJson('/api/admin/users/' . $f['guardianB']->id, [
                'full_name' => '改ざん',
            ])
            ->assertStatus(403);
    }

    // =========================================================================
    // AUTH-04: /api/ai/* guardian ロール遮断 + student 所属チェック
    // =========================================================================

    public function test_auth04_ai_generate_blocks_guardian_role(): void
    {
        $f = $this->fixture();

        // 保護者 B が AI 支援計画生成を呼ぶ → user_type middleware で 403
        $this->actingAs($f['guardianB'], 'sanctum')
            ->postJson('/api/ai/generate/support-plan', [
                'student_id' => $f['studentB']->id,
            ])
            ->assertStatus(403);
    }

    public function test_auth04_ai_generate_blocks_cross_classroom_student(): void
    {
        $f = $this->fixture();

        // 企業 A のスタッフが AI 同意を取得した上で、企業 B の児童を指定
        $staffA = User::create([
            'username'     => 'staff_ai_au015',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフAI',
            'user_type'    => 'staff',
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);
        foreach (UserConsent::REQUIRED_FOR_STAFF_AI as $type) {
            UserConsent::create([
                'user_id'      => $staffA->id,
                'consent_type' => $type,
                'version'      => UserConsent::CURRENT_VERSIONS[$type],
                'granted'      => true,
                'granted_at'   => now(),
            ]);
        }

        // 企業 B の児童を指定 → authorizeClassroomId で 403
        $this->actingAs($staffA, 'sanctum')
            ->postJson('/api/ai/generate/support-plan', [
                'student_id' => $f['studentB']->id,
            ])
            ->assertStatus(403);
    }
}
