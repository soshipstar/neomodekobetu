<?php

namespace Tests\Feature;

use App\Models\AiEditMetric;
use App\Models\AiEditReasonCategory;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\AiEditReasonCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AI学習基盤 S4d: 管理者レポートAPI(ai_edit_metrics の閲覧)。
 * 施設スコープ・権限・ラベル解決・facet/period・期間一覧を検証する。
 *
 * 差分カテゴリ: api
 */
class AiEditMetricsReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;
    private Classroom $roomA;
    private User $companyAdmin;
    private string $period;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AiEditReasonCategorySeeder::class);
        $this->period = Carbon::now()->format('Y-m');

        $this->companyA = Company::create(['name' => '企業A']);
        $this->companyB = Company::create(['name' => '企業B']);
        $this->roomA = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->companyA->id, 'is_active' => true]);
        $this->companyAdmin = User::create([
            'username' => 'cadmin_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '施設管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $this->roomA->id, 'is_active' => true,
        ]);

        $cat = AiEditReasonCategory::where('code', 'too_abstract')->first();
        $this->staff = User::create([
            'username' => 'staff_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '記入者花子',
            'user_type' => 'staff', 'classroom_id' => $this->roomA->id, 'is_active' => true,
        ]);

        // 集計済みセル(AiEditMetricsService の出力を模す)
        AiEditMetric::create(['period_ym' => $this->period, 'facet' => 'company', 'company_id' => $this->companyA->id,
            'gen_count' => 20, 'revision_count' => 12, 'edited_document_count' => 10, 'distinct_students' => 8,
            'edit_rate' => 0.5, 'change_ratio_avg' => 0.3, 'ai_acceptance' => 0.7,
            'top_reason_categories' => [['category_id' => $cat->id, 'count' => 5]], 'computed_at' => Carbon::now()]);
        AiEditMetric::create(['period_ym' => $this->period, 'facet' => 'cohort', 'company_id' => $this->companyA->id,
            'subj_cohort' => 'elementary', 'revision_count' => 7, 'distinct_students' => 6, 'ai_acceptance' => 0.6, 'computed_at' => Carbon::now()]);
        AiEditMetric::create(['period_ym' => $this->period, 'facet' => 'author', 'company_id' => $this->companyA->id,
            'author_user_id' => $this->staff->id, 'revision_count' => 5, 'distinct_students' => 5, 'ai_acceptance' => 0.65, 'computed_at' => Carbon::now()]);
        // 別企業のセル(企業Aの管理者には見えてはならない)
        AiEditMetric::create(['period_ym' => $this->period, 'facet' => 'company', 'company_id' => $this->companyB->id,
            'revision_count' => 99, 'distinct_students' => 9, 'computed_at' => Carbon::now()]);
    }

    private User $staff;

    public function test_company_admin_sees_only_own_company(): void
    {
        $res = $this->actingAs($this->companyAdmin, 'sanctum')->getJson("/api/admin/ai-edit-metrics?facet=company&period={$this->period}");
        $res->assertStatus(200)
            ->assertJsonPath('data.facet', 'company')
            ->assertJsonCount(1, 'data.rows');
        $this->assertSame('企業A', $res->json('data.rows.0.label'));
        $this->assertSame(0.7, $res->json('data.rows.0.ai_acceptance'));
        // top_reasons のラベルが解決される
        $this->assertSame('抽象的すぎる', $res->json('data.rows.0.top_reasons.0.label'));
        // 期間一覧が返る
        $this->assertContains($this->period, $res->json('data.periods'));
    }

    public function test_cohort_and_author_labels(): void
    {
        $cohort = $this->actingAs($this->companyAdmin, 'sanctum')->getJson("/api/admin/ai-edit-metrics?facet=cohort&period={$this->period}");
        $cohort->assertStatus(200)->assertJsonPath('data.rows.0.label', '小学生');

        $author = $this->actingAs($this->companyAdmin, 'sanctum')->getJson("/api/admin/ai-edit-metrics?facet=author&period={$this->period}");
        $author->assertStatus(200)->assertJsonPath('data.rows.0.label', '記入者花子');
    }

    public function test_master_sees_all_companies(): void
    {
        $master = User::create(['username' => 'm_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'マスター',
            'user_type' => 'admin', 'is_master' => true, 'classroom_id' => null, 'is_active' => true]);
        $res = $this->actingAs($master, 'sanctum')->getJson("/api/admin/ai-edit-metrics?facet=company&period={$this->period}");
        $res->assertStatus(200)->assertJsonCount(2, 'data.rows');
    }

    public function test_non_company_admin_forbidden(): void
    {
        $plain = User::create(['username' => 'a_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '一般管理者',
            'user_type' => 'admin', 'classroom_id' => $this->roomA->id, 'is_active' => true]);
        $this->actingAs($plain, 'sanctum')->getJson('/api/admin/ai-edit-metrics?facet=company')->assertStatus(403);
    }

    public function test_staff_cannot_access(): void
    {
        $this->actingAs($this->staff, 'sanctum')->getJson('/api/admin/ai-edit-metrics?facet=company')->assertStatus(403);
    }

    public function test_invalid_facet_rejected(): void
    {
        $this->actingAs($this->companyAdmin, 'sanctum')->getJson('/api/admin/ai-edit-metrics?facet=bogus')->assertStatus(422);
    }
}
