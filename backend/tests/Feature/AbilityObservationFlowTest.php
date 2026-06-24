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

    public function test_tool_scope_dev_primary_high_school_only_for_adv(): void
    {
        // 小学生(S3): DEV のみ。発達段階は小学校低学年(S1)から開始する。
        $elem = new Student(['grade_level' => 'elementary_5']);
        $this->assertSame(['DEV'], AbilityToolScope::toolsFor($elem));
        $this->assertSame('S1', AbilityToolScope::axisFor($elem, 'DEV'));

        // 中学生(S4): まだ DEV のみ。高卒標準(ADV)・就業・大学は出さない。
        $jh = new Student(['grade_level' => 'junior_high_2']);
        $this->assertSame(['DEV'], AbilityToolScope::toolsFor($jh));
        $this->assertSame('S1', AbilityToolScope::axisFor($jh, 'DEV'));

        // 高校生(S6): 高校段階で初めて ADV/WRK/UNV も加わる。
        // 各ツールは開始軸(最も低い段階/水準)から: DEV=S1 / ADV=L1(基礎) / 就業・大学=P2
        $hs = new Student(['grade_level' => 'high_school_2']);
        $this->assertSame(['DEV', 'ADV', 'WRK', 'UNV'], AbilityToolScope::toolsFor($hs));
        $this->assertSame('S1', AbilityToolScope::axisFor($hs, 'DEV'));
        $this->assertSame('L1', AbilityToolScope::axisFor($hs, 'ADV'));
        $this->assertSame('P2', AbilityToolScope::axisFor($hs, 'WRK'));
        $this->assertSame('P2', AbilityToolScope::axisFor($hs, 'UNV'));
    }

    public function test_stage_progression_advances_only_when_achieved(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $student = Student::create([
            'student_name' => '児A', 'classroom_id' => $room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ]);
        $item = \App\Models\AbilityEvalItem::where('item_id', 'DEV-1-1')->first();
        $svc = new \App\Services\AbilityQuestionService();

        // スコア無し → 開始段階 S1 から
        $this->assertSame('S1', $svc->currentAxisFor($student, $item));

        // S1 を到達(しきい値8) → 次の段階 S2 へ前進
        \App\Models\AbilityScore::create([
            'student_id' => $student->id, 'item_id' => 'DEV-1-1', 'axis_id' => 'S1', 'score' => 8,
            'method' => 'rule_engine', 'evaluated_on' => Carbon::now()->toDateString(),
        ]);
        $this->assertSame('S2', $svc->currentAxisFor($student, $item));

        // その後 S1 が未到達(7)に下がれば S1 に戻る(最新スコアで判定)
        \App\Models\AbilityScore::create([
            'student_id' => $student->id, 'item_id' => 'DEV-1-1', 'axis_id' => 'S1', 'score' => 7,
            'method' => 'rule_engine', 'evaluated_on' => Carbon::now()->addDay()->toDateString(),
        ]);
        $this->assertSame('S1', $svc->currentAxisFor($student, $item));
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

        // 設問取得: 小学生は DEV のみが対象。先頭は必ず DEV(発達の主軸)、
        // 到達目安は小学校低学年(S1)から始める(高卒標準・科目指導的な設問は出さない)。
        $q = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$student->id}/next-question");
        $q->assertStatus(200);
        $firstItem = $q->json('data.question.item_id');
        $this->assertStringStartsWith('DEV-', $firstItem);
        $this->assertSame('S1', $q->json('data.question.axis_id'));
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
        $this->assertSame('S1', $obs->axis_id);
        $this->assertSame($room->id, $obs->classroom_id);
        $this->assertSame($staff->id, $obs->recorded_by);

        // ローテーション: 直近に出した項目は避けて別項目を出す
        $q2 = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$student->id}/next-question");
        $q2->assertStatus(200);
        $this->assertNotSame($firstItem, $q2->json('data.question.item_id'));
    }

    public function test_build_question_prefers_generated_stage_question(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $student = Student::create([
            'student_name' => '児A', 'classroom_id' => $room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ]);
        $item = \App\Models\AbilityEvalItem::where('item_id', 'DEV-1-1')->first();
        $svc = new \App\Services\AbilityQuestionService();

        // 段階設問が無ければ到達目安(S1)が問いになる(フォールバック)
        $q = $svc->buildQuestion($student, $item);
        $this->assertSame('S1', $q['axis_id']);
        $this->assertNotEmpty($q['question']);
        $this->assertSame($q['benchmark'], $q['question']);
        $this->assertNull($q['hint']);

        // 段階設問(S1)を用意すると、それが問い＋ヒントになる
        \App\Models\AbilityStageQuestion::create([
            'item_id' => 'DEV-1-1', 'axis_id' => 'S1',
            'question' => '声かけがあれば手洗い・歯磨きを自分でできていますか?',
            'hint' => '食事前後・トイレ後の手洗い など', 'is_active' => true,
        ]);
        $q2 = $svc->buildQuestion($student->fresh(), $item);
        $this->assertSame('声かけがあれば手洗い・歯磨きを自分でできていますか?', $q2['question']);
        $this->assertSame('食事前後・トイレ後の手洗い など', $q2['hint']);

        // is_active=false は使わない(フォールバックに戻る)
        \App\Models\AbilityStageQuestion::where('item_id', 'DEV-1-1')->where('axis_id', 'S1')->update(['is_active' => false]);
        $q3 = $svc->buildQuestion($student->fresh(), $item);
        $this->assertSame($q3['benchmark'], $q3['question']);
    }

    public function test_dev_is_asked_first_even_for_high_schooler_and_never_adv_for_junior_high(): void
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $staff = $this->staff($room);

        // 高校生: ADV/WRK/UNV も対象だが、主軸の DEV から先に出す(科目指導的が先に出ない)
        $hs = Student::create([
            'student_name' => '高校生', 'classroom_id' => $room->id, 'grade_level' => 'high_school_2',
            'status' => 'active', 'is_active' => true,
        ]);
        $q = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$hs->id}/next-question");
        $q->assertStatus(200);
        $this->assertStringStartsWith('DEV-', $q->json('data.question.item_id'));
        $this->assertSame('S1', $q->json('data.question.axis_id'));

        // 中学生: 最初の数問はすべて DEV(高卒標準ADVは出題されない)
        $jh = Student::create([
            'student_name' => '中学生', 'classroom_id' => $room->id, 'grade_level' => 'junior_high_2',
            'status' => 'active', 'is_active' => true,
        ]);
        for ($i = 0; $i < 5; $i++) {
            $r = $this->actingAs($staff, 'sanctum')
                ->getJson("/api/staff/ability/students/{$jh->id}/next-question");
            $r->assertStatus(200);
            $itemId = $r->json('data.question.item_id');
            $this->assertStringStartsWith('DEV-', $itemId, '中学生に DEV 以外(高卒標準等)が出題された');
            // 出した項目を観察として保存し、ローテーションで次の項目へ進める
            $this->actingAs($staff, 'sanctum')->postJson('/api/staff/ability/observations', [
                'student_id' => $jh->id, 'item_id' => $itemId,
                'support_code' => 'SUP0', 'result' => 'completed',
            ])->assertStatus(201);
        }
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
