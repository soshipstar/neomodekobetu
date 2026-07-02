<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LR-001: 連絡帳保存の二重送信耐性 (冪等性)
 *
 * 差分カテゴリ: logic
 * 背景: POST /api/staff/renrakucho で同一 (record_date, staff_id, activity_name)
 *       が二重送信されると、daily_records のユニーク制約違反で
 *       UniqueConstraintViolationException が発生し 500 エラーを返していた。
 *       本番 error_logs に大量に記録される問題があった。
 *       store() を try/catch でラップし、二重送信時は既存レコードを 200 で返す。
 */
class L005_RenrakuchoStoreIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '教室A',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $staff = User::create([
            'username'     => 'staff_renraku_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
        ]);

        return [$staff, $student];
    }

    private function payload(int $studentId, string $activityName = '朝の活動'): array
    {
        return [
            'record_date'     => '2026-05-08',
            'activity_name'   => $activityName,
            'common_activity' => '本日の共通活動の内容です。',
            'students'        => [
                [
                    'id'                     => $studentId,
                    'health_life'            => '元気でした。',
                    'language_communication' => 'よく話していました。',
                    'notes'                  => 'メモ。',
                ],
            ],
        ];
    }

    public function test_first_store_creates_record_with_201(): void
    {
        [$staff, $student] = $this->setupContext();

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student->id));

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertEquals(1, DailyRecord::count());
        $this->assertEquals(1, StudentRecord::count());
    }

    public function test_duplicate_store_returns_200_with_duplicated_flag(): void
    {
        [$staff, $student] = $this->setupContext();
        $payload = $this->payload($student->id);

        // 1回目: 201 Created
        $first = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $payload);
        $first->assertStatus(201);

        // 2回目: 二重送信 → 500 ではなく 200 + duplicated:true
        $second = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $payload);

        $second->assertStatus(200);
        $second->assertJsonPath('success', true);
        $second->assertJsonPath('duplicated', true);

        // DB 上の daily_records は 1 件のまま
        $this->assertEquals(1, DailyRecord::count());
        // 1回目のレコードIDと一致
        $this->assertEquals(
            $first->json('data.id'),
            $second->json('data.id'),
        );
    }

    public function test_duplicate_store_does_not_duplicate_student_records(): void
    {
        [$staff, $student] = $this->setupContext();
        $payload = $this->payload($student->id);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $payload)
            ->assertStatus(201);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $payload)
            ->assertStatus(200);

        // student_records は二重に作られない
        $this->assertEquals(1, StudentRecord::count());
    }

    public function test_different_activity_name_creates_separate_record(): void
    {
        [$staff, $student] = $this->setupContext();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student->id, '朝の活動'))
            ->assertStatus(201);

        // 別の activity_name は別レコードとして 201 Created
        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student->id, '午後の活動'))
            ->assertStatus(201);

        $this->assertEquals(2, DailyRecord::count());
    }

    /**
     * バグ報告: 連絡帳を送信した後、同じ活動名のまま「別の生徒」を追加保存すると、
     * ユニーク制約違反のフォールバックが既存レコードを返すだけで新規生徒の記録を
     * 保存せず、"保存しました" と出るのに記録が出てこないデータ欠落が起きていた。
     * → フォールバックで生徒記録をマージし、欠落しないことを保証する。
     */
    public function test_same_activity_with_new_student_merges_records(): void
    {
        [$staff, $student] = $this->setupContext();
        $student2 = Student::create([
            'student_name' => '生徒B',
            'classroom_id' => $student->classroom_id,
        ]);

        // 1回目: 生徒A で活動作成 (201)
        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student->id))
            ->assertStatus(201);

        // 2回目: 同じ (record_date, staff, activity_name) のまま「別の生徒B」を保存
        $second = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student2->id));

        $second->assertStatus(200);
        $second->assertJsonPath('success', true);

        // daily_records は 1 件に統合される
        $this->assertEquals(1, DailyRecord::count());
        // 生徒A・生徒B の両方の記録が残る (生徒B が欠落しない)
        $this->assertEquals(2, StudentRecord::count());
        $this->assertDatabaseHas('student_records', [
            'daily_record_id' => $second->json('data.id'),
            'student_id'      => $student2->id,
        ]);
    }
}
