<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 見本キュレーション運用の quick wins:
 *  (c) 同意撤回(児童 ai_consent_learning=false)済みの記録はキュレーション母集団から除外
 *      (学習に使われないものを候補にも出さない=撤回反映の非対称解消)。
 *  (a) 見本の採否操作を audit_logs に記録(ガバナンス)。
 *
 * 分類: logic
 */
class ExemplarCurationConsentAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $company = Company::create(['name' => 'A法人', 'ai_consent_aggregate' => true]);
        $room = Classroom::create(['classroom_name' => 'A教室', 'company_id' => $company->id, 'is_active' => true]);
        $admin = User::create([
            'username' => 'admin_' . uniqid(),
            'password' => bcrypt('p'),
            'full_name' => '管理者',
            'user_type' => 'admin',
            'is_company_admin' => true,
            'company_id' => $company->id,
            'classroom_id' => $room->id,
            'is_active' => true,
        ]);

        return [$company, $room, $admin];
    }

    private function revisionFor(Student $student, Classroom $room, string $text): AiRevisionEvent
    {
        return AiRevisionEvent::create([
            'company_id'    => $room->company_id,
            'classroom_id'  => $room->id,
            'student_id'    => $student->id,
            'document_type' => 'monitoring',
            'document_id'   => 1,
            'section_key'   => 'overall_comment',
            'after_text'    => $text,
            'changed'       => true,
            'edit_kind'     => 'official',
            'structured'    => ['text_length' => 20, 'has_result_marker' => true],
            'created_at'    => now(),
        ]);
    }

    public function test_index_excludes_records_of_students_without_learning_consent(): void
    {
        [$company, $room, $admin] = $this->context();

        $consented = Student::create(['student_name' => '同意児', 'classroom_id' => $room->id, 'ai_consent_learning' => true, 'is_active' => true]);
        $withdrawn = Student::create(['student_name' => '撤回児', 'classroom_id' => $room->id, 'ai_consent_learning' => false, 'is_active' => true]);

        $evOk = $this->revisionFor($consented, $room, '同意児の良い確定記述です');
        $evNg = $this->revisionFor($withdrawn, $room, '撤回児の確定記述です');

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/exemplars')->assertStatus(200);

        $ids = collect($res->json('data.items'))->pluck('id')->all();
        $this->assertContains($evOk->id, $ids, '同意済み児童の記録は候補に出る');
        $this->assertNotContains($evNg->id, $ids, '同意撤回児童の記録は候補から除外される');
        $this->assertSame(1, $res->json('data.stats.finalized_total'), '母集団も同意済みのみ');
    }

    public function test_setStatus_writes_audit_log(): void
    {
        [$company, $room, $admin] = $this->context();
        $student = Student::create(['student_name' => '同意児', 'classroom_id' => $room->id, 'ai_consent_learning' => true, 'is_active' => true]);
        $ev = $this->revisionFor($student, $room, '採用候補の記述');

        $res = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/exemplars/{$ev->id}", ['status' => 'adopted'])
            ->assertStatus(200);

        $this->assertSame('adopted', $ev->fresh()->exemplar_status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id'      => $admin->id,
            'action'       => 'exemplar_curation',
            'target_table' => 'ai_revision_events',
            'target_id'    => $ev->id,
        ]);
    }
}
