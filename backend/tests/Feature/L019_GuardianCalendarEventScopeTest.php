<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L-019: 保護者カレンダーのイベント/祝日は在籍児童の教室に必ずスコープする。
 *
 * 背景(不具合): Guardian/DashboardController::buildCalendarData が
 * `if (! empty($classroomIds))` でしか絞り込んでおらず、児童未紐付け等で classroomIds が
 * 空になる保護者には全教室のイベント(デモ教室「かけはし」含む)が漏れて表示されていた。
 * 常に classroomIds で絞り込み(空なら0件)に修正。
 *
 * 差分カテゴリ: logic
 */
class L019_GuardianCalendarEventScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function makeEvent(int $classroomId, int $createdBy, string $date, string $name): void
    {
        DB::table('events')->insert([
            'event_date' => $date, 'event_name' => $name, 'classroom_id' => $classroomId,
            'created_by' => $createdBy, 'target_audience' => 'all',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_guardian_sees_only_own_classroom_events_and_none_when_no_students(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => 'デモ教室', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_cal_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);

        $this->makeEvent($roomA->id, $staff->id, '2030-01-15', '自教室イベント');
        $this->makeEvent($roomB->id, $staff->id, '2030-01-20', 'デモ教室イベント');

        // 自分の児童が教室Aに在籍する保護者
        $guardianA = User::create([
            'username' => 'gA_cal_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者A',
            'user_type' => 'guardian', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);
        Student::create([
            'student_name' => '児A', 'classroom_id' => $roomA->id, 'guardian_id' => $guardianA->id,
            'status' => 'active', 'is_active' => true,
        ]);

        $resA = $this->actingAs($guardianA, 'sanctum')->getJson('/api/guardian/dashboard?year=2030&month=1');
        $resA->assertStatus(200);
        $eventsA = $resA->json('data.calendar.events') ?? [];
        $this->assertArrayHasKey('2030-01-15', $eventsA);      // 自教室は見える
        $this->assertArrayNotHasKey('2030-01-20', $eventsA);   // 別(デモ)教室は見えない

        // 児童が紐付いていない保護者 → classroomIds が空 → どのイベントも見えない
        $guardianNone = User::create([
            'username' => 'gN_cal_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者N',
            'user_type' => 'guardian', 'classroom_id' => null, 'is_active' => true,
        ]);
        $resN = $this->actingAs($guardianNone, 'sanctum')->getJson('/api/guardian/dashboard?year=2030&month=1');
        $resN->assertStatus(200);
        $eventsN = $resN->json('data.calendar.events') ?? [];
        $this->assertArrayNotHasKey('2030-01-15', $eventsN);
        $this->assertArrayNotHasKey('2030-01-20', $eventsN);
    }
}
