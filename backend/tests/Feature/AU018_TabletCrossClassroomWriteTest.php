<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S-008 / S-009: タブレットの活動記録・連絡帳の書き込みは、所属教室の生徒/記録に限定する。
 *
 * 背景(不具合/越境): Tablet/TabletController の
 *  - storeActivity   : student_ids.* が `exists:students,id` のみで他教室の生徒IDを混入できた。
 *  - storeRenrakucho : student_id / daily_record_id が `exists` のみで他教室の生徒・記録に書けた。
 * いずれも $user->classroom_id に属することを必須にして 422 を返すよう修正。
 *
 * 差分カテゴリ: auth
 */
class AU018_TabletCrossClassroomWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_tablet_cannot_write_activity_or_renrakucho_for_other_classroom(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => '教室B', 'company_id' => $company->id, 'is_active' => true]);

        // タブレットユーザは教室Aに所属
        $tablet = User::create([
            'username' => 'tablet_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'タブレットA',
            'user_type' => 'tablet', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);

        $studentA = Student::create([
            'student_name' => '児A', 'classroom_id' => $roomA->id, 'status' => 'active', 'is_active' => true,
        ]);
        $studentB = Student::create([
            'student_name' => '児B', 'classroom_id' => $roomB->id, 'status' => 'active', 'is_active' => true,
        ]);

        // --- S-008: storeActivity ---
        // 他教室(B)の生徒を含む → 422
        $crossActivity = $this->actingAs($tablet, 'sanctum')->postJson('/api/tablet/activity-records', [
            'record_date' => '2030-02-01',
            'activity_name' => '外遊び',
            'student_ids' => [$studentB->id],
        ]);
        $crossActivity->assertStatus(422);

        // 自教室(A)の生徒のみ → 201
        $okActivity = $this->actingAs($tablet, 'sanctum')->postJson('/api/tablet/activity-records', [
            'record_date' => '2030-02-01',
            'activity_name' => '外遊び',
            'student_ids' => [$studentA->id],
        ]);
        $okActivity->assertStatus(201);

        // --- S-009: storeRenrakucho ---
        // 他教室(B)の生徒・記録
        $recordB = DailyRecord::create([
            'classroom_id' => $roomB->id, 'record_date' => '2030-02-02',
            'activity_name' => 'B活動', 'common_activity' => 'B活動', 'staff_id' => $tablet->id,
        ]);
        $crossNote = $this->actingAs($tablet, 'sanctum')->postJson('/api/tablet/renrakucho', [
            'student_id' => $studentB->id,
            'daily_record_id' => $recordB->id,
            'integrated_content' => '他教室への書き込み',
        ]);
        $crossNote->assertStatus(422);

        // 自教室(A)の生徒・記録 → 200
        $recordA = DailyRecord::create([
            'classroom_id' => $roomA->id, 'record_date' => '2030-02-02',
            'activity_name' => 'A活動', 'common_activity' => 'A活動', 'staff_id' => $tablet->id,
        ]);
        $okNote = $this->actingAs($tablet, 'sanctum')->postJson('/api/tablet/renrakucho', [
            'student_id' => $studentA->id,
            'daily_record_id' => $recordA->id,
            'integrated_content' => '自教室への書き込み',
        ]);
        $okNote->assertStatus(200);
    }
}
