<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class L002_GuardianSupportPlanReviewTest extends TestCase
{
    use DatabaseMigrations;

    private function createTestData(): array
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $guardian = User::create([
            'username' => 'guardian_test',
            'password' => bcrypt('password'),
            'full_name' => 'Test Guardian',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'guardian_id' => $guardian->id,
            'is_active' => true,
        ]);

        $plan = IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'student_name' => 'Test Student',
            'created_date' => '2025-08-17',
            'status' => 'published',
        ]);

        return compact('classroom', 'guardian', 'student', 'plan');
    }

    public function test_guardian_can_submit_review_comment(): void
    {
        $data = $this->createTestData();

        $response = $this->actingAs($data['guardian'], 'sanctum')
            ->postJson("/api/guardian/support-plans/{$data['plan']->id}/review", [
                'comment' => 'Please adjust the goal for domain 2.',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // コメントは日時見出し付き(【日時】\n本文)で追記保存されるため、部分一致で検証する。
        $saved = (string) $data['plan']->fresh()->guardian_review_comment;
        $this->assertStringContainsString('Please adjust the goal for domain 2.', $saved);
    }

    public function test_guardian_review_requires_comment(): void
    {
        // レビュー=コメント追記。承認は別途 sign(電子署名)エンドポイントで行うため、
        // 空コメントはバリデーションエラー(422)。
        $data = $this->createTestData();

        $response = $this->actingAs($data['guardian'], 'sanctum')
            ->postJson("/api/guardian/support-plans/{$data['plan']->id}/review", [
                'comment' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
    }

    public function test_guardian_cannot_review_other_students_plan(): void
    {
        $data = $this->createTestData();

        $otherGuardian = User::create([
            'username' => 'other_guardian',
            'password' => bcrypt('password'),
            'full_name' => 'Other Guardian',
            'user_type' => 'guardian',
            'classroom_id' => $data['classroom']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherGuardian, 'sanctum')
            ->postJson("/api/guardian/support-plans/{$data['plan']->id}/review", [
                'comment' => 'Trying to access someone else plan',
            ]);

        $response->assertStatus(403);
    }
}
