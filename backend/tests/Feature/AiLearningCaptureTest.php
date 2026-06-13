<?php

namespace Tests\Feature;

use App\Models\AiEditReason;
use App\Models\AiGenerationEvent;
use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\User;
use App\Services\AiLearningCapture;
use App\Services\ConsentService;
use App\Support\PiiMasker;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 配管(S2): AiLearningCapture の生成/修正蓄積と同意ゲート(fail-closed)、
 * および個別支援計画 update 経由の修正イベント蓄積を検証する。
 *
 * 差分カテゴリ: logic
 */
class AiLearningCaptureTest extends TestCase
{
    use RefreshDatabase;

    private AiLearningCapture $capture;
    private ConsentService $consent;
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
        $this->consent = new ConsentService();
        $this->capture = new AiLearningCapture($this->consent);

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true,
        ]);
        $this->student = Student::create([
            'student_name' => '山田太郎', 'classroom_id' => $this->room->id,
            'status' => 'active', 'is_active' => true,
        ]);
    }

    private function grantAggregate(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);
    }

    private function grantLearning(): void
    {
        $this->grantAggregate();
        $this->consent->recordStudentConsent($this->student, true);
    }

    public function test_generation_requires_company_consent(): void
    {
        // 未同意では記録しない(fail-closed)
        $this->assertNull($this->capture->recordGeneration(
            $this->student, 'support_plan', 1, 'support_plan_edit', 'gpt', ['long_term_goal' => 'x']
        ));
        $this->assertSame(0, AiGenerationEvent::count());

        // 施設同意後は記録され、payloadはマスクされる
        $this->grantAggregate();
        $this->student->refresh(); // 直前の呼び出しでロード済みの classroom.company を破棄(同意反映)
        $event = $this->capture->recordGeneration(
            $this->student, 'support_plan', 1, 'support_plan_edit', 'gpt',
            ['long_term_goal' => '山田太郎は集団で活動できる'],
        );
        $this->assertNotNull($event);
        $this->assertTrue($event->pii_masked);
        $this->assertStringNotContainsString('山田太郎', json_encode($event->generated_payload, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('【児童】', json_encode($event->generated_payload, JSON_UNESCAPED_UNICODE));
        $this->assertSame($this->company->id, $event->company_id);
    }

    public function test_revision_requires_full_and_consent(): void
    {
        $sections = ['long_term_goal' => ['旧目標', '新目標']];

        // 何も無し → 0
        $this->assertSame(0, $this->capture->recordSectionRevisions($this->student, 'support_plan', 1, $sections));

        // 施設のみ同意 → AND不成立で 0
        $this->grantAggregate();
        $this->assertSame(0, $this->capture->recordSectionRevisions($this->student, 'support_plan', 1, $sections));

        // 児童も同意 → 記録される
        $this->consent->recordStudentConsent($this->student, true);
        $this->assertSame(1, $this->capture->recordSectionRevisions($this->student, 'support_plan', 1, $sections));
        $this->assertSame(1, AiRevisionEvent::count());
    }

    public function test_revision_records_only_changed_sections_and_diff(): void
    {
        $this->grantLearning();
        $n = $this->capture->recordSectionRevisions($this->student, 'support_plan', 7, [
            'long_term_goal' => ['集団生活に慣れる', '集団生活の中で役割を担う'], // 変更あり
            'short_term_goal' => ['同じ文', '同じ文'],                              // 変更なし → 記録されない
            'overall_policy' => ['', '新規に追記された方針'],                        // 追記
        ], editKind: 'submit', editorUserId: null, editorRole: 'staff');

        $this->assertSame(2, $n);
        $this->assertSame(2, AiRevisionEvent::count());

        $ltg = AiRevisionEvent::where('section_key', 'long_term_goal')->first();
        $this->assertSame('集団生活に慣れる', $ltg->before_text); // encrypted cast 経由で復号
        $this->assertSame('集団生活の中で役割を担う', $ltg->after_text);
        $this->assertGreaterThan(0.0, $ltg->change_ratio);
        $this->assertLessThanOrEqual(1.0, $ltg->change_ratio);
        $this->assertSame('submit', $ltg->edit_kind);

        // 新規追記は change_ratio=1.0
        $op = AiRevisionEvent::where('section_key', 'overall_policy')->first();
        $this->assertNull($op->before_text);
        $this->assertSame(1.0, $op->change_ratio);
    }

    public function test_revision_links_annotations_as_reasons(): void
    {
        $this->grantLearning();
        $this->capture->recordSectionRevisions($this->student, 'support_plan', 7, [
            'long_term_goal' => ['旧', '新'],
            'detail:生活習慣:goal' => ['旧目標', '新目標'],
        ], editKind: 'revised_draft', editorRole: 'ai_revision', annotations: [
            ['field' => 'long_term_goal', 'type' => 'added', 'text' => '...', 'reason' => '保護者の要望'],
            ['field' => 'detail:生活習慣', 'type' => 'removed', 'text' => '...', 'reason' => '議事録'],
        ]);

        $ltg = AiRevisionEvent::where('section_key', 'long_term_goal')->first();
        $reason = AiEditReason::where('ai_revision_event_id', $ltg->id)->first();
        $this->assertNotNull($reason);
        $this->assertSame('ai_annotation', $reason->reason_source);
        $this->assertSame('added', $reason->source_ref['annotation_type']);
        // source_ref に自由記述本文(実名混入の恐れ)を残さない
        $this->assertArrayNotHasKey('reason', $reason->source_ref);

        // detail:生活習慣 という field は detail:生活習慣:goal セクションへ前方一致で紐づく
        $detail = AiRevisionEvent::where('section_key', 'detail:生活習慣:goal')->first();
        $this->assertSame('removed', AiEditReason::where('ai_revision_event_id', $detail->id)->first()->source_ref['annotation_type']);
    }

    public function test_support_plan_update_endpoint_captures_revision(): void
    {
        $this->grantLearning();
        $staff = User::create([
            'username' => 'staff_cap_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $plan = IndividualSupportPlan::create([
            'student_id' => $this->student->id, 'classroom_id' => $this->room->id, 'status' => 'draft',
            'long_term_goal' => '初期の長期目標', 'short_term_goal' => '初期の短期目標',
        ]);

        $res = $this->actingAs($staff, 'sanctum')->putJson("/api/staff/support-plans/{$plan->id}", [
            'status' => 'draft',
            'long_term_goal' => '改訂した長期目標(集団の中で役割を担う)',
        ]);
        $res->assertStatus(200);

        $rev = AiRevisionEvent::where('document_type', 'support_plan')->where('document_id', $plan->id)
            ->where('section_key', 'long_term_goal')->first();
        $this->assertNotNull($rev);
        $this->assertSame('初期の長期目標', $rev->before_text);
        $this->assertSame('改訂した長期目標(集団の中で役割を担う)', $rev->after_text);
        $this->assertSame('save_draft', $rev->edit_kind);
        $this->assertSame($staff->id, $rev->editor_user_id);

        // 変更していない short_term_goal は記録されない
        $this->assertSame(0, AiRevisionEvent::where('document_id', $plan->id)
            ->where('section_key', 'short_term_goal')->count());
    }

    public function test_update_endpoint_skips_capture_without_consent(): void
    {
        // 同意なし: update は通るが蓄積はされない(本処理を止めない)
        $staff = User::create([
            'username' => 'staff_noc_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $plan = IndividualSupportPlan::create([
            'student_id' => $this->student->id, 'classroom_id' => $this->room->id, 'status' => 'draft',
            'long_term_goal' => '初期',
        ]);

        $this->actingAs($staff, 'sanctum')->putJson("/api/staff/support-plans/{$plan->id}", [
            'status' => 'draft', 'long_term_goal' => '変更後',
        ])->assertStatus(200);

        $this->assertSame(0, AiRevisionEvent::count());
        $this->assertSame('変更後', $plan->fresh()->long_term_goal); // 本処理は成功
    }
}
