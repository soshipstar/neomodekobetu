<?php

namespace Tests\Feature;

use App\Models\AiGenerationEvent;
use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AiLearningCapture;
use App\Services\ConsentService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AIセーフティ 観点5(プライバシー保護)の回帰ガード:
 * Layer1原本(ai_revision_events.before_text/after_text)は実名を保持するが、
 * DBには暗号化して保存され、平文の氏名が残ってはならない。
 * また diff / ai_edit_reasons.source_ref(いずれも非暗号化jsonb)にも実名を入れない。
 *
 * 差分カテゴリ: auth
 */
class A007_NoPlaintextPiiInRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_revision_text_is_encrypted_at_rest_but_readable_via_model(): void
    {
        $this->seed(ConsentDefinitionSeeder::class); // 同意記録に版が必要(fail-closed)
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $guardian = User::create([
            'username' => 'g_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '山田花子',
            'user_type' => 'guardian', 'classroom_id' => $room->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '山田太郎', 'classroom_id' => $room->id, 'guardian_id' => $guardian->id,
            'status' => 'active', 'is_active' => true,
        ]);

        $consent = new ConsentService();
        $consent->recordCompanyConsent($company, true);
        $consent->recordStudentConsent($student, true);

        $name = '山田太郎';
        $guardianName = '山田花子';
        (new AiLearningCapture($consent))->recordSectionRevisions($student, 'support_plan', 1, [
            'long_term_goal' => [
                "{$name}は母({$guardianName})と離れて活動できる",
                "{$name}は集団の中で自分の役割を担い、母({$guardianName})の付き添い無しで活動できる",
            ],
        ], editKind: 'submit', annotations: [
            ['field' => 'long_term_goal', 'type' => 'added', 'text' => "{$name}の成長", 'reason' => "{$guardianName}の要望"],
        ]);

        // モデル経由(復号後)は実名が読める = 学習に使える
        $rev = AiRevisionEvent::first();
        $this->assertStringContainsString($name, $rev->before_text);
        $this->assertStringContainsString($name, $rev->after_text);

        // 生のDB値(暗号文)には平文の実名が無い
        $raw = DB::table('ai_revision_events')->where('id', $rev->id)->first();
        $this->assertStringNotContainsString($name, (string) $raw->before_text);
        $this->assertStringNotContainsString($name, (string) $raw->after_text);
        $this->assertStringNotContainsString($guardianName, (string) $raw->before_text);
        $this->assertStringNotContainsString($guardianName, (string) $raw->after_text);

        // 非暗号化カラム(diff)に実名が漏れていない(数値のみ)
        $this->assertStringNotContainsString($name, (string) $raw->diff);
        $this->assertStringNotContainsString($guardianName, (string) $raw->diff);

        // 修正理由の source_ref(非暗号化jsonb)に実名が漏れていない
        $reasonRow = DB::table('ai_edit_reasons')->where('ai_revision_event_id', $rev->id)->first();
        $this->assertNotNull($reasonRow);
        $this->assertStringNotContainsString($name, (string) $reasonRow->source_ref);
        $this->assertStringNotContainsString($guardianName, (string) $reasonRow->source_ref);
    }

    public function test_section_key_and_support_category_have_no_plaintext_pii(): void
    {
        $this->seed(ConsentDefinitionSeeder::class);
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $student = Student::create(['student_name' => '山田太郎', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true]);
        $consent = new ConsentService();
        $consent->recordCompanyConsent($company, true);
        $consent->recordStudentConsent($student, true);

        $name = '山田太郎';
        // 職員が区分(sub_category)欄に実名を入れてしまった場合でも、section_key 構築は安全化される。
        $safeSub = AiLearningCapture::safeSectionSub("{$name}の生活習慣");
        $this->assertStringNotContainsString($name, $safeSub);
        $this->assertSame('health_life', $safeSub); // 5領域が検出され実名は剥がれる

        $key = "detail:{$safeSub}:support_content";
        (new AiLearningCapture($consent))->recordSectionRevisions($student, 'support_plan', 1, [
            $key => ["{$name}は着替えに支援が必要", "{$name}は自分で着替えられた"],
        ]);

        $raw = DB::table('ai_revision_events')->where('section_key', $key)->first();
        $this->assertNotNull($raw);
        // 非暗号化列(section_key/support_category)に実名が無い
        $this->assertStringNotContainsString($name, (string) $raw->section_key);
        $this->assertStringNotContainsString($name, (string) $raw->support_category);
        $this->assertSame('health_life', $raw->support_category);
    }

    public function test_generation_event_omits_gender_without_child_consent(): void
    {
        $this->seed(ConsentDefinitionSeeder::class);
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $student = Student::create(['student_name' => '児A', 'classroom_id' => $room->id, 'gender' => 'female', 'status' => 'active', 'is_active' => true]);
        $consent = new ConsentService();
        $capture = new AiLearningCapture($consent);

        // 施設の集計同意のみ(児童の学習同意なし) → 生成イベントは記録されるが性別は乗せない
        $consent->recordCompanyConsent($company, true);
        $gen = $capture->recordGeneration($student, 'support_plan', 1, 'support_plan_edit', 'gpt', ['x' => 'y']);
        $this->assertNotNull($gen);
        $this->assertNull($gen->subj_gender); // ★要配慮属性は児童同意なしでは保存しない
        $this->assertSame('other', $gen->subj_cohort); // 学年由来の非要配慮次元は保存される

        // 児童も学習同意 → 修正イベントには性別が乗る(canUseForLearning ゲート通過)
        $consent->recordStudentConsent($student, true);
        $capture->recordSectionRevisions($student->refresh(), 'support_plan', 1, ['long_term_goal' => ['旧', '新']]);
        $rev = AiRevisionEvent::where('section_key', 'long_term_goal')->first();
        $this->assertSame('female', $rev->subj_gender);
    }
}
