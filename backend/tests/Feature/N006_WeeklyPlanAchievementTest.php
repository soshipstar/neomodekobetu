<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyPlan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class N006_WeeklyPlanAchievementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_update_accepts_achievement_fields(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'Test', 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff1', 'password' => bcrypt('p'), 'full_name' => 'Staff',
            'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'classroom_id' => $classroom->id, 'student_name' => 'Student', 'is_active' => true,
        ]);

        $plan = WeeklyPlan::create([
            'classroom_id' => $classroom->id, 'student_id' => $student->id,
            'week_start_date' => '2026-03-16', 'created_by' => $staff->id,
            'weekly_goal' => '目標A',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/weekly-plans/{$plan->id}", [
                'weekly_goal_achievement' => 5,
                'shared_goal_achievement' => 4,
                'must_do_achievement' => 1,
                'overall_comment' => 'よく頑張りました',
                'evaluated_at' => '2026-03-22',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('weekly_plans', [
            'id' => $plan->id,
            'weekly_goal_achievement' => 5,
            'shared_goal_achievement' => 4,
            'overall_comment' => 'よく頑張りました',
        ]);
    }

    public function test_update_accepts_goal_and_plan_fields(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'Test', 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff2', 'password' => bcrypt('p'), 'full_name' => 'Staff',
            'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);

        $plan = WeeklyPlan::create([
            'classroom_id' => $classroom->id,
            'week_start_date' => '2026-03-16', 'created_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/weekly-plans/{$plan->id}", [
                'weekly_goal' => '新しい目標',
                'shared_goal' => '共通目標',
                'must_do' => 'やるべきこと',
                'should_do' => 'やったほうがいい',
                'want_to_do' => 'やりたいこと',
                'plan_data' => ['day_0' => 'Monday plan'],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('weekly_plans', [
            'id' => $plan->id,
            'weekly_goal' => '新しい目標',
            'must_do' => 'やるべきこと',
        ]);
    }
}
