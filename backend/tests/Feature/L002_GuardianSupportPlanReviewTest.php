<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class L002_GuardianSupportPlanReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guardian can submit a review comment on a support plan.
     * POST with comment -> assert guardian_review_comment saved
     */
    public function test_guardian_can_submit_review_comment(): void
    {
        $classroom = Classroom::create([
            'name' => 'Test Classroom',
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

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/support-plans/{$plan->id}/review", [
                'comment' => 'Please adjust the goal for domain 2.',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $plan->refresh();
        $this->assertEquals('Please adjust the goal for domain 2.', $plan->guardian_review_comment);
        $this->assertNotNull($plan->guardian_review_comment_at);
    }

    /**
     * Test guardian can approve a plan with empty comment (confirm_review).
     * POST with empty comment (approval) -> assert guardian_review_comment = ""
     */
    public function test_guardian_can_approve_with_empty_comment(): void
    {
        $classroom = Classroom::create([
            'name' => 'Test Classroom',
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

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/support-plans/{$plan->id}/review", [
                'comment' => '',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $plan->refresh();
        $this->assertEquals('', $plan->guardian_review_comment);
        $this->assertNotNull($plan->guardian_review_comment_at);
    }

    /**
     * Test guardian cannot review another student's plan.
     */
    public function test_guardian_cannot_review_other_students_plan(): void
    {
        $classroom = Classroom::create([
            'name' => 'Test Classroom',
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

        $otherGuardian = User::create([
            'username' => 'other_guardian',
            'password' => bcrypt('password'),
            'full_name' => 'Other Guardian',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Other Student',
            'guardian_id' => $otherGuardian->id,
            'is_active' => true,
        ]);

        $plan = IndividualSupportPlan::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'student_name' => 'Other Student',
            'created_date' => '2025-08-17',
            'status' => 'published',
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/support-plans/{$plan->id}/review", [
                'comment' => 'Trying to access someone else plan',
            ]);

        $response->assertStatus(403);
    }
}
