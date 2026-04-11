<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ER003: 事業所評価の結果閲覧系エンドポイントは管理者のみアクセス可
 *
 * 差分カテゴリ: api
 * 背景: 事業所評価の個別回答・集計・PDF・自己評価総括表などの「結果閲覧」は
 *       管理者 (user_type=admin) のみに限定する要件。
 *       スタッフは「回収状況 (responseStatus)」と「自分の回答フォーム
 *       (staffEvaluation)」のみ参照可。
 */
class ER003_FacilityEvaluationAdminGateTest extends TestCase
{
    use RefreshDatabase;

    private function setupFixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $staff = User::create([
            'username' => 'staff_er003',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        // 施設の管理者 (一般管理者)。マスターではない。
        $admin = User::create([
            'username' => 'admin_er003',
            'password' => bcrypt('pass'),
            'full_name' => '施設管理者',
            'user_type' => 'admin',
            'is_master' => false,
            'is_company_admin' => false,
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $periodId = DB::table('facility_evaluation_periods')->insertGetId([
            'fiscal_year' => 2025,
            'title' => '2025年度施設評価',
            'status' => 'aggregating',
            'classroom_id' => $classroom->id,
            'guardian_deadline' => '2026-03-31',
            'staff_deadline' => '2026-03-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('classroom', 'staff', 'admin', 'periodId');
    }

    private function master(): User
    {
        return User::create([
            'username' => 'master_er003',
            'password' => bcrypt('pass'),
            'full_name' => 'マスター',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    public function test_staff_cannot_access_summary(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/facility-evaluation/summary?period_id=' . $f['periodId'])
            ->assertStatus(403);
    }

    public function test_master_admin_cannot_access_summary(): void
    {
        // マスター管理者は事業所評価の結果を見られない（施設単位の関心事のため）
        $f = $this->setupFixture();
        $master = $this->master();
        $this->actingAs($master, 'sanctum')
            ->getJson('/api/staff/facility-evaluation/summary?period_id=' . $f['periodId'])
            ->assertStatus(403);
    }

    public function test_master_admin_cannot_access_responses_list(): void
    {
        $f = $this->setupFixture();
        $master = $this->master();
        $this->actingAs($master, 'sanctum')
            ->getJson('/api/staff/facility-evaluation/responses?period_id=' . $f['periodId'])
            ->assertStatus(403);
    }

    public function test_admin_can_access_summary(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['admin'], 'sanctum')
            ->getJson('/api/staff/facility-evaluation/summary?period_id=' . $f['periodId'])
            ->assertStatus(200);
    }

    public function test_staff_cannot_access_responses_list(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/facility-evaluation/responses?period_id=' . $f['periodId'])
            ->assertStatus(403);
    }

    public function test_staff_cannot_access_aggregate(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/facility-evaluation/aggregate', [
                'period_id' => $f['periodId'],
            ])
            ->assertStatus(403);
    }

    public function test_staff_cannot_save_facility_comment(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/facility-evaluation/facility-comment', [
                'period_id' => $f['periodId'],
                'question_id' => 1,
                'facility_comment' => 'test',
            ])
            ->assertStatus(403);
    }

    public function test_staff_cannot_create_period(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/facility-evaluation/periods', [
                'fiscal_year' => 2026,
            ])
            ->assertStatus(403);
    }

    public function test_staff_cannot_update_period_status(): void
    {
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->putJson("/api/staff/facility-evaluation/periods/{$f['periodId']}", [
                'status' => 'published',
            ])
            ->assertStatus(403);
    }

    public function test_staff_can_view_response_status(): void
    {
        // 回収状況 (誰が提出済みか) はスタッフも閲覧可
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/facility-evaluation/periods/{$f['periodId']}/status")
            ->assertStatus(200);
    }

    public function test_staff_can_access_own_evaluation_form(): void
    {
        // 自分の回答フォームはスタッフも使える
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/facility-evaluation/staff-evaluation?period_id=' . $f['periodId'])
            ->assertStatus(200);
    }

    public function test_staff_can_list_periods(): void
    {
        // 評価期間の一覧はスタッフも閲覧可（自分の回答フォームへの導線として）
        $f = $this->setupFixture();
        $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/facility-evaluation/periods')
            ->assertStatus(200);
    }

    public function test_guardian_api_hides_own_answers_after_submission(): void
    {
        // 保護者は自分の回答内容も閲覧不可。提出済みの場合 answers は空で返る。
        $company = Company::create(['name' => '企業B']);
        $classroom = Classroom::create([
            'classroom_name' => '別校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $periodId = DB::table('facility_evaluation_periods')->insertGetId([
            'fiscal_year' => 2025,
            'title' => '2025年度 (別)',
            'status' => 'collecting',
            'classroom_id' => $classroom->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $guardian = User::create([
            'username' => 'guardian_er003',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
        $evalId = DB::table('facility_guardian_evaluations')->insertGetId([
            'period_id' => $periodId,
            'guardian_id' => $guardian->id,
            'is_submitted' => true,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // 1 件の答えを入れておく
        $q = DB::table('facility_evaluation_questions')
            ->where('question_type', 'guardian')
            ->where('is_active', true)
            ->first();
        if ($q) {
            DB::table('facility_guardian_evaluation_answers')->insert([
                'evaluation_id' => $evalId,
                'question_id' => $q->id,
                'answer' => 'yes',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($guardian, 'sanctum')
            ->getJson('/api/guardian/evaluation');

        $response->assertStatus(200);
        // 提出済みなので answers は空
        $this->assertEquals([], $response->json('data.answers'));
        // evaluation オブジェクトは返る（is_submitted 表示用）
        $this->assertTrue((bool) $response->json('data.evaluation.is_submitted'));
    }
}
