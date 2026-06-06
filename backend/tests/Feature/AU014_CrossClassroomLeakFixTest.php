<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\MeetingRequest;
use App\Models\Newsletter;
use App\Models\Student;
use App\Models\SubmissionRequest;
use App\Models\User;
use App\Models\WagePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AU014: 教室・企業横断データ漏洩防止テスト
 *
 * 差分カテゴリ: auth
 *
 * 監査により以下の cross-classroom leak が特定された:
 *  LEAK-001 MeetingController::show()/update() — classroom_id 無検査
 *  LEAK-002 NewsletterController::show()/update()/destroy() — classroom_id 無検査
 *  LEAK-003 StaffSubmissionController::show()/update()/destroy()/students() — classroom_id 無検査
 *  LEAK-005 PendingTaskController が guardian.classroom_id でスコープ (students.classroom_id にすべき)
 *  LEAK-006 SchoolHolidayActivity/DailyRoutine/Event 等が classroom_id=null で全件返却
 *  LEAK-007 RenrakuchoController が staff.classroom_id 経由でチェック (staff=null でバイパス可)
 *  LEAK-008 WageController が classroom_id 単一比較 (multi-classroom staff に過剰制限)
 *  DASHBOARD-LEAK DashboardController が master に全教室データを表示
 *
 * 旧アプリ (neomodekobetu) は教室単位で厳格にスコープされていたのが正解。
 */
