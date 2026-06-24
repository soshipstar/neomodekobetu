<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LR-020: 児童に紐づく連絡帳(振り返り一覧) GET /api/staff/students/{student}/renrakucho
 *
 * 差分カテゴリ: screen
 * 背景: 個別教室では「前回・前々回にこの子に何をしたか」を振り返って今回の
 *       活動を組み立てる場面が多い。従来は日付起点の一覧しか無く、1人の児童で
 *       串刺しに遡る手段が無かった (現場要望)。
 *       その児童の StudentRecord(領域別観察) を軸に、DailyRecord(活動名・共通活動)
 *       と IntegratedNote(連絡帳本文・送信状況) を時系列(活動日降順)で返す。
 *       下書き・未送信も含め全件返す。
 */
class L020_RenrakuchoByStudentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Student, 2: Classroom}
     */
    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '教室A',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $staff = User::create([
            'username'     => 'staff_by_student_' . uniqid(),
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

        return [$staff, $student, $classroom];
    }

    private function makeRecord(
        Classroom $classroom,
        User $staff,
        Student $student,
        string $date,
        string $activity,
        bool $withSentNote = false,
    ): DailyRecord {
        $record = DailyRecord::create([
            'record_date'     => $date,
            'staff_id'        => $staff->id,
            'classroom_id'    => $classroom->id,
            'activity_name'   => $activity,
            'common_activity' => "{$activity}の共通活動内容。",
        ]);
        StudentRecord::create([
            'daily_record_id' => $record->id,
            'student_id'      => $student->id,
            'health_life'     => "{$activity}: 元気でした。",
            'notes'           => "{$activity}: メモ。",
        ]);
        IntegratedNote::create([
            'daily_record_id'    => $record->id,
            'student_id'         => $student->id,
            'integrated_content' => "{$activity}の連絡帳本文。",
            'is_sent'            => $withSentNote,
            'sent_at'            => $withSentNote ? now() : null,
        ]);

        return $record;
    }

    public function test_returns_student_records_in_date_descending_order(): void
    {
        [$staff, $student, $classroom] = $this->setupContext();

        $this->makeRecord($classroom, $staff, $student, '2026-05-01', '5月1日活動');
        $this->makeRecord($classroom, $staff, $student, '2026-05-10', '5月10日活動');
        $this->makeRecord($classroom, $staff, $student, '2026-05-05', '5月5日活動');

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/students/{$student->id}/renrakucho");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $dates = collect($response->json('data.data'))
            ->pluck('daily_record.record_date')
            ->all();

        $this->assertSame(['2026-05-10', '2026-05-05', '2026-05-01'], $dates);
    }

    public function test_includes_unsent_integrated_note(): void
    {
        [$staff, $student, $classroom] = $this->setupContext();

        // 未送信(下書き)の連絡帳も一覧に含まれること
        $this->makeRecord($classroom, $staff, $student, '2026-05-02', '未送信活動', withSentNote: false);

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/students/{$student->id}/renrakucho");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.integrated_note.is_sent', false);
        $response->assertJsonPath('data.data.0.integrated_note.integrated_content', '未送信活動の連絡帳本文。');
        $response->assertJsonPath('data.data.0.daily_record.activity_name', '未送信活動');
        // その子の観察記録(領域)も返る
        $response->assertJsonPath('data.data.0.health_life', '未送信活動: 元気でした。');
    }

    public function test_does_not_include_other_students_records(): void
    {
        [$staff, $student, $classroom] = $this->setupContext();
        $other = Student::create([
            'student_name' => '生徒B',
            'classroom_id' => $classroom->id,
        ]);

        $this->makeRecord($classroom, $staff, $student, '2026-05-03', '対象児童の活動');
        $this->makeRecord($classroom, $staff, $other, '2026-05-04', '別児童の活動');

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/students/{$student->id}/renrakucho");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.daily_record.activity_name', '対象児童の活動');
    }

    public function test_other_classroom_records_are_scoped_out(): void
    {
        [$staff, $student, $classroom] = $this->setupContext();

        // 別教室の活動に同じ児童の記録が混入していても、スタッフのアクセス可能
        // 教室外であれば一覧に出さない (情報漏えい防止)。
        $company = Company::create(['name' => '企業B']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '教室B',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $otherStaff = User::create([
            'username'     => 'staff_other_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフB',
            'user_type'    => 'staff',
            'classroom_id' => $otherClassroom->id,
            'is_active'    => true,
        ]);

        $this->makeRecord($classroom, $staff, $student, '2026-05-06', '自教室の活動');
        $this->makeRecord($otherClassroom, $otherStaff, $student, '2026-05-07', '他教室の活動');

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/students/{$student->id}/renrakucho");

        $response->assertStatus(200);
        $activities = collect($response->json('data.data'))
            ->pluck('daily_record.activity_name')
            ->all();

        $this->assertSame(['自教室の活動'], $activities);
    }

    public function test_forbidden_for_student_outside_accessible_classroom(): void
    {
        [$staff] = $this->setupContext();

        // 別企業・別教室の児童 → 403
        $company = Company::create(['name' => '企業C']);
        $otherClassroom = Classroom::create([
            'classroom_name' => '教室C',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $foreignStudent = Student::create([
            'student_name' => '生徒C',
            'classroom_id' => $otherClassroom->id,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/students/{$foreignStudent->id}/renrakucho")
            ->assertStatus(403);
    }
}
