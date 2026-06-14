<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\SupportKnowledge;
use App\Services\KnowledgeDistillationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * rank13 見本抜粋のマスク強化: support_knowledge.exemplar_excerpts のPIIフリー回帰ガード。
 *
 * 支援知の見本抜粋は外部提示(D5 横断検索)に出るため、施設氏名マスク+構造化PII除去に加え、
 * マスク不能な短名(1文字氏名)が残る抜粋は丸ごと捨てる(WritingProfileService::scrubExcerpt 共通経路)。
 *
 * 差分カテゴリ: logic
 */
class KnowledgeExcerptPiiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function makeRevision(Company $c, Classroom $room, Student $s, string $afterText): void
    {
        AiRevisionEvent::create([
            'company_id' => $c->id, 'classroom_id' => $room->id, 'student_id' => $s->id,
            'document_type' => 'support_plan', 'document_id' => 1,
            'section_key' => 'detail:health_life:support_content',
            'after_text' => $afterText, 'changed' => true, 'edit_kind' => 'submit',
            'editor_role' => 'staff', 'sensitivity' => 'raw', 'support_category' => 'health_life',
            'structured' => ['text_length' => mb_strlen($afterText), 'tags' => ['health_life']],
        ]);
    }

    public function test_exemplar_excerpts_contain_no_plaintext_names(): void
    {
        $company = Company::create(['name' => '企業A', 'ai_consent_aggregate' => true]);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);

        // 同条件(小学生/S3)で5名 + 短名1名 = 6名(k匿名クリア)。
        $names = [];
        for ($i = 0; $i < 5; $i++) {
            $name = "田中太郎{$i}"; // 2文字以上 → マスク登録される
            $names[] = $name;
            $s = Student::create([
                'student_name' => $name, 'classroom_id' => $room->id, 'grade_level' => 'elementary_5',
                'ai_consent_learning' => true, 'status' => 'active', 'is_active' => true,
            ]);
            $this->makeRevision($company, $room, $s, "{$name}は朝の支度を手順表で自分で進めた。例{$i}");
        }
        // 1文字氏名(マスク不能)→ その抜粋は丸ごと捨てられるべき。
        $shortNamed = Student::create([
            'student_name' => '愛', 'classroom_id' => $room->id, 'grade_level' => 'elementary_5',
            'ai_consent_learning' => true, 'status' => 'active', 'is_active' => true,
        ]);
        $this->makeRevision($company, $room, $shortNamed, '愛は集中して活動に取り組んだ。');

        app(KnowledgeDistillationService::class)->rebuild($company->id);

        $k = SupportKnowledge::where('company_id', $company->id)->firstOrFail();
        $blob = json_encode($k->exemplar_excerpts, JSON_UNESCAPED_UNICODE);

        $this->assertNotEmpty($k->exemplar_excerpts, '見本抜粋が空でない(マスク済の田中の例が残る)');
        $this->assertStringContainsString('【児童】', $blob, '児童名がプレースホルダにマスクされている');
        foreach ($names as $name) {
            $this->assertStringNotContainsString($name, $blob, "実名 {$name} が抜粋に残っていない");
        }
        // 短名(愛)が残る抜粋は捨てられ、本文ごと出てこない。
        $this->assertStringNotContainsString('愛は集中して活動に取り組んだ', $blob, '短名が残る抜粋は破棄される');
    }
}
