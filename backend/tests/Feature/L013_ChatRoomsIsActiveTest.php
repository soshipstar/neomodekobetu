<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-013: 一斉送信の「在籍中の保護者のみ」絞り込み用に、スタッフのチャットルーム一覧が
 * 各児童の is_active を返すことを保証する。
 *
 * FE の BroadcastModal は room.student.is_active で在籍中のみを選択するため、
 * このフィールドが欠けると絞り込みが機能しなくなる(回帰防止)。
 *
 * 差分カテゴリ: screen
 */
class L013_ChatRoomsIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_rooms_response_includes_student_is_active(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_active_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'スタッフA', 'user_type' => 'staff', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_active_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => '保護者A', 'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);

        $activeStudent = Student::create([
            'student_name' => '在籍児', 'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id,
            'status' => 'active', 'is_active' => true,
        ]);
        $withdrawnStudent = Student::create([
            'student_name' => '退所児', 'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id,
            'status' => 'withdrawn', 'is_active' => false,
        ]);
        ChatRoom::create(['student_id' => $activeStudent->id, 'guardian_id' => $guardian->id]);
        ChatRoom::create(['student_id' => $withdrawnStudent->id, 'guardian_id' => $guardian->id]);

        $res = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/chat/rooms');
        $res->assertStatus(200);

        $byStudent = collect($res->json('data'))->keyBy('student_id');

        $this->assertArrayHasKey('is_active', $byStudent[$activeStudent->id]['student']);
        $this->assertTrue($byStudent[$activeStudent->id]['student']['is_active']);
        $this->assertFalse($byStudent[$withdrawnStudent->id]['student']['is_active']);
    }
}
