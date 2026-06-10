<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Newsletter;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S-001 / S-002: 保護者のお便り・施設評価は在籍児童の教室にスコープする。
 *
 * 背景(不具合/越境): `if (! empty($classroomIds))` でしか絞り込んでおらず、
 * 児童未紐付け等で classroomIds が空になる保護者には
 *  - Newsletter index : 全教室の公開お便りが一覧に漏れる
 *  - Newsletter show  : 403 ガードが素通りし任意の公開お便りを ID 指定で閲覧できる
 *  - FacilityEvaluation index : 他教室の収集中評価を引き当てて回答画面が出る
 * いずれも常に accessibleClassroomIds で絞り込む(空なら 0 件)よう修正。
 *
 * 差分カテゴリ: auth
 */
class AU016_GuardianNewsletterEvaluationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    private function makeNewsletter(int $classroomId, int $createdBy, string $title): Newsletter
    {
        return Newsletter::create([
            'classroom_id' => $classroomId, 'year' => 2030, 'month' => 1,
            'title' => $title, 'status' => 'published', 'published_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    private function makeCollectingPeriod(int $classroomId, int $fiscalYear): void
    {
        DB::table('facility_evaluation_periods')->insert([
            'classroom_id' => $classroomId, 'fiscal_year' => $fiscalYear,
            'title' => "{$fiscalYear}年度評価", 'status' => 'collecting',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_guardian_newsletter_and_evaluation_are_scoped_to_own_classroom(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => '教室B', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_nl_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);

        $nlA = $this->makeNewsletter($roomA->id, $staff->id, '教室Aだより');
        $nlB = $this->makeNewsletter($roomB->id, $staff->id, '教室Bだより');

        // --- 在籍児童が教室Aの保護者 ---
        $guardianA = User::create([
            'username' => 'gA_nl_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者A',
            'user_type' => 'guardian', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);
        Student::create([
            'student_name' => '児A', 'classroom_id' => $roomA->id, 'guardian_id' => $guardianA->id,
            'status' => 'active', 'is_active' => true,
        ]);

        // index: 自教室のお便りのみ
        $listA = $this->actingAs($guardianA, 'sanctum')->getJson('/api/guardian/newsletters');
        $listA->assertStatus(200);
        $titlesA = collect($listA->json('data.data') ?? $listA->json('data'))->pluck('title')->all();
        $this->assertContains('教室Aだより', $titlesA);
        $this->assertNotContains('教室Bだより', $titlesA);

        // show: 他教室のお便りは 403
        $this->actingAs($guardianA, 'sanctum')
            ->getJson("/api/guardian/newsletters/{$nlB->id}")->assertStatus(403);
        // show: 自教室のお便りは 200
        $this->actingAs($guardianA, 'sanctum')
            ->getJson("/api/guardian/newsletters/{$nlA->id}")->assertStatus(200);

        // 施設評価: 他教室(B)に収集中期間 → 教室Aの保護者には出ない
        $this->makeCollectingPeriod($roomB->id, 2030);
        $evalA = $this->actingAs($guardianA, 'sanctum')->getJson('/api/guardian/evaluation');
        $evalA->assertStatus(200);
        $this->assertNull($evalA->json('data.period'));

        // 自教室(A)に収集中期間 → 出る
        $this->makeCollectingPeriod($roomA->id, 2030);
        $evalA2 = $this->actingAs($guardianA, 'sanctum')->getJson('/api/guardian/evaluation');
        $evalA2->assertStatus(200);
        $this->assertNotNull($evalA2->json('data.period'));

        // --- 児童未紐付けの保護者 → classroomIds 空 → 一切見えない ---
        $guardianNone = User::create([
            'username' => 'gN_nl_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者N',
            'user_type' => 'guardian', 'classroom_id' => null, 'is_active' => true,
        ]);

        $listN = $this->actingAs($guardianNone, 'sanctum')->getJson('/api/guardian/newsletters');
        $listN->assertStatus(200);
        $titlesN = collect($listN->json('data.data') ?? $listN->json('data'))->pluck('title')->all();
        $this->assertEmpty($titlesN);

        // show: 空保護者は任意の公開お便りも 403
        $this->actingAs($guardianNone, 'sanctum')
            ->getJson("/api/guardian/newsletters/{$nlA->id}")->assertStatus(403);

        // 施設評価: 空保護者には period が出ない
        $evalN = $this->actingAs($guardianNone, 'sanctum')->getJson('/api/guardian/evaluation');
        $evalN->assertStatus(200);
        $this->assertNull($evalN->json('data.period'));
    }
}
