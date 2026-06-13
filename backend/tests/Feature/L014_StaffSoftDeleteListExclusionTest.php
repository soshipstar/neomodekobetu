<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L014: スタッフ削除後の一覧除外・識別子再利用 (logic)
 *
 * 発生ページ: /admin/staff-management (StaffManagementController)
 * 報告: 「スタッフを削除しているが、無効で残っているのでメールアドレスが再度使用できない」
 *
 * 旧アプリ staff_management.php は `DELETE FROM users` の物理削除で、削除済みは
 * 一覧から消え username/email を再利用できた。新アプリは参照整合性 (面談記録・
 * 業務日誌など法定記録の cascade 消失防止) のため論理削除
 * (is_active=false + username='deleted__{id}__...' リネーム + email=null) を採用するが、
 * 論理削除済みレコードが一覧に「無効」として残り続けていた (= 報告の「無効で残っている」)。
 *
 * 本テストは:
 *  - 論理削除済みスタッフが一覧に出ないこと (旧アプリの物理削除と同じ可視挙動)
 *  - 単に無効化しただけのスタッフは一覧に残ること (過剰除外しない)
 *  - 削除後に同じ username / email で再登録できること (識別子解放)
 * を検証する。
 */
class L014_StaffSoftDeleteListExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company   = Company::create(['name' => '企業L014']);
        $classroom = Classroom::create(['classroom_name' => 'L014教室', 'company_id' => $company->id, 'is_active' => true]);

        $master = User::create([
            'username'     => 'master_l014',
            'password'     => bcrypt('pass'),
            'full_name'    => 'マスター',
            'user_type'    => 'admin',
            'is_master'    => true,
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        return compact('company', 'classroom', 'master');
    }

    public function test_soft_deleted_staff_hidden_but_disabled_staff_shown(): void
    {
        $f = $this->fixture();

        $toDelete = User::create([
            'username'     => 'taro_l014',
            'password'     => bcrypt('pass'),
            'full_name'    => '削除太郎',
            'email'        => 'taro@example.jp',
            'user_type'    => 'staff',
            'classroom_id' => $f['classroom']->id,
            'is_active'    => true,
        ]);

        $disabled = User::create([
            'username'     => 'jiro_l014',
            'password'     => bcrypt('pass'),
            'full_name'    => '無効次郎',
            'user_type'    => 'staff',
            'classroom_id' => $f['classroom']->id,
            'is_active'    => false, // 削除ではなく単なる無効化
        ]);

        // taro を削除 (論理削除: is_active=false + username リネーム + email=null)
        $this->actingAs($f['master'], 'sanctum')
            ->deleteJson('/api/admin/staff/' . $toDelete->id)
            ->assertOk();

        $res = $this->actingAs($f['master'], 'sanctum')
            ->getJson('/api/admin/staff?per_page=100')
            ->assertOk();

        $ids = collect($res->json('data.data'))->pluck('id');

        $this->assertFalse($ids->contains($toDelete->id), '論理削除済みスタッフが一覧に残っています (無効で残る不具合)。');
        $this->assertTrue($ids->contains($disabled->id), '単に無効化しただけのスタッフが一覧から消えています (過剰除外)。');
    }

    public function test_username_and_email_reusable_after_delete(): void
    {
        $f = $this->fixture();

        $original = User::create([
            'username'     => 'reuse_l014',
            'password'     => bcrypt('pass'),
            'full_name'    => '再利用太郎',
            'email'        => 'reuse@example.jp',
            'user_type'    => 'staff',
            'classroom_id' => $f['classroom']->id,
            'is_active'    => true,
        ]);

        $this->actingAs($f['master'], 'sanctum')
            ->deleteJson('/api/admin/staff/' . $original->id)
            ->assertOk();

        // 同じ username / email で再登録できる (識別子が解放されている)
        $this->actingAs($f['master'], 'sanctum')
            ->postJson('/api/admin/staff', [
                'username'     => 'reuse_l014',
                'password'     => 'secret123',
                'full_name'    => '新・再利用太郎',
                'email'        => 'reuse@example.jp',
                'classroom_id' => $f['classroom']->id,
            ])
            ->assertCreated();
    }
}
