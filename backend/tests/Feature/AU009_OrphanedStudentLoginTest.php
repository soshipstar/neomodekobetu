<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AU009_OrphanedStudentLoginTest extends TestCase
{
    use DatabaseMigrations;

    public function test_orphaned_student_gets_token(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'Test', 'is_active' => true]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Orphan Student',
            'username' => 'orphan_student',
            'password_hash' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'orphan_student',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotNull($data['token'], 'Orphaned student must receive a non-null token');
        $this->assertEquals('student_only', $data['login_type']);
    }

    public function test_student_with_guardian_gets_token(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'Test', 'is_active' => true]);

        $guardian = User::create([
            'username' => 'guardian1',
            'password' => bcrypt('pass'),
            'full_name' => 'Guardian',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'Linked Student',
            'username' => 'linked_student',
            'password_hash' => bcrypt('password123'),
            'guardian_id' => $guardian->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'linked_student',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.token'));
        $this->assertEquals('student', $response->json('data.login_type'));
    }
}
