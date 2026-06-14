<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\SupportKnowledge;
use App\Models\User;
use App\Services\KnowledgeDistillationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 支援知蒸留 D4/D5: 法人内の条件別蒸留(k匿名・同意)と児童条件での横断検索。
 *
 * 差分カテゴリ: logic
 */
class KnowledgeDistillationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->company = Company::create(['name' => '企業A', 'ai_consent_aggregate' => true]);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_kd_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    private function makeStudentWithRecord(string $grade): Student
    {
        $s = Student::create([
            'student_name' => '児'.uniqid(), 'classroom_id' => $this->room->id, 'grade_level' => $grade,
            'ai_consent_learning' => true, 'status' => 'active', 'is_active' => true,
        ]);
        AiRevisionEvent::create([
            'company_id' => $this->company->id, 'classroom_id' => $this->room->id, 'student_id' => $s->id,
            'document_type' => 'support_plan', 'document_id' => 1, 'section_key' => 'detail:health_life:support_content',
            'after_text' => '朝の支度を手順表を用いて自分で進める', 'changed' => true, 'edit_kind' => 'submit',
            'editor_role' => 'staff', 'sensitivity' => 'raw', 'support_category' => 'health_life',
            'structured' => ['text_length' => 20, 'tags' => ['health_life']],
        ]);

        return $s;
    }

    public function test_distills_per_condition_with_k_anonymity(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeStudentWithRecord('elementary_5'); // 小学生・S3 が5名
        }
        for ($i = 0; $i < 4; $i++) {
            $this->makeStudentWithRecord('junior_high_1'); // 中学生・S4 が4名(k匿名で除外)
        }

        $n = app(KnowledgeDistillationService::class)->rebuild($this->company->id);
        $this->assertSame(1, $n); // elementary/S3 のみ(junior_high は4名で除外)

        $k = SupportKnowledge::where('company_id', $this->company->id)->first();
        $this->assertSame('elementary', $k->cohort);
        $this->assertSame('S3', $k->growth_stage);
        $this->assertSame(5, $k->sample_n);
        $this->assertSame('health_life', $k->top_support_categories[0]['code']);
        $this->assertNull(SupportKnowledge::where('growth_stage', 'S4')->first()); // k匿名
    }

    public function test_skips_when_company_not_consented(): void
    {
        $this->company->update(['ai_consent_aggregate' => false]);
        for ($i = 0; $i < 5; $i++) {
            $this->makeStudentWithRecord('elementary_5');
        }
        $this->assertSame(0, app(KnowledgeDistillationService::class)->rebuild($this->company->id));
        $this->assertSame(0, SupportKnowledge::count());
    }

    public function test_lookup_endpoint_returns_matching_knowledge(): void
    {
        $target = null;
        for ($i = 0; $i < 5; $i++) {
            $target = $this->makeStudentWithRecord('elementary_5');
        }
        app(KnowledgeDistillationService::class)->rebuild($this->company->id);

        $res = $this->actingAs($this->staff, 'sanctum')->getJson("/api/staff/students/{$target->id}/knowledge");
        $res->assertStatus(200)
            ->assertJsonPath('data.cohort_label', '小学生')
            ->assertJsonPath('data.sample_n', 5);
        $this->assertSame('健康・生活', $res->json('data.top_support_categories.0.label'));
    }

    public function test_lookup_null_when_no_matching_condition(): void
    {
        // 蓄積なしの単独児童 → null(同条件の支援知が無い)
        $solo = $this->makeStudentWithRecord('high_school_2');
        $res = $this->actingAs($this->staff, 'sanctum')->getJson("/api/staff/students/{$solo->id}/knowledge");
        $res->assertStatus(200)->assertJsonPath('data', null);
    }
}
