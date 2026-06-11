<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 能力評価システム P1: 事業所(教室)単位の利用トグル。
 *
 * 個別支援計画で能力評価を参考データとして使うかを教室設定で ON/OFF でき、
 * index/update API に反映される。通常管理者は自教室のみ変更可(越境は403)。
 *
 * 差分カテゴリ: screen
 */
class AbilityAssessmentToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function admin(Classroom $room, bool $isMaster = false): User
    {
        return User::create([
            'username' => 'adm_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '管理者',
            'user_type' => 'admin', 'is_master' => $isMaster, 'classroom_id' => $room->id, 'is_active' => true,
        ]);
    }

    public function test_admin_can_toggle_ability_assessment_for_own_classroom(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);

        $admin = $this->admin($roomA);

        // 既定は false で index に出る
        $idx = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/classroom-settings');
        $idx->assertStatus(200);
        $this->assertFalse(collect($idx->json('data'))->firstWhere('id', $roomA->id)['ability_assessment_enabled']);

        // ON にする
        $res = $this->actingAs($admin, 'sanctum')->putJson('/api/admin/classroom-settings', [
            'classroom_id' => $roomA->id,
            'ability_assessment_enabled' => true,
        ]);
        $res->assertStatus(200);
        $this->assertTrue((bool) Classroom::find($roomA->id)->ability_assessment_enabled);

        // index にも反映
        $idx2 = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/classroom-settings');
        $this->assertTrue(collect($idx2->json('data'))->firstWhere('id', $roomA->id)['ability_assessment_enabled']);

        // OFF に戻せる
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/classroom-settings', [
            'classroom_id' => $roomA->id,
            'ability_assessment_enabled' => false,
        ])->assertStatus(200);
        $this->assertFalse((bool) Classroom::find($roomA->id)->ability_assessment_enabled);
    }

    public function test_normal_admin_cannot_toggle_other_classroom(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => '事業所B', 'company_id' => $company->id, 'is_active' => true]);

        $adminA = $this->admin($roomA); // 非マスター

        $res = $this->actingAs($adminA, 'sanctum')->putJson('/api/admin/classroom-settings', [
            'classroom_id' => $roomB->id,
            'ability_assessment_enabled' => true,
        ]);
        $res->assertStatus(403);
        $this->assertFalse((bool) Classroom::find($roomB->id)->ability_assessment_enabled);
    }
}
