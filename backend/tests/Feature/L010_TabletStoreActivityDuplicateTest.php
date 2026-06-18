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
 * storeActivity の重複登録ガード。
 *
 * 背景(本番エラー 23505): daily_records は (record_date, staff_id, activity_name) に
 * unique 制約があるが storeActivity は素の create() のため、同日同名の活動を再登録
 * すると SQLSTATE 23505 で 500 になっていた(タブレットの二重送信/リトライが主因)。
 * updateActivity と同様に事前チェックして 422 を返すよう修正。
 *
 * 差分カテゴリ: logic
 */
class L010_TabletStoreActivityDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_duplicate_activity_returns_422_not_500_and_does_not_insert_twice(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $tablet = User::create([
            'username' => 'tablet_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'タブレットA',
            'user_type' => 'tablet', 'classroom_id' => $room->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '児A', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true,
        ]);

        $payload = [
            'record_date'   => '2030-03-01',
            'activity_name' => '①ストレッチ　②協力して渡りきろう',
            'student_ids'   => [$student->id],
        ];

        // 1回目: 201 で作成
        $this->actingAs($tablet, 'sanctum')
            ->postJson('/api/tablet/activity-records', $payload)
            ->assertStatus(201);

        // 2回目(同一 record_date + staff_id + activity_name): 500 ではなく 422
        $second = $this->actingAs($tablet, 'sanctum')
            ->postJson('/api/tablet/activity-records', $payload);
        $second->assertStatus(422);
        $second->assertJson(['success' => false]);
        $second->assertJsonStructure(['success', 'message', 'conflict_activity_id']);

        // daily_records は1件のまま(二重 INSERT されていない)
        $count = DailyRecord::query()
            ->where('record_date', '2030-03-01')
            ->where('staff_id', $tablet->id)
            ->where('activity_name', '①ストレッチ　②協力して渡りきろう')
            ->count();
        $this->assertSame(1, $count);
    }
}
