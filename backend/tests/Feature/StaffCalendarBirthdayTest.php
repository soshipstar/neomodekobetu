<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * スタッフカレンダーに所属教室の在籍児童の誕生日を載せる。
 *
 * 表示は氏名を伏せマークのみ(クリックで確認)だが、API は誰の誕生日かを返す
 * (スタッフは担当児童名を閲覧可)。教室スコープ・表示月で絞り込む。
 *
 * 差分カテゴリ: screen
 */
class StaffCalendarBirthdayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_calendar_returns_birthdays_scoped_to_classroom_and_month(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => '事業所B', 'company_id' => $company->id, 'is_active' => true]);

        $staff = User::create([
            'username' => 'staff_bd_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);

        $birthday = Student::create([
            'student_name' => '誕生児', 'classroom_id' => $roomA->id, 'birth_date' => '2014-06-15',
            'status' => 'active', 'is_active' => true,
        ]);
        // 別教室の児童(対象外)
        Student::create([
            'student_name' => '他教室児', 'classroom_id' => $roomB->id, 'birth_date' => '2014-06-20',
            'status' => 'active', 'is_active' => true,
        ]);
        // 別月の児童(6月には出ない)
        Student::create([
            'student_name' => '7月児', 'classroom_id' => $roomA->id, 'birth_date' => '2014-07-10',
            'status' => 'active', 'is_active' => true,
        ]);

        $res = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/dashboard/calendar?year=2026&month=6');
        $res->assertStatus(200);

        $births = collect($res->json('data.birth_dates'));
        $this->assertCount(1, $births);
        $row = $births->first();
        $this->assertSame('2026-06-15', $row['date']);
        $this->assertSame($birthday->id, $row['student_id']);
        $this->assertSame('誕生児', $row['student_name']);
    }
}
