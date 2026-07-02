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
 * LR-001: 連絡帳(活動記録)の保存ポリシー
 *
 * 差分カテゴリ: schema / logic
 * 方針(2026-07): 同じ支援案(=同一活動名)で同日に2件以上の活動記録を作りたい
 *       運用があるため、daily_records の (record_date, staff_id, activity_name)
 *       ユニーク制約を撤廃した。保存は常に新規レコードとして作成される
 *       (旧「二重送信は既存を返す(duplicated)」挙動は撤廃)。
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

    /**
     * 同じ支援案(=同一 record_date, staff, activity_name)で同日に2件目を保存しても、
     * ブロックやマージをせず「別レコード」として 201 で作成される。
     */
    public function test_same_activity_name_same_day_creates_separate_records(): void
    {
        [$staff, $student] = $this->setupContext();
        $payload = $this->payload($student->id);

        $first = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $payload);
        $first->assertStatus(201);

        $second = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $payload);
        $second->assertStatus(201);

        // 2件の別レコードとして作成される
        $this->assertEquals(2, DailyRecord::count());
        $this->assertNotEquals($first->json('data.id'), $second->json('data.id'));
        // それぞれに生徒記録が付く
        $this->assertEquals(2, StudentRecord::count());
    }

    public function test_different_activity_name_creates_separate_record(): void
    {
        [$staff, $student] = $this->setupContext();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student->id, '朝の活動'))
            ->assertStatus(201);

        // 別の activity_name も別レコードとして 201 Created
        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho', $this->payload($student->id, '午後の活動'))
            ->assertStatus(201);

        $this->assertEquals(2, DailyRecord::count());
    }
}
