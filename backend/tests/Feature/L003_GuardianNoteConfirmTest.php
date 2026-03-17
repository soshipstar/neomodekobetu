<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class L003_GuardianNoteConfirmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guardian can confirm a note.
     * POST /api/guardian/notes/{id}/confirm -> 200
     * Assert DB: guardian_confirmed = true, guardian_confirmed_at is not null
     */
    public function test_guardian_can_confirm_note(): void
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

        $note = IntegratedNote::create([
            'student_id' => $student->id,
            'integrated_content' => 'Test note content',
            'is_sent' => true,
            'sent_at' => now(),
            'guardian_confirmed' => false,
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $note->refresh();
        $this->assertTrue($note->guardian_confirmed);
        $this->assertNotNull($note->guardian_confirmed_at);
    }

    /**
     * Test guardian cannot confirm note belonging to another student.
     */
    public function test_guardian_cannot_confirm_other_students_note(): void
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

        $note = IntegratedNote::create([
            'student_id' => $student->id,
            'integrated_content' => 'Test note content',
            'is_sent' => true,
            'sent_at' => now(),
            'guardian_confirmed' => false,
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(403);
    }

    /**
     * Test guardian cannot confirm unsent note.
     */
    public function test_guardian_cannot_confirm_unsent_note(): void
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

        $note = IntegratedNote::create([
            'student_id' => $student->id,
            'integrated_content' => 'Test note content',
            'is_sent' => false,
            'guardian_confirmed' => false,
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(422);
    }

    /**
     * Test guardian cannot double-confirm.
     */
    public function test_guardian_cannot_double_confirm(): void
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

        $note = IntegratedNote::create([
            'student_id' => $student->id,
            'integrated_content' => 'Test note content',
            'is_sent' => true,
            'sent_at' => now(),
            'guardian_confirmed' => true,
            'guardian_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/guardian/notes/{$note->id}/confirm");

        $response->assertStatus(422);
    }
}
