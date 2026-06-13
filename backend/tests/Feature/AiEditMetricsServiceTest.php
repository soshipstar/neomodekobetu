<?php

namespace Tests\Feature;

use App\Models\AiEditMetric;
use App\Models\AiEditReason;
use App\Models\AiEditReasonCategory;
use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AiEditMetricsService;
use App\Services\AiLearningCapture;
use App\Services\ConsentService;
use Database\Seeders\AiEditReasonCategorySeeder;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AI学習基盤 Layer2(S4c): ai_edit_metrics 集計。
 * 同意済みのみ・k匿名(<5は秘匿)・facet別ロールアップ・冪等を検証する。
 *
 * 差分カテゴリ: logic
 */
class AiEditMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private User $staff;
    private ConsentService $consent;
    private AiLearningCapture $capture;
    private string $period;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ConsentDefinitionSeeder::class);
        $this->seed(AiEditReasonCategorySeeder::class);
        $this->consent = new ConsentService();
        $this->capture = new AiLearningCapture($this->consent);
        $this->period = Carbon::now()->format('Y-m'); // イベントのcreated_at(DB now)に合わせる

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_m_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->consent->recordCompanyConsent($this->company, true);
    }

    private function makeStudent(string $grade): Student
    {
        $s = Student::create([
            'student_name' => '児'.uniqid(), 'classroom_id' => $this->room->id,
            'grade_level' => $grade, 'status' => 'active', 'is_active' => true,
        ]);
        $this->consent->recordStudentConsent($s, true);

        return $s->refresh();
    }

    private function recordFor(Student $s, int $i): void
    {
        $this->capture->recordGeneration($s, 'support_plan', $s->id, 'support_plan_edit', 'gpt', ['long_term_goal' => 'x']);
        $this->capture->recordSectionRevisions($s, 'support_plan', $s->id, [
            'long_term_goal' => ['初期の長期目標です', "改訂した長期目標になりました{$i}"],
        ], editKind: 'submit', editorUserId: $this->staff->id);
    }

    public function test_rollup_respects_k_anonymity_and_facets(): void
    {
        // 小学生5名(コホートelementary, S3)+ 中学生1名(junior_high, S4)
        for ($i = 0; $i < 5; $i++) {
            $this->recordFor($this->makeStudent('elementary_5'), $i);
        }
        $this->recordFor($this->makeStudent('junior_high_1'), 99);

        $n = (new AiEditMetricsService())->rebuild($this->period);
        $this->assertGreaterThan(0, $n);

        // company facet: 6名・修正6件・生成6件・edit_rate=1.0
        $company = AiEditMetric::where('period_ym', $this->period)->where('facet', 'company')->first();
        $this->assertNotNull($company);
        $this->assertSame(6, $company->distinct_students);
        $this->assertSame(6, $company->revision_count);
        $this->assertSame(6, $company->gen_count);
        $this->assertSame(1.0, $company->edit_rate);
        $this->assertNotNull($company->change_ratio_avg);
        $this->assertNotNull($company->ai_acceptance);

        // cohort facet: elementary(5名)は出る、junior_high(1名)はk匿名で出ない
        $this->assertNotNull(AiEditMetric::where('facet', 'cohort')->where('subj_cohort', 'elementary')->first());
        $this->assertNull(AiEditMetric::where('facet', 'cohort')->where('subj_cohort', 'junior_high')->first());

        // author facet: 全修正が同一スタッフ → 6名のセル
        $author = AiEditMetric::where('facet', 'author')->where('author_user_id', $this->staff->id)->first();
        $this->assertNotNull($author);
        $this->assertSame(6, $author->distinct_students);

        // support_category facet: long_term_goal は5領域でない → null。よって support_category セルは無い
        $this->assertSame(0, AiEditMetric::where('facet', 'support_category')->count());
    }

    public function test_top_reason_categories_aggregated(): void
    {
        $cat = AiEditReasonCategory::where('code', 'too_abstract')->first();
        for ($i = 0; $i < 5; $i++) {
            $s = $this->makeStudent('elementary_5');
            $this->recordFor($s, $i);
            $rev = AiRevisionEvent::where('student_id', $s->id)->first();
            AiEditReason::create([
                'ai_revision_event_id' => $rev->id, 'category_id' => $cat->id,
                'reason_source' => 'human_manual', 'user_id' => $this->staff->id,
            ]);
        }

        (new AiEditMetricsService())->rebuild($this->period);

        $company = AiEditMetric::where('facet', 'company')->first();
        $this->assertNotEmpty($company->top_reason_categories);
        $this->assertSame($cat->id, $company->top_reason_categories[0]['category_id']);
        $this->assertSame(5, $company->top_reason_categories[0]['count']);
    }

    public function test_excludes_revoked_consent_and_is_idempotent(): void
    {
        $students = [];
        for ($i = 0; $i < 5; $i++) {
            $students[] = $s = $this->makeStudent('elementary_5');
            $this->recordFor($s, $i);
        }

        $svc = new AiEditMetricsService();
        $svc->rebuild($this->period);
        $this->assertSame(5, AiEditMetric::where('facet', 'company')->first()->distinct_students);

        // 1名が同意撤回 → 4名になりk匿名で company セルが消える
        $this->consent->recordStudentConsent($students[0], false);
        $count2 = $svc->rebuild($this->period);
        $this->assertNull(AiEditMetric::where('facet', 'company')->first()); // 4 < K

        // 冪等: 再実行で件数が二重化しない
        $count3 = $svc->rebuild($this->period);
        $this->assertSame($count2, $count3);
    }
}
