<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Services\AiLearningCapture;
use App\Services\ConsentService;
use App\Services\StructuredExtractionService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 支援知蒸留エンジン D1: L2 構造化(ルール)。タグ + 結果/仮説マーカー、本文非保存(PII無し)。
 *
 * 差分カテゴリ: logic
 */
class StructuredExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_extract_tags_and_markers(): void
    {
        $s = StructuredExtractionService::extract(
            '集団活動に自分から参加できた。見通しを示したためと思われる。',
            'social_relations', 12, 'elementary', 'S3',
        );

        $this->assertTrue($s['has_result_marker']);     // 参加/できた
        $this->assertTrue($s['has_hypothesis_marker']); // ため/思われ
        $this->assertContains('social_relations', $s['tags']);
        $this->assertContains('elementary', $s['tags']);
        $this->assertContains('S3', $s['tags']);
        $this->assertContains('program:12', $s['tags']);
        $this->assertSame('rule', $s['method']);

        // 事実のみ(結果/仮説語なし)
        $plain = StructuredExtractionService::extract('工作をした。', 'cognitive_behavior', null, 'elementary', 'S2');
        $this->assertFalse($plain['has_result_marker']);
        $this->assertFalse($plain['has_hypothesis_marker']);
    }

    public function test_structured_is_pii_free_and_attached_on_capture(): void
    {
        $this->seed(ConsentDefinitionSeeder::class);
        $consent = new ConsentService();
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $student = Student::create(['student_name' => '山田太郎', 'classroom_id' => $room->id, 'grade_level' => 'elementary_5', 'status' => 'active', 'is_active' => true]);
        $consent->recordCompanyConsent($company, true);
        $consent->recordStudentConsent($student, true);

        (new AiLearningCapture($consent))->recordSectionRevisions($student->refresh(), 'support_plan', 1, [
            'long_term_goal' => ['初期', '山田太郎は集団活動に自分から参加できた。安心できる環境のためと思われる。'],
        ], editorUserId: null);

        $rev = AiRevisionEvent::where('section_key', 'long_term_goal')->first();
        $this->assertNotNull($rev->structured);
        $this->assertTrue($rev->structured['has_result_marker']);
        $this->assertTrue($rev->structured['has_hypothesis_marker']);
        // 本体項目なので support_category=null、成長段階/コホートのタグは付く
        $this->assertContains('S3', $rev->structured['tags']);
        $this->assertContains('elementary', $rev->structured['tags']);

        // structured(非暗号化jsonb)に実名が無い(本文を保存しない)
        $this->assertStringNotContainsString('山田太郎', json_encode($rev->structured, JSON_UNESCAPED_UNICODE));
    }
}
