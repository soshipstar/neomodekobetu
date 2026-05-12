<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R2 / B132: 保護者1アカウントで企業内の複数教室の児童を閲覧できるようにする
 *
 * 差分カテゴリ: logic
 *
 * 報告内容: 「児童の保護者が企業内であればどの教室でも自動と紐づけできるはずなのに
 * それができない。児童・施設の組み合わせで個別支援計画・連絡帳が作成されているので
 * 保護者もその単位で自分のお子さんのことを確認できるようにしてほしい。保護者は1アカウントで
 * 教室・児童を閲覧できてほしい。」
 *
 * 修正方針:
 * - users.classroom_id (単一) で絞り込んでいた箇所を accessibleClassroomIds() (配列) で
 *   絞り込むよう変更。
 * - 影響範囲: Guardian/DashboardController, Guardian/NewsletterController,
 *   Guardian/AnnouncementController, Guardian/FacilityEvaluationController
 */
class B132_GuardianMultiClassroomTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 同企業内の2教室それぞれに児童を1人ずつ持つ保護者の fixture。
     *
     * @return array{guardian:User, classroomA:Classroom, classroomB:Classroom, studentA:Student, studentB:Student}
     */
    private function fixture(): array
    {
        $classroomA = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $classroomB = Classroom::create(['classroom_name' => '教室B', 'is_active' => true]);

        // 保護者は教室Aに classroom_id を持つ前提だが、児童は両教室にまたがる
        $guardian = User::create([
            'username'     => 'g_b132_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => '保護者B132',
            'user_type'    => 'guardian',
            'classroom_id' => $classroomA->id,
            'is_active'    => true,
        ]);

        $studentA = Student::create([
            'classroom_id' => $classroomA->id,
            'guardian_id'  => $guardian->id,
            'student_name' => '児童A',
            'is_active'    => true,
            'status'       => 'active',
        ]);

        $studentB = Student::create([
            'classroom_id' => $classroomB->id,
            'guardian_id'  => $guardian->id,
            'student_name' => '児童B',
            'is_active'    => true,
            'status'       => 'active',
        ]);

        return compact('guardian', 'classroomA', 'classroomB', 'studentA', 'studentB');
    }

    /**
     * accessibleClassroomIds() が保護者の児童経由で複数教室を返すこと
     */
    public function test_accessible_classroom_ids_returns_all_child_classrooms(): void
    {
        ['guardian' => $g, 'classroomA' => $a, 'classroomB' => $b] = $this->fixture();

        $ids = $g->accessibleClassroomIds();
        sort($ids);
        $expected = [$a->id, $b->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    /**
     * ダッシュボードのカレンダーで、両教室のホリデー・イベントが返ること
     */
    public function test_dashboard_calendar_includes_both_classroom_holidays_and_events(): void
    {
        ['guardian' => $g, 'classroomA' => $a, 'classroomB' => $b] = $this->fixture();

        // 教室Aと教室Bにそれぞれ別の休日とイベントを登録
        DB::table('holidays')->insert([
            'holiday_date' => '2026-05-15',
            'classroom_id' => $a->id,
            'holiday_name' => '教室A休日',
            'holiday_type' => 'special',
            'created_at'   => now(),
        ]);
        DB::table('holidays')->insert([
            'holiday_date' => '2026-05-20',
            'classroom_id' => $b->id,
            'holiday_name' => '教室B休日',
            'holiday_type' => 'special',
            'created_at'   => now(),
        ]);

        DB::table('events')->insert([
            'event_date'   => '2026-05-12',
            'classroom_id' => $a->id,
            'event_name'   => '教室Aイベント',
            'created_by'   => $g->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('events')->insert([
            'event_date'   => '2026-05-25',
            'classroom_id' => $b->id,
            'event_name'   => '教室Bイベント',
            'created_by'   => $g->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($g, 'sanctum')
            ->getJson('/api/guardian/dashboard?year=2026&month=5');

        $response->assertStatus(200);
        $calendar = $response->json('data.calendar');

        // 両教室の休日が含まれる
        $this->assertArrayHasKey('2026-05-15', $calendar['holidays']);
        $this->assertArrayHasKey('2026-05-20', $calendar['holidays']);

        // 両教室のイベントが含まれる
        $this->assertArrayHasKey('2026-05-12', $calendar['events']);
        $this->assertArrayHasKey('2026-05-25', $calendar['events']);
    }

    /**
     * お便り (newsletters) も両教室分が返ること
     */
    public function test_newsletter_index_includes_both_classroom_newsletters(): void
    {
        ['guardian' => $g, 'classroomA' => $a, 'classroomB' => $b] = $this->fixture();

        DB::table('newsletters')->insert([
            'classroom_id' => $a->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => '教室Aお便り',
            'status'       => 'published',
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('newsletters')->insert([
            'classroom_id' => $b->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => '教室Bお便り',
            'status'       => 'published',
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($g, 'sanctum')
            ->getJson('/api/guardian/newsletters');

        $response->assertStatus(200);
        $titles = collect($response->json('data.data'))->pluck('title')->all();
        $this->assertContains('教室Aお便り', $titles);
        $this->assertContains('教室Bお便り', $titles);
    }

    /**
     * 他企業/他保護者の教室のお便りは取得されないこと
     */
    public function test_newsletter_index_excludes_unrelated_classrooms(): void
    {
        ['guardian' => $g, 'classroomA' => $a] = $this->fixture();

        // この保護者と無関係の教室
        $otherClassroom = Classroom::create(['classroom_name' => '無関係教室', 'is_active' => true]);

        DB::table('newsletters')->insert([
            'classroom_id' => $a->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => '見えるお便り',
            'status'       => 'published',
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        DB::table('newsletters')->insert([
            'classroom_id' => $otherClassroom->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => '見えないお便り',
            'status'       => 'published',
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($g, 'sanctum')
            ->getJson('/api/guardian/newsletters');

        $response->assertStatus(200);
        $titles = collect($response->json('data.data'))->pluck('title')->all();
        $this->assertContains('見えるお便り', $titles);
        $this->assertNotContains('見えないお便り', $titles);
    }

    /**
     * Newsletter::show は権限外教室なら 403
     */
    public function test_newsletter_show_returns_403_for_unrelated_classroom(): void
    {
        ['guardian' => $g] = $this->fixture();

        $otherClassroom = Classroom::create(['classroom_name' => '無関係教室', 'is_active' => true]);
        $newsletterId = DB::table('newsletters')->insertGetId([
            'classroom_id' => $otherClassroom->id,
            'year'         => 2026,
            'month'        => 5,
            'title'        => '見えないお便り',
            'status'       => 'published',
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($g, 'sanctum')
            ->getJson("/api/guardian/newsletters/{$newsletterId}");

        $response->assertStatus(403);
    }
}
