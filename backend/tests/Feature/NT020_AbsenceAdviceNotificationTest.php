<?php

namespace Tests\Feature;

use App\Models\AbsenceNotification;
use App\Models\Classroom;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LR-007 Phase 4 (logic): スタッフがアドバイスを保存したとき、保護者に通知を送る
 *
 * 差分カテゴリ: logic
 * 背景: 淡田さんからの要望で、スタッフが欠席連絡にアドバイスを記入したら
 *       保護者に通知が届くようにする。
 *  - 新規/更新時のみ通知 (内容が同じなら冪等的に通知しない)
 *  - クリア (空文字保存) では通知しない
 *  - 通知 type は 'absence' (notification_preferences の "欠席連絡" カテゴリで制御)
 */
class NT020_AbsenceAdviceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $guardian = User::create([
            'username'  => 'g_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '保護者A',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
            'guardian_id'  => $guardian->id,
        ]);
        $staff = User::create([
            'username'     => 's_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $absence = AbsenceNotification::create([
            'student_id'   => $student->id,
            'absence_date' => '2026-05-09',
            'reason'       => '発熱のため',
        ]);

        return [$staff, $guardian, $student, $absence];
    }

    public function test_saving_advice_creates_notification_for_guardian(): void
    {
        [$staff, $guardian, , $absence] = $this->setupContext();

        $before = Notification::where('user_id', $guardian->id)->count();

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => '十分な水分補給と休養をお願いします。',
            ]);

        $response->assertStatus(200);

        $after = Notification::where('user_id', $guardian->id)->count();
        $this->assertSame($before + 1, $after);

        $latest = Notification::where('user_id', $guardian->id)->latest('id')->first();
        $this->assertSame('absence', $latest->type);
        $this->assertStringContainsString('アドバイス', $latest->title);
    }

    public function test_clearing_advice_does_not_notify(): void
    {
        [$staff, $guardian, , $absence] = $this->setupContext();
        // 既存アドバイスを設定
        $absence->update(['advice' => 'よく休んでください', 'advice_by' => $staff->id, 'advice_at' => now()]);

        $before = Notification::where('user_id', $guardian->id)->count();

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => '',
            ]);

        $response->assertStatus(200);
        $this->assertSame($before, Notification::where('user_id', $guardian->id)->count());
    }

    public function test_saving_same_advice_does_not_double_notify(): void
    {
        [$staff, $guardian, , $absence] = $this->setupContext();

        $advice = '休養を取ってください。';
        $absence->update(['advice' => $advice, 'advice_by' => $staff->id, 'advice_at' => now()]);

        $before = Notification::where('user_id', $guardian->id)->count();

        // 同じ内容を再送 → 重複通知しない
        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => $advice,
            ]);

        $response->assertStatus(200);
        $this->assertSame($before, Notification::where('user_id', $guardian->id)->count());
    }

    public function test_advice_with_no_guardian_does_not_throw(): void
    {
        [$staff] = $this->setupContext();

        // 保護者なしの生徒 (孤立生徒)
        $classroom = Classroom::first();
        $orphanStudent = Student::create([
            'student_name' => '孤立生徒',
            'classroom_id' => $classroom->id,
            'guardian_id'  => null,
        ]);
        $absence = AbsenceNotification::create([
            'student_id'   => $orphanStudent->id,
            'absence_date' => '2026-05-09',
            'reason'       => '私用',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/absence/{$absence->id}/advice", [
                'advice' => 'お大事に',
            ]);

        // 保護者がいなくても 200 で正常終了 (通知なし)
        $response->assertStatus(200);
    }
}
