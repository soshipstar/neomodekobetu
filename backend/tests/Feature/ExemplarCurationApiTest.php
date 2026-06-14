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
 * 見本キュレーション(管理者): 候補一覧(マスク済プレビュー)と採用/除外。
 *
 * 差分カテゴリ: api
 */
class ExemplarCurationApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $admin;
    private AiRevisionEvent $rev;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->student = Student::create(['student_name' => '山田太郎', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);
        $this->admin = User::create([
            'username' => 'ca_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '施設管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->rev = AiRevisionEvent::create([
            'company_id' => $this->company->id, 'classroom_id' => $this->room->id, 'student_id' => $this->student->id,
            'document_type' => 'support_plan', 'document_id' => 1, 'section_key' => 'long_term_goal',
            'after_text' => '山田太郎は集団活動に自分から参加できる', 'change_ratio' => 0.4, 'changed' => true,
            'edit_kind' => 'official', 'editor_role' => 'staff', 'sensitivity' => 'raw',
            'structured' => ['text_length' => 20, 'has_hypothesis_marker' => false, 'tags' => []],
        ]);
    }

    public function test_index_returns_masked_preview_and_stats(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/exemplars');
        $res->assertStatus(200)->assertJsonCount(1, 'data.items');
        $preview = $res->json('data.items.0.preview');
        // プレビューに実名が出ない(施設マスカーでマスク)
        $this->assertStringNotContainsString('山田太郎', $preview);
        $this->assertStringContainsString('集団活動に自分から参加', $preview);
        $this->assertNull($res->json('data.items.0.exemplar_status'));
        // 運用可視化: 進捗統計
        $this->assertSame(1, $res->json('data.stats.finalized_total'));
        $this->assertSame(1, $res->json('data.stats.uncurated'));
        $this->assertSame(0, $res->json('data.stats.adopted'));
        // 因果マーカー無し→推奨ではない
        $this->assertSame(0, $res->json('data.stats.recommended_uncurated'));
        $this->assertFalse($res->json('data.items.0.recommended'));
    }

    public function test_recommended_uncurated_surfaced_first(): void
    {
        // 因果(仮説)まで書けた未判定の確定記録 → 推奨候補として先頭に出る
        $rec = AiRevisionEvent::create([
            'company_id' => $this->company->id, 'classroom_id' => $this->room->id, 'student_id' => $this->student->id,
            'document_type' => 'support_plan', 'document_id' => 2, 'section_key' => 'short_term_goal',
            'after_text' => '不安が高いため、見通しを示すと落ち着いて参加できた', 'change_ratio' => 0.3, 'changed' => true,
            'edit_kind' => 'official', 'editor_role' => 'staff', 'sensitivity' => 'raw',
            'structured' => ['text_length' => 25, 'has_hypothesis_marker' => true, 'has_result_marker' => true, 'tags' => []],
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/exemplars')->assertStatus(200);
        $this->assertSame(1, $res->json('data.stats.recommended_uncurated'));
        // 推奨は先頭(sortByDesc recommended)
        $this->assertSame($rec->id, $res->json('data.items.0.id'));
        $this->assertTrue($res->json('data.items.0.recommended'));
    }

    public function test_admin_adopts_and_excludes(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/exemplars/{$this->rev->id}", ['status' => 'adopted'])
            ->assertStatus(200);
        $this->assertSame('adopted', $this->rev->fresh()->exemplar_status);
        $this->assertSame($this->admin->id, $this->rev->fresh()->curated_by);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/exemplars/{$this->rev->id}", ['status' => 'cleared'])
            ->assertStatus(200);
        $this->assertNull($this->rev->fresh()->exemplar_status);
    }

    public function test_other_company_admin_forbidden(): void
    {
        $otherCompany = Company::create(['name' => '企業B']);
        $otherRoom = Classroom::create(['classroom_name' => 'B', 'company_id' => $otherCompany->id, 'is_active' => true]);
        $otherAdmin = User::create([
            'username' => 'cb_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'B管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $otherRoom->id, 'is_active' => true,
        ]);
        $this->actingAs($otherAdmin, 'sanctum')->postJson("/api/admin/exemplars/{$this->rev->id}", ['status' => 'adopted'])
            ->assertStatus(403);
    }

    public function test_staff_cannot_curate(): void
    {
        $staff = User::create([
            'username' => 's_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/exemplars')->assertStatus(403);
    }
}
