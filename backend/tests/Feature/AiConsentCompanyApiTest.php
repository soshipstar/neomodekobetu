<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\ConsentRecord;
use App\Models\User;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 同意UI(S1配管の上物): 施設の集計同意API。
 * 施設管理者が自施設の improvement_aggregate を設定でき、履歴(consent_records)が追記されること。
 *
 * 差分カテゴリ: api
 */
class AiConsentCompanyApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ConsentDefinitionSeeder::class);
        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
    }

    private function companyAdmin(): User
    {
        return User::create([
            'username' => 'cadmin_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '施設管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    public function test_company_admin_can_view_and_toggle_aggregate_consent(): void
    {
        $admin = $this->companyAdmin();

        // 初期は未同意
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ai-consent/company')
            ->assertStatus(200)
            ->assertJsonPath('data.ai_consent_aggregate', false)
            ->assertJsonPath('data.company_id', $this->company->id);

        // ON
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/ai-consent/company', ['granted' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.ai_consent_aggregate', true);
        $this->assertTrue($this->company->fresh()->ai_consent_aggregate);
        $this->assertNotNull($this->company->fresh()->ai_consent_aggregate_at);

        // OFF
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/ai-consent/company', ['granted' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.ai_consent_aggregate', false);
        $this->assertFalse($this->company->fresh()->ai_consent_aggregate);

        // append-only: granted → revoked の2行が施設宛で残る
        $records = ConsentRecord::where('subject_type', 'company')->where('subject_id', $this->company->id)->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame('improvement_aggregate', $records[0]->consent_key);
        $this->assertSame('granted', $records[0]->state);
        $this->assertSame('revoked', $records[1]->state);
        $this->assertSame('company_admin', $records[0]->granted_by_role);
    }

    public function test_non_company_admin_cannot_toggle(): void
    {
        // 通常管理者(is_company_admin=false, is_master=false): 閲覧は可・変更は403
        $plainAdmin = User::create([
            'username' => 'admin_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '管理者',
            'user_type' => 'admin', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);

        $this->actingAs($plainAdmin, 'sanctum')->getJson('/api/admin/ai-consent/company')->assertStatus(200);
        $this->actingAs($plainAdmin, 'sanctum')->putJson('/api/admin/ai-consent/company', ['granted' => true])
            ->assertStatus(403);
        $this->assertFalse($this->company->fresh()->ai_consent_aggregate);
    }

    public function test_master_without_company_gets_409(): void
    {
        $master = User::create([
            'username' => 'master_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'マスター',
            'user_type' => 'admin', 'is_master' => true, 'classroom_id' => null, 'is_active' => true,
        ]);

        $this->actingAs($master, 'sanctum')->getJson('/api/admin/ai-consent/company')->assertStatus(409);
        $this->actingAs($master, 'sanctum')->putJson('/api/admin/ai-consent/company', ['granted' => true])->assertStatus(409);
    }

    public function test_staff_cannot_access_admin_route(): void
    {
        $staff = User::create([
            'username' => 'staff_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/ai-consent/company')->assertStatus(403);
    }
}
