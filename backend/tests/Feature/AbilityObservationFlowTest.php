<?php

namespace Tests\Feature;

use App\Models\AbilityObservation;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Support\AbilityGrowthStage;
use App\Support\AbilityToolScope;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 能力評価 P2: 日々の設問取得・観察記録保存・設問ローテーション・認可。
 *
 * 差分カテゴリ: logic
 */
class AbilityObservationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AbilityEvalMasterSeeder::class);
    }

    private function staff(Classroom $room): User
    {
        return User::create([
            'username' => 'staff_ab_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $room->id, 'is_active' => true,
        ]);
    }

    public function test_growth_stage_is_derived_from_grade_level_and_birth_date(): void
    {
        // 学年単位の grade_level が最優先
        $s1 = new Student(['grade_level' => 'elementary_5']);
        $this->assertSame('S3', AbilityGrowthStage::forStudent($s1));
        $s2 = new Student(['grade_level' => 'junior_high_2']);
        $this->assertSame('S4', AbilityGrowthStage::forStudent($s2));

        // birth_date から年度ベースで学年計算(中1=S4)。clock非依存で asOf 固定。
        $grade = AbilityGrowthStage::japaneseGrade(Carbon::parse('2014-05-01'), Carbon::parse('2026-06-01'));
        $this->assertSame(7, $grade); // 中1
    }

    public function test_tool_scope_expands_with_age(): void
    {
        // 小学生(S3): DEV+ADV のみ
        $elem = new Student(['grade_level' => 'elementary_5']);
        $this->assertSame(['DEV', 'ADV'], AbilityToolScope::toolsFor($elem));
        $this->assertSame('S3', AbilityToolScope::axisFor($elem, 'DEV'));
        $this->assertSame('L2', AbilityToolScope::axisFor($elem, 'ADV'));

        // 中学生(S4): 就業WRK・大学UNV も追加。時期は中学期P1
        $jh = new Student(['grade_level' => 'junior_high_2']);
        $this->assertSame(['DEV', 'ADV', 'WRK', 'UNV'], AbilityToolScope::toolsFor($jh));
        $this->assertSame('P1', AbilityToolScope::axisFor($jh, 'WRK'));

        // 高校生(S6): 時期は高校期P2
        $hs = new Student(['grade_level' => 'high_school_2']);
        $this->assertSame(['DEV', 'ADV', 'WRK', 'UNV'], AbilityToolScope::toolsFor($hs));
        $this->assertSame('P2', AbilityToolScope::axisFor($hs, 'UNV'));
    }

    public function test_next_question_and_store_and_rotation(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $staff = $this->staff($room);
        $student = Student::create([
            'student_name' => '児A', 'classroom_id' => $room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ]);

        // 設問取得: 小学生(S3)は DEV+ADV が対象。項目のツールに応じた軸の到達目安が返る
        $q = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$student->id}/next-question");
        $q->assertStatus(200);
        $firstItem = $q->json('data.question.item_id');
        $this->assertMatchesRegularExpression('/^(DEV|ADV)-/', $firstItem);
        $expectedAxis = str_starts_with($firstItem, 'ADV-') ? 'L2' : 'S3';
        $this->assertSame($expectedAxis, $q->json('data.question.axis_id'));
        $this->assertNotEmpty($q->json('data.question.benchmark'));
        $this->assertSame(['completed', 'partial', 'refused'], $q->json('data.results'));
        $this->assertNotEmpty(collect($q->json('data.support_codes'))->firstWhere('code', 'SUP0'));

        // 観察記録を保存: axis_id/classroom はサーバ側で確定
        $store = $this->actingAs($staff, 'sanctum')->postJson('/api/staff/ability/observations', [
            'student_id' => $student->id,
            'item_id' => $firstItem,
            'support_code' => 'SUP0',
            'result' => 'completed',
            'is_new_scene' => false,
            'behavior' => '声かけなしで取り組んだ',
        ]);
        $store->assertStatus(201);
        $obs = AbilityObservation::first();
        $this->assertSame($expectedAxis, $obs->axis_id);
        $this->assertSame($room->id, $obs->classroom_id);
        $this->assertSame($staff->id, $obs->recorded_by);

        // ローテーション: 直近に出した項目は避けて別項目を出す
        $q2 = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$student->id}/next-question");
        $q2->assertStatus(200);
        $this->assertNotSame($firstItem, $q2->json('data.question.item_id'));
    }

    public function test_disabled_classroom_returns_409_and_cross_classroom_403(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomOff = Classroom::create([
            'classroom_name' => 'OFF', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => false,
        ]);
        $roomOther = Classroom::create([
            'classroom_name' => '別', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $staff = $this->staff($roomOff);

        $studentOff = Student::create([
            'student_name' => '児Off', 'classroom_id' => $roomOff->id, 'grade_level' => 'elementary_3',
            'status' => 'active', 'is_active' => true,
        ]);
        $studentOther = Student::create([
            'student_name' => '児Other', 'classroom_id' => $roomOther->id, 'grade_level' => 'elementary_3',
            'status' => 'active', 'is_active' => true,
        ]);

        // 機能OFFの自教室 → 409
        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$studentOff->id}/next-question")
            ->assertStatus(409);

        // 他教室の児童 → 403(越境防止)
        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$studentOther->id}/next-question")
            ->assertStatus(403);

        // 保存も同様に越境は403
        $this->actingAs($staff, 'sanctum')->postJson('/api/staff/ability/observations', [
            'student_id' => $studentOther->id,
            'item_id' => 'DEV-1-1',
        ])->assertStatus(403);
    }
}
