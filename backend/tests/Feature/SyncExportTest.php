<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 国保連請求システム(kiduriacount)向けデータ提供 API（共有シークレット）。
 * students=児童＋保護者、attendance=連絡帳由来の利用日＋到着帰宅由来の利用時間。
 */
class SyncExportTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-sync-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.kiduriacount.sso_secret' => self::SECRET]);
    }

    public function test_rejects_bad_secret(): void
    {
        $this->postJson('/api/sync/students', ['secret' => 'wrong', 'classroom_ids' => [1]])
            ->assertStatus(401);
    }

    public function test_students_export_returns_children_and_guardian(): void
    {
        $company = Company::create(['name' => 'なずく法人']);
        $classroom = Classroom::create(['classroom_name' => 'なずく教室', 'company_id' => $company->id, 'is_active' => true]);
        $guardian = User::create([
            'username' => 'g_'.uniqid(), 'password' => bcrypt('p'),
            'full_name' => '栗原 太郎', 'full_name_kana' => 'クリハラ タロウ', 'email' => 'g@example.com',
            'user_type' => 'guardian', 'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $withGuardian = Student::create([
            'classroom_id' => $classroom->id, 'guardian_id' => $guardian->id,
            'student_name' => '栗原 海', 'student_name_kana' => 'クリハラ カイ',
            'birth_date' => '2017-05-01', 'grade_level' => 'elementary_1', 'status' => 'active', 'is_active' => true,
        ]);
        Student::create([
            'classroom_id' => $classroom->id, 'student_name' => '保護者なし児', 'status' => 'active', 'is_active' => true,
        ]);

        $res = $this->postJson('/api/sync/students', ['secret' => self::SECRET, 'classroom_ids' => [$classroom->id]]);
        $res->assertOk();
        $students = collect($res->json('data.students'));
        $this->assertCount(2, $students);

        $a = $students->firstWhere('k26_student_id', $withGuardian->id);
        $this->assertSame('栗原 海', $a['child_name']);
        $this->assertSame('クリハラ カイ', $a['child_name_kana']);
        $this->assertSame('2017-05-01', $a['birth_date']);
        $this->assertSame($company->id, $a['company_id']);
        $this->assertSame('なずく教室', $a['classroom_name']);
        $this->assertSame('栗原 太郎', $a['guardian']['full_name']);
        $this->assertArrayNotHasKey('child_sex', $a);

        $noG = $students->firstWhere('child_name', '保護者なし児');
        $this->assertNull($noG['guardian'], '保護者なしは guardian=null で表面化');
    }

    public function test_attendance_days_from_renrakucho_and_times_from_attendance(): void
    {
        $classroom = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $student = Student::create(['classroom_id' => $classroom->id, 'student_name' => '児', 'status' => 'active', 'is_active' => true]);

        // 連絡帳: 5/07 と 5/08 に integrated_note → 利用日2日
        foreach (['2026-05-07', '2026-05-08'] as $date) {
            $drId = DB::table('daily_records')->insertGetId([
                'classroom_id' => $classroom->id, 'record_date' => $date, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('integrated_notes')->insert([
                'daily_record_id' => $drId, 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 到着帰宅: 5/07 のみ時刻あり（attendance_records をテスト内で用意）
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_id');
            $t->unsignedBigInteger('classroom_id');
            $t->date('record_date');
            $t->timestamp('check_in_time')->nullable();
            $t->timestamp('check_out_time')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });
        DB::table('attendance_records')->insert([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'record_date' => '2026-05-07',
            'check_in_time' => '2026-05-07T09:30:00+09:00', 'check_out_time' => '2026-05-07T16:00:00+09:00',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->postJson('/api/sync/attendance', ['secret' => self::SECRET, 'classroom_id' => $classroom->id, 'month' => '202605']);
        $res->assertOk();
        $att = collect($res->json('data.attendance'));
        $this->assertCount(1, $att);
        $days = collect($att->first()['days'])->keyBy('date');
        $this->assertSame(['2026-05-07', '2026-05-08'], $days->keys()->sort()->values()->all(), '利用日=連絡帳の2日');
        $this->assertSame('09:30', $days['2026-05-07']['start_time'], '到着=JST H:i');
        $this->assertSame('16:00', $days['2026-05-07']['end_time']);
        $this->assertNull($days['2026-05-08']['start_time'], '到着連絡なし→空');
        $this->assertNull($days['2026-05-08']['end_time']);

        Schema::dropIfExists('attendance_records');
    }

    public function test_attendance_without_attendance_table_returns_null_times(): void
    {
        Schema::dropIfExists('attendance_records'); // 本番に同テーブルが無い環境を再現
        $classroom = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $student = Student::create(['classroom_id' => $classroom->id, 'student_name' => '児', 'status' => 'active', 'is_active' => true]);
        $drId = DB::table('daily_records')->insertGetId([
            'classroom_id' => $classroom->id, 'record_date' => '2026-05-10', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('integrated_notes')->insert([
            'daily_record_id' => $drId, 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->postJson('/api/sync/attendance', ['secret' => self::SECRET, 'classroom_id' => $classroom->id, 'month' => '202605']);
        $res->assertOk(); // テーブル不在でも500にならない
        $days = $res->json('data.attendance.0.days');
        $this->assertCount(1, $days);
        $this->assertSame('2026-05-10', $days[0]['date']);
        $this->assertNull($days[0]['start_time']);
    }
}
