<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\MeetingRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LR-002: ダッシュボードカレンダーの面談セクションに時刻を表示する
 *
 * 差分カテゴリ: logic + screen
 * 背景: GET /api/staff/dashboard/calendar の meeting_dates レスポンスが
 *       confirmed_date から日付部分しか返さず、フロント側で時刻を表示できなかった。
 *       confirmed_date は timestampTz なので時刻情報を保持しているにもかかわらず
 *       Carbon::parse($m->confirmed_date)->toDateString() で時刻を切り捨てていた。
 */
class L007_DashboardMeetingTimeTest extends TestCase
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
            'username'     => 'staff_dash_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $guardian = User::create([
            'username'  => 'guardian_dash_' . uniqid(),
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

        return [$staff, $student, $guardian, $classroom];
    }

    public function test_calendar_returns_time_for_confirmed_meeting(): void
    {
        [$staff, $student, $guardian, $classroom] = $this->setupContext();

        // 当月内に確定済み面談を1件作成 (JST 14:30)
        $confirmed = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(13)->setTime(14, 30, 0);
        MeetingRequest::create([
            'classroom_id'    => $classroom->id,
            'student_id'      => $student->id,
            'guardian_id'     => $guardian->id,
            'purpose'         => '個別支援計画更新のため',
            'candidate_dates' => [],
            'confirmed_date'  => $confirmed,
            'status'          => 'confirmed',
            'confirmed_by'    => 'staff',
            'confirmed_at'    => $confirmed,
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/dashboard/calendar');

        $response->assertStatus(200);
        $meetings = $response->json('data.meeting_dates');
        $this->assertIsArray($meetings);
        $this->assertCount(1, $meetings);
        $this->assertSame($confirmed->toDateString(), $meetings[0]['date']);
        $this->assertSame('14:30', $meetings[0]['time']);
        $this->assertSame('生徒A', $meetings[0]['student_name']);
        $this->assertSame('保護者A', $meetings[0]['guardian_name']);
        $this->assertSame('個別支援計画更新のため', $meetings[0]['purpose']);
    }

    public function test_pending_meeting_is_not_returned(): void
    {
        [$staff, $student, $guardian, $classroom] = $this->setupContext();

        MeetingRequest::create([
            'classroom_id'    => $classroom->id,
            'student_id'      => $student->id,
            'guardian_id'     => $guardian->id,
            'purpose'         => '保留中の面談',
            'candidate_dates' => [],
            'confirmed_date'  => null,
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/dashboard/calendar');

        $response->assertStatus(200);
        $meetings = $response->json('data.meeting_dates');
        $this->assertCount(0, $meetings);
    }

    public function test_multiple_meetings_have_independent_times(): void
    {
        [$staff, $student, $guardian, $classroom] = $this->setupContext();

        $morning   = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(5)->setTime(9, 0, 0);
        $afternoon = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(10)->setTime(15, 45, 0);

        foreach ([$morning, $afternoon] as $i => $when) {
            MeetingRequest::create([
                'classroom_id'    => $classroom->id,
                'student_id'      => $student->id,
                'guardian_id'     => $guardian->id,
                'purpose'         => "面談{$i}",
                'candidate_dates' => [],
                'confirmed_date'  => $when,
                'status'          => 'confirmed',
                'confirmed_by'    => 'staff',
                'confirmed_at'    => $when,
            ]);
        }

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/dashboard/calendar');

        $response->assertStatus(200);
        $meetings = $response->json('data.meeting_dates');
        $this->assertCount(2, $meetings);

        $times = collect($meetings)->pluck('time')->all();
        $this->assertContains('09:00', $times);
        $this->assertContains('15:45', $times);
    }

    public function test_other_classroom_meeting_is_not_returned(): void
    {
        [$staff, , , $classroom] = $this->setupContext();

        // 別教室・別企業
        $otherCompany = Company::create(['name' => '企業B']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '教室B',
            'company_id'     => $otherCompany->id,
            'is_active'      => true,
        ]);
        $otherGuardian = User::create([
            'username'  => 'g_other_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '他保護者',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);
        $otherStudent = Student::create([
            'student_name' => '他生徒',
            'classroom_id' => $otherClassroom->id,
            'guardian_id'  => $otherGuardian->id,
        ]);

        $confirmed = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(7)->setTime(11, 0, 0);
        MeetingRequest::create([
            'classroom_id'    => $otherClassroom->id,
            'student_id'      => $otherStudent->id,
            'guardian_id'     => $otherGuardian->id,
            'purpose'         => '他教室の面談',
            'candidate_dates' => [],
            'confirmed_date'  => $confirmed,
            'status'          => 'confirmed',
            'confirmed_by'    => 'staff',
            'confirmed_at'    => $confirmed,
        ]);

        // staff は教室Aのみアクセス可能。他教室の面談は見えない
        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/dashboard/calendar');

        $response->assertStatus(200);
        $meetings = $response->json('data.meeting_dates');
        $this->assertCount(0, $meetings);
    }
}
