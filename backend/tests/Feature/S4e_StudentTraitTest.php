<?php

namespace Tests\Feature;

use App\Models\AiRevisionEvent;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AiLearningCapture;
use App\Support\StudentTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 S4e: 特性(統制タグ)の語彙正規化・代理記録API・多次元スナップショット凍結。
 *
 * 差分カテゴリ: logic / api
 */
class S4e_StudentTraitTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->company = Company::create(['name' => '企業A', 'ai_consent_aggregate' => true]);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_trait_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'student_name' => '児'.uniqid(), 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ], $attrs));
    }

    public function test_sanitize_filters_unknown_and_dedups_in_vocabulary_order(): void
    {
        // 未知コード・自由記述・重複は捨て、語彙順に整列する(決定的)。
        $out = StudentTrait::sanitize(['social_support', 'sensory_sensitive', '田中太郎', 'social_support', 'bogus']);
        $this->assertSame(['sensory_sensitive', 'social_support'], $out);
        $this->assertSame([], StudentTrait::sanitize('not-an-array'));
        $this->assertCount(16, StudentTrait::vocabulary());
    }

    public function test_update_persists_sanitized_traits_and_show_returns_them(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/staff/students/{$student->id}/traits", [
                'traits' => ['social_support', 'sensory_sensitive', 'sensory_sensitive'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.selected', ['sensory_sensitive', 'social_support']);

        $this->assertSame(['sensory_sensitive', 'social_support'], $student->fresh()->traits);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/students/{$student->id}/traits")
            ->assertStatus(200)
            ->assertJsonPath('data.selected', ['sensory_sensitive', 'social_support'])
            ->assertJsonCount(16, 'data.available');
    }

    public function test_update_rejects_unknown_code(): void
    {
        $student = $this->makeStudent();
        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/staff/students/{$student->id}/traits", ['traits' => ['not_a_real_trait']])
            ->assertStatus(422);
    }

    public function test_authz_forbidden_for_out_of_scope_classroom(): void
    {
        $otherRoom = Classroom::create(['classroom_name' => '別事業所', 'company_id' => $this->company->id, 'is_active' => true]);
        $outsider = Student::create([
            'student_name' => '別児', 'classroom_id' => $otherRoom->id, 'grade_level' => 'elementary_3',
            'status' => 'active', 'is_active' => true,
        ]);
        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/students/{$outsider->id}/traits")
            ->assertStatus(403);
    }

    public function test_revision_freezes_traits_into_dim_meta_when_consented(): void
    {
        // 同意あり(施設集計 AND 児童学習)+ 特性あり → 修正イベントの dim_meta に凍結。
        $student = $this->makeStudent([
            'ai_consent_learning' => true,
            'traits' => ['social_support', 'sensory_sensitive'],
        ]);

        $n = app(AiLearningCapture::class)->recordSectionRevisions(
            $student,
            'support_plan',
            1,
            ['detail:health_life:support_content' => ['元の文', '修正後の文']],
            'submit',
        );

        $this->assertSame(1, $n);
        $event = AiRevisionEvent::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(['traits' => ['sensory_sensitive', 'social_support']], $event->dim_meta);
    }

    public function test_revision_dim_meta_null_when_no_traits(): void
    {
        $student = $this->makeStudent(['ai_consent_learning' => true]); // 特性なし
        app(AiLearningCapture::class)->recordSectionRevisions(
            $student, 'support_plan', 2, ['detail:health_life:support_content' => ['a', 'b']], 'submit',
        );
        $event = AiRevisionEvent::where('student_id', $student->id)->firstOrFail();
        $this->assertNull($event->dim_meta);
    }
}