class AU014_CrossClassroomLeakFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 2 企業 + 各 1 教室 + スタッフ + 生徒 + 保護者の最小フィクスチャ。
     */
    private function fixture(): array
    {
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);

        $classA = Classroom::create(['classroom_name' => 'A1', 'company_id' => $companyA->id, 'is_active' => true]);
        $classB = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);

        $staffA = User::create([
            'username'     => 'staff_a_au014',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classA->id,
            'is_active'    => true,
        ]);
        $staffB = User::create([
            'username'     => 'staff_b_au014',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフB',
            'user_type'    => 'staff',
            'classroom_id' => $classB->id,
            'is_active'    => true,
        ]);

        $guardianA = User::create([
            'username'     => 'guardian_a_au014',
            'password'     => bcrypt('pass'),
            'full_name'    => '保護者A',
            'user_type'    => 'guardian',
            'classroom_id' => $classA->id,
            'is_active'    => true,
        ]);

        $studentA = Student::create([
            'classroom_id' => $classA->id,
            'student_name' => '生徒A',
            'guardian_id'  => $guardianA->id,
            'is_active'    => true,
        ]);

        return compact('companyA', 'companyB', 'classA', 'classB', 'staffA', 'staffB', 'guardianA', 'studentA');
    }

    // =========================================================================
    // LEAK-001: MeetingController show/update
    // =========================================================================

    public function test_leak001_meeting_show_blocks_other_classroom(): void
    {
        $f = $this->fixture();
        $meeting = MeetingRequest::create([
            'classroom_id' => $f['classA']->id,
            'student_id'   => $f['studentA']->id,
            'guardian_id'  => $f['guardianA']->id,
            'staff_id'     => $f['staffA']->id,
            'purpose'      => '面談A',
            'status'       => 'pending',
        ]);

        // staffB (別企業) からアクセス
        $response = $this->actingAs($f['staffB'], 'sanctum')
            ->getJson('/api/staff/meetings/' . $meeting->id);
        $response->assertStatus(403);

        // staffA (同教室) からは見られる
        $this->actingAs($f['staffA'], 'sanctum')
            ->getJson('/api/staff/meetings/' . $meeting->id)
            ->assertStatus(200);
    }

    public function test_leak001_meeting_update_blocks_other_classroom(): void
    {
        $f = $this->fixture();
        $meeting = MeetingRequest::create([
            'classroom_id' => $f['classA']->id,
            'student_id'   => $f['studentA']->id,
            'guardian_id'  => $f['guardianA']->id,
            'staff_id'     => $f['staffA']->id,
            'purpose'      => '面談A',
            'status'       => 'pending',
        ]);

        $this->actingAs($f['staffB'], 'sanctum')
            ->putJson('/api/staff/meetings/' . $meeting->id, ['action' => 'cancel'])
            ->assertStatus(403);
    }

    // =========================================================================
    // LEAK-002: NewsletterController show/update/destroy
    // =========================================================================

    public function test_leak002_newsletter_show_blocks_other_classroom(): void
    {
        $f = $this->fixture();
        $newsletter = Newsletter::create([
            'classroom_id' => $f['classA']->id,
            'created_by'   => $f['staffA']->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => 'お便りA',
            'is_published' => false,
        ]);

        $this->actingAs($f['staffB'], 'sanctum')
            ->getJson('/api/staff/newsletters/' . $newsletter->id)
            ->assertStatus(403);
    }

    public function test_leak002_newsletter_destroy_blocks_other_classroom(): void
    {
        $f = $this->fixture();
        $newsletter = Newsletter::create([
            'classroom_id' => $f['classA']->id,
            'created_by'   => $f['staffA']->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => 'お便りA',
            'is_published' => false,
        ]);

        $this->actingAs($f['staffB'], 'sanctum')
            ->deleteJson('/api/staff/newsletters/' . $newsletter->id)
            ->assertStatus(403);

        $this->assertNotNull(Newsletter::find($newsletter->id));
    }

    // =========================================================================
    // LEAK-003: StaffSubmissionController show/destroy
    // =========================================================================

    public function test_leak003_submission_show_blocks_other_classroom(): void
    {
        $f = $this->fixture();
        $submissionId = DB::table('submission_requests')->insertGetId([
            'student_id'   => $f['studentA']->id,
            'created_by'   => $f['staffA']->id,
            'title'        => '提出物A',
            'is_completed' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->actingAs($f['staffB'], 'sanctum')
            ->getJson('/api/staff/submissions/' . $submissionId)
            ->assertStatus(403);

        $this->actingAs($f['staffA'], 'sanctum')
            ->getJson('/api/staff/submissions/' . $submissionId)
            ->assertStatus(200);
    }

    // =========================================================================
    // LEAK-005: PendingTaskController が students.classroom_id でスコープ
    // =========================================================================

    public function test_leak005_pending_tasks_uses_student_classroom_not_guardian(): void
    {
        $f = $this->fixture();

        // 保護者は classA に登録、子は classB に在籍 (ややねじれたケース)
        $crossGuardian = User::create([
            'username'     => 'cross_guardian_au014',
            'password'     => bcrypt('pass'),
            'full_name'    => '横断保護者',
            'user_type'    => 'guardian',
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);
        $crossStudent = Student::create([
            'classroom_id'        => $f['classB']->id,
            'student_name'        => 'classB生徒',
            'guardian_id'         => $crossGuardian->id,
            'is_active'           => true,
            'support_start_date'  => now()->subYear()->toDateString(),
        ]);

        // staffA (classA) が pending-tasks を見ても、classB の生徒は混入しない
        $response = $this->actingAs($f['staffA'], 'sanctum')
            ->getJson('/api/staff/pending-tasks');
        $response->assertStatus(200);

        $plans = collect($response->json('data.plans') ?? []);
        $names = $plans->pluck('student_name')->all();
        $this->assertNotContains('classB生徒', $names,
            '保護者の classroom_id ではなく生徒の classroom_id でスコープすべき');
    }

    // =========================================================================
    // LEAK-006: classroom_id=null ユーザでも全件漏洩しない
    // =========================================================================

    public function test_leak006_event_index_returns_empty_for_classroom_null_user(): void
    {
        $f = $this->fixture();

        // classroom_id=null のスタッフ (誤設定/移籍中など)
        $orphan = User::create([
            'username'     => 'orphan_au014',
            'password'     => bcrypt('pass'),
            'full_name'    => '所属なし',
            'user_type'    => 'staff',
            'classroom_id' => null,
            'is_master'    => false,
            'is_active'    => true,
        ]);

        // classA にイベントを作成
        \App\Models\Event::create([
            'classroom_id' => $f['classA']->id,
            'event_name'   => 'classA イベント',
            'event_date'   => now()->toDateString(),
        ]);

        $response = $this->actingAs($orphan, 'sanctum')
            ->getJson('/api/staff/events');
        $response->assertStatus(200);

        $events = $response->json('data') ?? [];
        $events = is_array($events) && isset($events['data']) ? $events['data'] : $events;
        $this->assertCount(0, $events, 'classroom_id=null ユーザに全件返却してはならない');
    }

    // =========================================================================
    // DASHBOARD-LEAK: マスター管理者が dashboard で別教室を見ない
    // =========================================================================

    public function test_dashboard_leak_master_only_sees_current_workspace_classroom(): void
    {
        $f = $this->fixture();

        // マスター管理者を作成し、classA に workspace 切替中とする
        $master = User::create([
            'username'     => 'master_au014',
            'password'     => bcrypt('pass'),
            'full_name'    => 'マスター',
            'user_type'    => 'admin',
            'is_master'    => true,
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);

        // classB に欠席連絡を作る (マスターには見えてはならない)
        \App\Models\AbsenceNotification::create([
            'student_id'     => Student::create([
                'classroom_id' => $f['classB']->id,
                'student_name' => 'classB生徒',
                'is_active'    => true,
            ])->id,
            'absence_date'   => now()->toDateString(),
            'reason'         => '体調不良',
            'makeup_status'  => 'pending',
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->getJson('/api/staff/dashboard/summary');
        $response->assertStatus(200);

        // pending_makeup は classA 内のみカウント → classB の振替依頼は混入しない
        $this->assertSame(0, $response->json('data.pending_makeup'),
            'マスター管理者は現在 workspace 切替中 (classA) のデータのみを集計すべき');
    }

    // =========================================================================
    // HOLIDAYS-422: 非マスターの classroom_id 強制 / 422 解消
    // =========================================================================

    public function test_holidays_store_autofills_classroom_id_for_non_master(): void
    {
        $f = $this->fixture();

        // フロントエンドは classroom_id を送らないケース
        $response = $this->actingAs($f['staffA'], 'sanctum')
            ->postJson('/api/admin/holidays', [
                'holiday_date' => '2026-12-30',
                'holiday_name' => '年末年始',
            ]);

        $response->assertStatus(201);
        $this->assertSame($f['classA']->id, $response->json('data.classroom_id'));
    }

    public function test_holidays_store_rejects_non_master_specifying_other_classroom(): void
    {
        $f = $this->fixture();

        // staffA が他教室の classroom_id を送っても無視されて自教室になる
        $response = $this->actingAs($f['staffA'], 'sanctum')
            ->postJson('/api/admin/holidays', [
                'classroom_id' => $f['classB']->id,
                'holiday_date' => '2026-12-30',
                'holiday_name' => 'なりすまし試行',
            ]);

        $response->assertStatus(201);
        // 非マスターは request の classroom_id を無視して自教室で登録される
        $this->assertSame($f['classA']->id, $response->json('data.classroom_id'));
    }

    // =========================================================================
    // STUDENT-LEAK: マスターが workspace 切替先以外の生徒を見ない
    //   /staff/students/11 で就労 workspace にいるとき放デイの生徒が見える
    //   問題のレポートに対応。
    // =========================================================================

    public function test_master_staff_students_list_scoped_to_current_workspace(): void
    {
        $f = $this->fixture();

        // 別企業 (classB) に生徒を作成
        Student::create([
            'classroom_id' => $f['classB']->id,
            'student_name' => 'classB生徒',
            'is_active'    => true,
            'status'       => 'active',
        ]);

        $master = User::create([
            'username'     => 'master_au014_st',
            'password'     => bcrypt('pass'),
            'full_name'    => 'Master',
            'user_type'    => 'admin',
            'is_master'    => true,
            'classroom_id' => $f['classA']->id,  // workspace: classA に切替中
            'is_active'    => true,
        ]);

        $response = $this->actingAs($master, 'sanctum')
            ->getJson('/api/staff/students');
        $response->assertStatus(200);

        $names = collect($response->json('data') ?? [])->pluck('student_name')->all();
        $this->assertContains('生徒A', $names, 'classA の生徒は見えるべき');
        $this->assertNotContains('classB生徒', $names,
            'マスター workspace 切替中の教室外の生徒は見えてはならない');
    }

    // =========================================================================
    // STORE-AUTH: 非マスターが他教室を classroom_id に指定して登録できない
    // =========================================================================

    public function test_admin_student_store_rejects_non_master_other_classroom(): void
    {
        $f = $this->fixture();

        // staffA を admin として一時的に格上げ (is_master=false)
        $admin = User::create([
            'username'     => 'admin_a_au014_st',
            'password'     => bcrypt('pass'),
            'full_name'    => '通常管理者A',
            'user_type'    => 'admin',
            'is_master'    => false,
            'is_company_admin' => false,
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);

        // 別企業 classB に登録しようとする
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/students', [
                'classroom_id' => $f['classB']->id,
                'student_name' => '不正登録試行',
                'username'     => 'illegal_user_au014',
                'password'     => 'pass1234',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_student_store_allows_master_to_any_classroom(): void
    {
        $f = $this->fixture();

        $master = User::create([
            'username'     => 'master_au014_st_create',
            'password'     => bcrypt('pass'),
            'full_name'    => 'Master',
            'user_type'    => 'admin',
            'is_master'    => true,
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);

        // master は classB にも登録可
        $response = $this->actingAs($master, 'sanctum')
            ->postJson('/api/admin/students', [
                'classroom_id' => $f['classB']->id,
                'student_name' => 'マスター登録',
                'username'     => 'master_create_au014',
                'password'     => 'pass1234',
            ]);

        $response->assertStatus(201);
        $this->assertSame($f['classB']->id, $response->json('data.classroom_id'));
    }
}
