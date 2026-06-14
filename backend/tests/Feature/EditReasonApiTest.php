<?php

namespace Tests\Feature;

use App\Models\AiEditReason;
use App\Models\AiEditReasonCandidate;
use App\Models\AiEditReasonCategory;
use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AiEditReasonCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AI学習基盤 §11: 修正理由(chips + 自由記述)の記録と動的カテゴリ昇格。
 *
 * 差分カテゴリ: api
 */
class EditReasonApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;
    private AiRevisionEvent $rev;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AiEditReasonCategorySeeder::class);

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->student = Student::create(['student_name' => '山田太郎', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_er_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->rev = AiRevisionEvent::create([
            'company_id' => $this->company->id, 'classroom_id' => $this->room->id, 'student_id' => $this->student->id,
            'document_type' => 'support_plan', 'document_id' => 1, 'section_key' => 'long_term_goal',
            'before_text' => '旧', 'after_text' => '新', 'change_ratio' => 0.5, 'changed' => true,
            'edit_kind' => 'submit', 'editor_user_id' => $this->staff->id, 'editor_role' => 'staff', 'sensitivity' => 'raw',
        ]);
    }

    public function test_categories_list(): void
    {
        $res = $this->actingAs($this->staff, 'sanctum')->getJson('/api/staff/edit-reason-categories');
        $res->assertStatus(200);
        $this->assertSame(11, count($res->json('data')));
    }

    public function test_attach_chips_and_masked_free_text_creates_candidate(): void
    {
        $tooAbstract = AiEditReasonCategory::where('code', 'too_abstract')->first();

        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/edit-reasons/{$this->rev->id}/attach", [
            'category_ids' => [$tooAbstract->id],
            'free_text' => '山田太郎の様子が伝わらない',
        ])->assertStatus(200);

        // chip理由が human_manual で付与され usage_count が増える
        $this->assertDatabaseHas('ai_edit_reasons', [
            'ai_revision_event_id' => $this->rev->id, 'category_id' => $tooAbstract->id, 'reason_source' => 'human_manual',
        ]);
        $this->assertSame(1, AiEditReasonCategory::whereKey($tooAbstract->id)->value('usage_count'));

        // 自由記述はマスクされて保存(実名が残らない)
        $freeRow = AiEditReason::where('ai_revision_event_id', $this->rev->id)->whereNotNull('free_text')->first();
        $this->assertNotNull($freeRow);
        $this->assertStringNotContainsString('山田太郎', $freeRow->free_text);
        $this->assertStringContainsString('【児童】', $freeRow->free_text);

        // 既存カテゴリ名に一致しない自由記述 → 候補化(マスク済テキスト)
        $cand = AiEditReasonCandidate::where('company_id', $this->company->id)->first();
        $this->assertNotNull($cand);
        $this->assertStringNotContainsString('山田太郎', $cand->normalized_text);
        $this->assertSame('pending', $cand->status);
    }

    public function test_reattach_replaces_previous_reasons(): void
    {
        $a = AiEditReasonCategory::where('code', 'too_abstract')->first();
        $b = AiEditReasonCategory::where('code', 'too_verbose')->first();

        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/edit-reasons/{$this->rev->id}/attach", ['category_ids' => [$a->id]])->assertStatus(200);
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/staff/edit-reasons/{$this->rev->id}/attach", ['category_ids' => [$b->id]])->assertStatus(200);

        $reasons = AiEditReason::where('ai_revision_event_id', $this->rev->id)->where('reason_source', 'human_manual')->pluck('category_id')->filter()->all();
        $this->assertSame([$b->id], array_values($reasons)); // 再選択で置換
    }

    public function test_cross_classroom_forbidden(): void
    {
        $otherRoom = Classroom::create(['classroom_name' => '別', 'company_id' => $this->company->id, 'is_active' => true]);
        $other = User::create(['username' => 'o_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '別',
            'user_type' => 'staff', 'classroom_id' => $otherRoom->id, 'is_active' => true]);
        $this->actingAs($other, 'sanctum')->postJson("/api/staff/edit-reasons/{$this->rev->id}/attach", ['category_ids' => []])->assertStatus(403);
    }

    public function test_admin_promotes_candidate(): void
    {
        $admin = User::create(['username' => 'ca_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '施設管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $this->room->id, 'is_active' => true]);
        $cand = AiEditReasonCandidate::create([
            'company_id' => $this->company->id, 'normalized_text' => '見通しが弱い', 'frequency' => 3, 'distinct_users' => 2, 'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/edit-reason-candidates')
            ->assertStatus(200)->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/edit-reason-candidates/{$cand->id}/promote", [
            'code' => 'foresight_weak', 'label_ja' => '見通しが弱い',
        ])->assertStatus(200);

        $this->assertDatabaseHas('ai_edit_reason_categories', ['code' => 'foresight_weak', 'company_id' => $this->company->id, 'is_seeded' => false]);
        $this->assertSame('merged', $cand->fresh()->status);

        // 昇格後はチップ候補として選択肢に出る
        $this->actingAs($this->staff, 'sanctum')->getJson('/api/staff/edit-reason-categories')
            ->assertStatus(200);
        $this->assertSame(12, AiEditReasonCategory::where('status', 'active')->count()); // 固定11 + 昇格1
    }

    public function test_staff_cannot_promote(): void
    {
        $cand = AiEditReasonCandidate::create(['company_id' => $this->company->id, 'normalized_text' => 'x', 'frequency' => 1, 'distinct_users' => 1, 'status' => 'pending']);
        $this->actingAs($this->staff, 'sanctum')->getJson('/api/admin/edit-reason-candidates')->assertStatus(403);
    }
}
