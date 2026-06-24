<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SOSHIP Growth OS 連携(SSOログイン)を「企業単位」で切り替える。
 * POST /api/admin/companies/{company}/soship
 *
 * 差分カテゴリ: screen
 * - マスター管理者のみ操作可（通常管理者は 403）。
 * - 企業フラグ更新に加え、配下の全事業所(classrooms.soship_enabled)へ一括適用する。
 * - 既定は false（無効）。
 */
class SoshipToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function admin(bool $isMaster): User
    {
        return User::create([
            'username' => 'adm_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '管理者',
            'user_type' => 'admin', 'is_master' => $isMaster, 'classroom_id' => null, 'is_active' => true,
        ]);
    }

    public function test_master_can_enable_and_it_cascades_to_all_classrooms(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => '事業所B', 'company_id' => $company->id, 'is_active' => true]);

        // 既定は false（無効）
        $this->assertFalse((bool) $company->fresh()->soship_enabled);
        $this->assertFalse((bool) $roomA->fresh()->soship_enabled);

        $master = $this->admin(true);

        $res = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/soship", ['soship_enabled' => true]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.soship_enabled', true);
        $res->assertJsonPath('data.applied_classrooms', 2);

        $this->assertTrue((bool) $company->fresh()->soship_enabled);
        $this->assertTrue((bool) $roomA->fresh()->soship_enabled);
        $this->assertTrue((bool) $roomB->fresh()->soship_enabled);

        // 無効化も配下へ一括適用される
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/soship", ['soship_enabled' => false])
            ->assertStatus(200);
        $this->assertFalse((bool) $company->fresh()->soship_enabled);
        $this->assertFalse((bool) $roomA->fresh()->soship_enabled);
        $this->assertFalse((bool) $roomB->fresh()->soship_enabled);
    }

    public function test_normal_admin_cannot_toggle(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);

        $admin = $this->admin(false); // 非マスター

        $res = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/soship", ['soship_enabled' => true]);

        $res->assertStatus(403);
        // 変更されていない（既定 false のまま）
        $this->assertFalse((bool) $company->fresh()->soship_enabled);
        $this->assertFalse((bool) $room->fresh()->soship_enabled);
    }
}
