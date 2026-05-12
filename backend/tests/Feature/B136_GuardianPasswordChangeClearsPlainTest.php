<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * B136: 保護者が自分でパスワードを変更したら users.password_plain を NULL にする
 *
 * 差分カテゴリ: logic
 *
 * 報告者 (淡田由貴) の問い合わせ:
 * > 現在のパスワードのところは保護者が変更したら表示されなくなるのだろうか？
 *
 * 従来挙動: Guardian/GuardianProfileController::changePassword は password (ハッシュ)
 * のみ更新し、password_plain は古い値のまま残っていた。これにより:
 *  - スタッフ編集画面の「現在のパスワード」欄に既に無効な平文が表示され続ける
 *  - スタッフが古い値を保護者に案内すると、保護者はログインできず混乱する
 *
 * 修正後: 保護者本人が変更したタイミングで password_plain を NULL に落とす。
 * 以降、編集画面では「（保護者により変更済み）」と表示される (画面側で実装)。
 * 紛失時はスタッフが編集画面の「新しいパスワード」欄でリセットすれば
 * password と password_plain が同時に新値で再発行される。
 */
class B136_GuardianPasswordChangeClearsPlainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{guardian:User}
     */
    private function fixture(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室B136', 'is_active' => true]);

        $guardian = User::create([
            'username'       => 'guardian_b136_' . uniqid(),
            'password'       => Hash::make('initial_pass'),
            'password_plain' => 'initial_pass', // 初期発行値
            'full_name'      => 'B136保護者',
            'user_type'      => 'guardian',
            'classroom_id'   => $classroom->id,
            'is_active'      => true,
        ]);

        return compact('guardian');
    }

    /**
     * 保護者が自分でパスワードを正常に変更すると password_plain が NULL になる
     */
    public function test_self_change_clears_password_plain(): void
    {
        ['guardian' => $g] = $this->fixture();

        $this->assertSame('initial_pass', $g->password_plain);

        $response = $this->actingAs($g, 'sanctum')
            ->putJson('/api/guardian/profile/password', [
                'current_password'          => 'initial_pass',
                'new_password'              => 'new_pass_4567',
                'new_password_confirmation' => 'new_pass_4567',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $g->refresh();
        // ハッシュは新値で更新される
        $this->assertTrue(Hash::check('new_pass_4567', $g->password));
        // 平文表示用は NULL にクリア
        $this->assertNull($g->password_plain);
    }

    /**
     * 現在のパスワード誤入力時は 422 で、password_plain は変わらない
     */
    public function test_wrong_current_password_keeps_plain_intact(): void
    {
        ['guardian' => $g] = $this->fixture();

        $response = $this->actingAs($g, 'sanctum')
            ->putJson('/api/guardian/profile/password', [
                'current_password'          => 'WRONG',
                'new_password'              => 'new_pass_4567',
                'new_password_confirmation' => 'new_pass_4567',
            ]);

        $response->assertStatus(422);
        $g->refresh();
        $this->assertSame('initial_pass', $g->password_plain);
        $this->assertTrue(Hash::check('initial_pass', $g->password));
    }

    /**
     * スタッフが編集画面でパスワードをリセットすると、password と password_plain
     * 両方が新値で再設定される (= 「現在のパスワード」欄に新値が再び表示される)
     */
    public function test_staff_reset_repopulates_password_plain(): void
    {
        ['guardian' => $g] = $this->fixture();

        // 保護者が一度変更 → plain = null
        $this->actingAs($g, 'sanctum')
            ->putJson('/api/guardian/profile/password', [
                'current_password'          => 'initial_pass',
                'new_password'              => 'mid_pass_8888',
                'new_password_confirmation' => 'mid_pass_8888',
            ])
            ->assertStatus(200);
        $this->assertNull($g->fresh()->password_plain);

        // スタッフが編集画面でリセット
        $staff = User::create([
            'username' => 'staff_b136_' . uniqid(),
            'password' => Hash::make('p'),
            'full_name' => 'B136スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $g->classroom_id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/guardians/{$g->id}", [
                'password' => 'reset_by_staff_999',
            ]);

        $response->assertStatus(200);
        $g->refresh();
        $this->assertSame('reset_by_staff_999', $g->password_plain);
        $this->assertTrue(Hash::check('reset_by_staff_999', $g->password));
    }
}
