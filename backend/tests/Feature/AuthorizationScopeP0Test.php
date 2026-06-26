<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\SubmissionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 認可(IDOR)回帰: 連絡帳(DailyRecord)・提出物(SubmissionRequest)の越境アクセスを 403 で拒否する。
 *
 * 監査で検出した P0(テナント横断で児童PIIの閲覧/更新が可能)の修正に対する回帰テスト。
 * 別会社・別教室のリソースID直叩きは 403、自教室は従来どおり通る(過剰ブロックしない)ことを確認する。
 *
 * 差分カテゴリ: auth
 */
class AuthorizationScopeP0Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function staff(Classroom $room, string $u): User
    {
        return User::create([
            'username' => $u, 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $room->id, 'is_active' => true,
        ]);
    }

    private function student(Classroom $room, string $name): Student
    {
        return Student::create([
            'student_name' => $name, 'classroom_id' => $room->id, 'grade_level' => 'elementary_3',
            'status' => 'active', 'is_active' => true,
        ]);
    }

    public function test_cross_tenant_renrakucho_and_submission_are_forbidden(): void
    {
        // 別会社の2教室(テナント横断)
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $roomA = Classroom::create(['classroom_name' => 'A', 'company_id' => $companyA->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => 'B', 'company_id' => $companyB->id, 'is_active' => true]);

        $staffA = $this->staff($roomA, 'sa_' . uniqid());
        $staffB = $this->staff($roomB, 'sb_' . uniqid());
        $studentB = $this->student($roomB, '児B');

        $recordB = DailyRecord::create([
            'classroom_id' => $roomB->id, 'staff_id' => $staffB->id,
            'record_date' => '2026-06-01', 'activity_name' => '活動B', 'common_activity' => 'B活動',
        ]);
        StudentRecord::create(['daily_record_id' => $recordB->id, 'student_id' => $studentB->id, 'notes' => 'x']);
        $submissionB = SubmissionRequest::create([
            'student_id' => $studentB->id, 'created_by' => $staffB->id, 'title' => '提出B',
        ]);

        // staffA(別会社・別教室)からの越境は全て 403
        $this->actingAs($staffA, 'sanctum')->getJson("/api/staff/renrakucho/{$recordB->id}/student-records")->assertStatus(403);
        $this->actingAs($staffA, 'sanctum')->postJson("/api/staff/renrakucho/{$recordB->id}/student-records", ['student_id' => $studentB->id])->assertStatus(403);
        $this->actingAs($staffA, 'sanctum')->postJson("/api/staff/renrakucho/{$recordB->id}/generate-integrated", ['student_id' => $studentB->id])->assertStatus(403);
        $this->actingAs($staffA, 'sanctum')->getJson("/api/staff/submissions/{$submissionB->id}")->assertStatus(403);
        $this->actingAs($staffA, 'sanctum')->putJson("/api/staff/submissions/{$submissionB->id}", ['title' => 'x'])->assertStatus(403);
        $this->actingAs($staffA, 'sanctum')->deleteJson("/api/staff/submissions/{$submissionB->id}")->assertStatus(403);
        // 越境で別教室児童への提出物作成・関連児童一覧も拒否 (P1)
        $this->actingAs($staffA, 'sanctum')->postJson('/api/staff/submissions', ['student_id' => $studentB->id, 'title' => 'x'])->assertStatus(403);
        $this->actingAs($staffA, 'sanctum')->getJson("/api/staff/submissions/{$submissionB->id}/students")->assertStatus(403);

        // 越境で書き換え/削除/作成されていないこと
        $this->assertDatabaseHas('submission_requests', ['id' => $submissionB->id, 'title' => '提出B']);
        $this->assertDatabaseMissing('submission_requests', ['student_id' => $studentB->id, 'title' => 'x']);
    }

    public function test_own_classroom_is_allowed(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => 'A', 'company_id' => $company->id, 'is_active' => true]);
        $staffA = $this->staff($roomA, 'sa2_' . uniqid());
        $studentA = $this->student($roomA, '児A');

        $recordA = DailyRecord::create([
            'classroom_id' => $roomA->id, 'staff_id' => $staffA->id,
            'record_date' => '2026-06-02', 'activity_name' => '活動A', 'common_activity' => 'A活動',
        ]);
        StudentRecord::create(['daily_record_id' => $recordA->id, 'student_id' => $studentA->id, 'notes' => 'y']);
        $submissionA = SubmissionRequest::create(['student_id' => $studentA->id, 'created_by' => $staffA->id, 'title' => '提出A']);

        // 自教室は従来どおり通る(過剰ブロックしない)
        $this->actingAs($staffA, 'sanctum')->getJson("/api/staff/renrakucho/{$recordA->id}/student-records")->assertStatus(200);
        $this->actingAs($staffA, 'sanctum')->getJson("/api/staff/submissions/{$submissionA->id}")->assertStatus(200);
    }
}
