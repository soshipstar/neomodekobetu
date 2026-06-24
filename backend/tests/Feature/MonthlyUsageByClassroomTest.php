<?php

namespace Tests\Feature;

use App\Models\AbsenceResponseRecord;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 月次利用日数(連絡帳ベース) ＋ 欠席時対応加算 集計
 * GET /api/admin/monthly-usage?classroom_id&year&month
 *
 * 差分カテゴリ: api(logic)
 * - 利用日数 = student_records を持つ daily_records.record_date の異なる日付数
 *   (同一児童が同日複数記録でも 1 とカウント)
 * - 欠席時対応加算 = absence_response_records の件数。算定回数 = min(件数, 4)
 * - 施設フラグ OFF のとき算定回数は 0、レスポンスの absence_addition_enabled=false
 * - 月またぎ・他施設は混入しない / master 以外の越境アクセスは 403
 */
class MonthlyUsageByClassroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function admin(Classroom $room, bool $isMaster = false): User
    {
        return User::create([
            'username' => 'adm_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '管理者',
            'user_type' => 'admin', 'is_master' => $isMaster, 'classroom_id' => $room->id, 'is_active' => true,
        ]);
    }

    private function student(Classroom $room, string $name): Student
    {
        return Student::create([
            'student_name' => $name, 'classroom_id' => $room->id,
            'status' => 'active', 'is_active' => true,
        ]);
    }

    /** 連絡帳の利用日(= DailyRecord + StudentRecord) を1件作る */
    private function addUsage(Classroom $room, User $staff, Student $student, string $date): void
    {
        // daily_records は (record_date, staff_id, activity_name) が一意。
        // 同日に複数記録を作るケースを検証するため活動名は毎回ユニークにする。
        $record = DailyRecord::create([
            'record_date' => $date, 'staff_id' => $staff->id, 'classroom_id' => $room->id,
            'activity_name' => '活動' . uniqid(), 'common_activity' => '共通活動',
        ]);
        StudentRecord::create([
            'daily_record_id' => $record->id, 'student_id' => $student->id, 'notes' => 'メモ',
        ]);
    }

    private function addAbsenceResponse(Classroom $room, User $staff, Student $student, string $date): void
    {
        AbsenceResponseRecord::create([
            'student_id' => $student->id, 'classroom_id' => $room->id, 'absence_date' => $date,
            'response_content' => '電話にて状況確認', 'staff_id' => $staff->id,
        ]);
    }

    public function test_counts_usage_days_and_absence_addition_per_student(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->admin($room);
        $s1 = $this->student($room, '児A');
        $s2 = $this->student($room, '児B');

        // 別企業・別施設(混入してはいけない)
        $companyB = Company::create(['name' => '企業B']);
        $roomB = Classroom::create(['classroom_name' => '事業所B', 'company_id' => $companyB->id, 'is_active' => true]);
        $adminB = $this->admin($roomB);
        $sB = $this->student($roomB, '他児');

        // S1: 5/10 を2回(同日複数記録)→ distinct 1、5/12 → distinct 2
        $this->addUsage($room, $admin, $s1, '2026-05-10');
        $this->addUsage($room, $admin, $s1, '2026-05-10');
        $this->addUsage($room, $admin, $s1, '2026-05-12');
        // S2: 5/15 → 1、6/01(翌月)→ 5月には数えない
        $this->addUsage($room, $admin, $s2, '2026-05-15');
        $this->addUsage($room, $admin, $s2, '2026-06-01');
        // 他施設の利用(混入しない)
        $this->addUsage($roomB, $adminB, $sB, '2026-05-10');

        // 加算: S1 は5月に5件 → billable=min(5,4)=4、S2 は1件、6月分・他施設分は除外
        foreach (['2026-05-01', '2026-05-05', '2026-05-08', '2026-05-20', '2026-05-25'] as $d) {
            $this->addAbsenceResponse($room, $admin, $s1, $d);
        }
        $this->addAbsenceResponse($room, $admin, $s2, '2026-05-03');
        $this->addAbsenceResponse($room, $admin, $s1, '2026-06-03'); // 翌月 → 除外
        $this->addAbsenceResponse($roomB, $adminB, $sB, '2026-05-04'); // 他施設 → 除外

        $res = $this->actingAs($admin, 'sanctum')->getJson(
            "/api/admin/monthly-usage?classroom_id={$room->id}&year=2026&month=5"
        );
        $res->assertStatus(200);
        $res->assertJsonPath('data.absence_addition_enabled', true);

        $rows = collect($res->json('data.rows'));
        $r1 = $rows->firstWhere('student_id', $s1->id);
        $r2 = $rows->firstWhere('student_id', $s2->id);

        $this->assertSame(2, $r1['usage_days']);
        $this->assertSame(5, $r1['addition_records']);
        $this->assertSame(4, $r1['addition_billable']); // 上限4

        $this->assertSame(1, $r2['usage_days']);
        $this->assertSame(1, $r2['addition_records']);
        $this->assertSame(1, $r2['addition_billable']);

        // 他施設の児童は出ない
        $this->assertNull($rows->firstWhere('student_id', $sB->id));

        // 合計
        $this->assertSame(3, $res->json('data.totals.usage_days'));
        $this->assertSame(6, $res->json('data.totals.addition_records'));
        $this->assertSame(5, $res->json('data.totals.addition_billable'));
    }

    public function test_addition_columns_zero_when_classroom_flag_off(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true,
            'absence_addition_enabled' => false,
        ]);
        $admin = $this->admin($room);
        $s1 = $this->student($room, '児A');

        $this->addUsage($room, $admin, $s1, '2026-05-10');
        $this->addAbsenceResponse($room, $admin, $s1, '2026-05-01');
        $this->addAbsenceResponse($room, $admin, $s1, '2026-05-02');

        $res = $this->actingAs($admin, 'sanctum')->getJson(
            "/api/admin/monthly-usage?classroom_id={$room->id}&year=2026&month=5"
        );
        $res->assertStatus(200);
        $res->assertJsonPath('data.absence_addition_enabled', false);

        $row = collect($res->json('data.rows'))->firstWhere('student_id', $s1->id);
        $this->assertSame(1, $row['usage_days']);      // 利用日数は数える
        $this->assertSame(0, $row['addition_records']); // 加算は 0
        $this->assertSame(0, $row['addition_billable']);
        $this->assertSame(0, $res->json('data.totals.addition_billable'));
    }

    public function test_normal_admin_cannot_access_other_classroom(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $admin = $this->admin($room); // 非マスター

        $companyB = Company::create(['name' => '企業B']);
        $roomB = Classroom::create(['classroom_name' => '事業所B', 'company_id' => $companyB->id, 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')->getJson(
            "/api/admin/monthly-usage?classroom_id={$roomB->id}&year=2026&month=5"
        )->assertStatus(403);
    }
}
