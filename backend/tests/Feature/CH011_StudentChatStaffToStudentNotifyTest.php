<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CH011: スタッフ→生徒チャット送信時に生徒本人にも通知が飛ぶか検証
 *
 * 差分カテゴリ: logic
 * 背景: 従来は保護者にしか通知が送られず、生徒本人の User アカウントに
 *       通知が届かなかった。生徒ユーザーは `users.username='student_{studentId}'`
 *       パターンで存在するため、そこを経由して通知対象に含める。
 */
class CH011_StudentChatStaffToStudentNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $staff = User::create([
            'username' => 'staff_ch011',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $guardian = User::create([
            'username' => 'guardian_ch011',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'classroom_id' => $classroom->id,
            'guardian_id' => $guardian->id,
            'student_name' => 'テスト生徒',
            'username' => null,
            'password_hash' => null,
            'is_active' => true,
            'status' => 'active',
        ]);

        // 生徒の User アカウント (ログイン用)
        $studentUser = User::create([
            'username' => 'student_' . $student->id,
            'password' => bcrypt('pass'),
            'full_name' => $student->student_name,
            'user_type' => 'student',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $room = StudentChatRoom::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
        ]);

        return compact('staff', 'guardian', 'student', 'studentUser', 'room');
    }

    public function test_staff_sending_message_notifies_student_user(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['staff'], 'sanctum')
            ->postJson("/api/staff/student-chats/{$f['room']->id}/messages", [
                'message' => 'こんにちは',
            ])
            ->assertStatus(201);

        // 生徒本人にも通知が作成されていること
        $this->assertDatabaseHas('notifications', [
            'user_id' => $f['studentUser']->id,
            'type' => 'chat_message',
            'title' => '新着メッセージ',
        ]);

        // 保護者にも通知が作成されていること (既存仕様を壊していない)
        $this->assertDatabaseHas('notifications', [
            'user_id' => $f['guardian']->id,
            'type' => 'chat_message',
            'title' => '新着メッセージ（生徒チャット）',
        ]);
    }

    public function test_student_user_gets_notification_even_when_no_guardian(): void
    {
        $f = $this->fixture();
        // 保護者リンクを外す
        $f['student']->update(['guardian_id' => null]);

        $this->actingAs($f['staff'], 'sanctum')
            ->postJson("/api/staff/student-chats/{$f['room']->id}/messages", [
                'message' => 'おはよう',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $f['studentUser']->id,
            'type' => 'chat_message',
        ]);
        // 保護者宛の通知は作られない
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $f['guardian']->id,
        ]);
    }

    public function test_inactive_student_user_is_not_notified(): void
    {
        $f = $this->fixture();
        $f['studentUser']->update(['is_active' => false]);

        $this->actingAs($f['staff'], 'sanctum')
            ->postJson("/api/staff/student-chats/{$f['room']->id}/messages", [
                'message' => 'テスト',
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $f['studentUser']->id,
        ]);
    }
}
