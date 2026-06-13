<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AiLearningCapture;
use App\Services\ConsentService;
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
}
