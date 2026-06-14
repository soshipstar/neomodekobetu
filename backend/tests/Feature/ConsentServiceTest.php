<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\ConsentRecord;
use App\Models\Student;
use App\Services\ConsentService;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 同意基盤(S1): ConsentService の判定(AND条件・fail-closed)と
 * append-only な記録(grant/revoke でフラグと履歴を同時更新)を検証する。
 *
 * 差分カテゴリ: logic
 */
class ConsentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsentService $svc;
    private Company $company;
    private Classroom $room;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ConsentDefinitionSeeder::class);
        $this->svc = new ConsentService();

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true,
        ]);
        $this->student = Student::create([
            'student_name' => '児A', 'classroom_id' => $this->room->id,
            'status' => 'active', 'is_active' => true,
        ]);
    }

    public function test_defaults_to_no_consent(): void
    {
        $this->assertFalse($this->svc->canAggregate($this->company));
        $this->assertFalse($this->svc->canUseForLearning($this->student));
    }

    public function test_learning_requires_both_company_and_student(): void
    {
        // 児童のみ同意 → 施設未同意なので不可
        $this->svc->recordStudentConsent($this->student, true);
        $this->assertFalse($this->svc->canUseForLearning($this->student->refresh()));

        // 施設も同意 → 可
        $this->svc->recordCompanyConsent($this->company, true);
        $this->assertTrue($this->svc->canUseForLearning($this->student->refresh()));

        // 施設のみ・児童撤回 → 不可
        $this->svc->recordStudentConsent($this->student, false);
        $this->assertFalse($this->svc->canUseForLearning($this->student->refresh()));
    }

    public function test_company_grant_revoke_is_append_only(): void
    {
        $this->svc->recordCompanyConsent($this->company, true, userId: null, role: 'company_admin');
        $this->company->refresh();
        $this->assertTrue($this->company->ai_consent_aggregate);
        $this->assertNotNull($this->company->ai_consent_aggregate_at);
        $this->assertTrue($this->svc->canAggregate($this->company));

        $this->svc->recordCompanyConsent($this->company, false);
        $this->company->refresh();
        $this->assertFalse($this->company->ai_consent_aggregate);
        $this->assertNull($this->company->ai_consent_aggregate_at);

        // append-only: 2行(granted → revoked)残る
        $records = ConsentRecord::where('subject_type', 'company')->where('subject_id', $this->company->id)
            ->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame('granted', $records[0]->state);
        $this->assertSame('revoked', $records[1]->state);
        $this->assertSame('improvement_aggregate', $records[0]->consent_key);
    }

    public function test_grant_fails_closed_when_consent_definition_missing(): void
    {
        // 同意定義が無い環境で grant すると版/定義IDがNULLの「立証不能な同意」が積まれてしまう。
        // append-only で後から直せないため fail-closed: 例外で中断し、壊れた記録もフラグも残さない。
        \App\Models\ConsentDefinition::query()->delete();

        try {
            $this->svc->recordCompanyConsent($this->company, true);
            $this->fail('同意定義が無い場合の grant は例外を投げるべき');
        } catch (\App\Exceptions\ConsentDefinitionMissingException $e) {
            // 期待どおり
        }

        // Tx ロールバックで壊れたレコードもフラグも残らない
        $this->assertSame(0, ConsentRecord::where('subject_type', 'company')->count());
        $this->assertFalse($this->company->fresh()->ai_consent_aggregate);
    }

    public function test_revoke_allowed_even_when_consent_definition_missing(): void
    {
        // 撤回(revoke)は本人の権利として常に通す(定義欠落でもブロックしない)。
        $this->svc->recordCompanyConsent($this->company, true); // 定義ありで grant
        \App\Models\ConsentDefinition::query()->delete();
        \Illuminate\Support\Facades\Log::spy();

        $this->svc->recordCompanyConsent($this->company, false); // 定義を消した後でも撤回は通る

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, 'consent_definitions'))
            ->atLeast()->once();

        $this->assertFalse($this->company->fresh()->ai_consent_aggregate);
        $rec = ConsentRecord::where('subject_type', 'company')->latest('id')->first();
        $this->assertSame('revoked', $rec->state);
    }

    public function test_student_consent_records_version_and_company_id(): void
    {
        $this->svc->recordStudentConsent($this->student, true);
        $this->student->refresh();
        $this->assertTrue($this->student->ai_consent_learning);
        $this->assertSame(1, $this->student->ai_consent_learning_version);
        $this->assertNotNull($this->student->ai_consent_learning_at);

        $rec = ConsentRecord::where('subject_type', 'student')->where('subject_id', $this->student->id)->latest('id')->first();
        $this->assertSame('model_learning', $rec->consent_key);
        $this->assertSame(1, $rec->version);
        $this->assertSame($this->company->id, $rec->company_id);
        $this->assertNotNull($rec->consent_definition_id);

        // 撤回でフラグとバージョンがクリアされる
        $this->svc->recordStudentConsent($this->student, false);
        $this->student->refresh();
        $this->assertFalse($this->student->ai_consent_learning);
        $this->assertNull($this->student->ai_consent_learning_version);
        $this->assertNull($this->student->ai_consent_learning_at);
    }
}
