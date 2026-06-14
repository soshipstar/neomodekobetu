<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\SupporterLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 支援知蒸留 D3: 支援者成長モデル(Lv1〜4判定とレベル別の問い返し方針)。
 *
 * 差分カテゴリ: logic
 */
class SupporterLevelServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupporterLevelService $svc;
    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->svc = new SupporterLevelService();
        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->student = Student::create(['student_name' => '児A', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_lv_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    private function makeRev(bool $hyp, bool $res, ?string $exemplar = null): void
    {
        AiRevisionEvent::create([
            'company_id' => $this->company->id, 'classroom_id' => $this->room->id, 'student_id' => $this->student->id,
            'document_type' => 'support_plan', 'document_id' => 1, 'section_key' => 'long_term_goal',
            'after_text' => 'x', 'changed' => true, 'editor_user_id' => $this->staff->id, 'editor_role' => 'staff', 'sensitivity' => 'raw',
            'exemplar_status' => $exemplar,
            'structured' => ['has_hypothesis_marker' => $hyp, 'has_result_marker' => $res, 'tags' => [], 'text_length' => 1],
        ]);
    }

    public function test_level1_when_few_records(): void
    {
        $this->makeRev(false, false);
        $this->makeRev(false, false);
        $this->assertSame(1, $this->svc->levelFor($this->staff->id)['level']);
    }

    public function test_level2_results_only(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeRev(false, true); // 結果は書けるが仮説なし
        }
        $this->assertSame(2, $this->svc->levelFor($this->staff->id)['level']);
    }

    public function test_level3_writes_hypotheses(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeRev(true, true); // 仮説あり
        }
        $this->makeRev(false, true);
        $this->makeRev(false, true); // 5件中3件仮説=0.6、見本採用なし → Lv3
        $this->assertSame(3, $this->svc->levelFor($this->staff->id)['level']);
    }

    public function test_level4_hypotheses_and_adopted(): void
    {
        $this->makeRev(true, true, 'adopted'); // 見本採用
        for ($i = 0; $i < 2; $i++) {
            $this->makeRev(true, true);
        }
        $this->makeRev(false, true);
        $this->makeRev(false, true); // 5件中3件仮説=0.6 + 採用1 → Lv4
        $r = $this->svc->levelFor($this->staff->id);
        $this->assertSame(4, $r['level']);
        $this->assertSame('ベテラン', $r['label']);
        $this->assertSame(1, $r['signals']['adopted_exemplars']);
    }

    public function test_inquiry_policy_scales_with_level(): void
    {
        // 新人ほど問いが多く、熟達ほど少ない(足場を外す)
        $this->assertGreaterThan($this->svc->inquiryPolicy(4)['question_count'], $this->svc->inquiryPolicy(1)['question_count']);
    }
}
