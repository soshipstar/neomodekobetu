<?php

namespace Tests\Feature;

use App\Http\Controllers\Staff\AssessmentController;
use App\Models\AssessmentGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * rank1(改) 家庭視点をAI生成入力へ: 保護者アセスメント要約の整形ロジック。
 *
 * 保護者アセスメントはAI生成を持たない(保護者の直接記入)ため ai_revision_events では捕捉しない。
 * 代わりに「家庭からの視点」を職員アセスメントのAI生成入力に加える(マスクは生成時の$maskerが担う)。
 *
 * 差分カテゴリ: logic
 */
class GuardianAssessmentGenerationInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_null_entry_yields_empty_summary(): void
    {
        $this->assertSame('', AssessmentController::guardianAssessmentSummary(null));
    }

    public function test_summary_includes_filled_fields_and_omits_empty(): void
    {
        $g = new AssessmentGuardian([
            'student_wish' => '家では絵を描くのが好き',
            'home_challenges' => '朝の支度に時間がかかる',
            'domain_health_life' => '偏食が強い',
            'short_term_goal' => '', // 空は出さない
            'domain_social_relations' => null, // null は出さない
        ]);

        $summary = AssessmentController::guardianAssessmentSummary($g);

        $this->assertStringContainsString('本人の願い(家庭)', $summary);
        $this->assertStringContainsString('家では絵を描くのが好き', $summary);
        $this->assertStringContainsString('家庭での困りごと', $summary);
        $this->assertStringContainsString('朝の支度に時間がかかる', $summary);
        $this->assertStringContainsString('健康・生活(家庭)', $summary);
        $this->assertStringContainsString('偏食が強い', $summary);
        // 空/null 項目のラベルは出ない
        $this->assertStringNotContainsString('短期目標(家庭の希望)', $summary);
        $this->assertStringNotContainsString('人間関係・社会性(家庭)', $summary);
    }

    public function test_summary_empty_when_no_fields_filled(): void
    {
        $g = new AssessmentGuardian(['student_wish' => '', 'home_challenges' => '   ']);
        $this->assertSame('', AssessmentController::guardianAssessmentSummary($g));
    }
}
