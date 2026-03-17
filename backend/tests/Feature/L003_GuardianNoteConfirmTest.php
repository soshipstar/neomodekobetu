<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class L003_GuardianNoteConfirmTest extends TestCase
{
    use DatabaseMigrations;

    private function createTestData(): array
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $staff = User::create([
            'username' => 'staff_test',
            'password' => bcrypt('password'),
            'full_name' => 'Test Staff',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
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

        $dailyRecord = DailyRecord::create([
            'record_date' => now()->toDateString(),
            'staff_id' => $staff->id,
            'classroom_id' => $classroom->id,
            'activity_name' => 'Test Activity',
            'common_activity' => 'Test common activity',
        ]);

        return compact('classroom', 'staff', 'guardian', 'student', 'dailyRecord');
    }

    public function test_guardian_can_confirm_note(): void
    {
        $data = $this->createTestData();

        $note = IntegratedNote::create([
            'daily_record_id' => $data['dailyRecord']->id,
            'student_id' => $data['student']->id,
            'integrated_content' => 'Test note content',
            'is_sent' => true,
            'sent_at' => now(),
            'guardian_confirmed' => false,
        ]);

        $response = $this->actingAs($data['guardian'], 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('integrated_notes', [
            'id' => $note->id,
            'guardian_confirmed' => true,
        ]);
    }

    public function test_guardian_cannot_confirm_other_students_note(): void
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

        $otherStudent = Student::create([
            'classroom_id' => $data['classroom']->id,
            'student_name' => 'Other Student',
            'guardian_id' => $otherGuardian->id,
            'is_active' => true,
        ]);

        $note = IntegratedNote::create([
            'daily_record_id' => $data['dailyRecord']->id,
            'student_id' => $otherStudent->id,
            'integrated_content' => 'Test note content',
            'is_sent' => true,
            'sent_at' => now(),
            'guardian_confirmed' => false,
        ]);

        $response = $this->actingAs($data['guardian'], 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(403);
    }

    public function test_guardian_cannot_confirm_unsent_note(): void
    {
        $data = $this->createTestData();

        $note = IntegratedNote::create([
            'daily_record_id' => $data['dailyRecord']->id,
            'student_id' => $data['student']->id,
            'integrated_content' => 'Test note content',
            'is_sent' => false,
            'guardian_confirmed' => false,
        ]);

        $response = $this->actingAs($data['guardian'], 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(422);
    }

    public function test_guardian_cannot_double_confirm(): void
    {
        $data = $this->createTestData();

        $note = IntegratedNote::create([
            'daily_record_id' => $data['dailyRecord']->id,
            'student_id' => $data['student']->id,
            'integrated_content' => 'Test note content',
            'is_sent' => true,
            'sent_at' => now(),
            'guardian_confirmed' => true,
            'guardian_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($data['guardian'], 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(422);
    }
}
