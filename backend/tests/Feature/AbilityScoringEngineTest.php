<?php

namespace Tests\Feature;

use App\Models\AbilityObservation;
use App\Models\AbilityScore;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AbilityScoringService;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 能力評価 P3: スコア更新ルールエンジン(決定的採点)の各判定経路。
 *
 * 差分カテゴリ: logic
 */
class AbilityScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AbilityEvalMasterSeeder::class);

        $company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $this->student = Student::create([
            'student_name' => '児A', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ]);
    }

    private function obs(string $itemId, string $support, string $result, bool $newScene = false, int $daysAgo = 5): void
    {
        AbilityObservation::create([
            'classroom_id' => $this->room->id,
            'student_id' => $this->student->id,
            'item_id' => $itemId,
            'axis_id' => 'S3',
            'support_code' => $support,
            'result' => $result,
            'is_new_scene' => $newScene,
            'behavior' => 'テスト行動',
            'observed_date' => Carbon::now()->subDays($daysAgo)->toDateString(),
            'recorded_by' => null,
        ]);
    }

    /** 採点結果(item単位)を取り出す。 */
    private function resultFor(array $results, string $itemId): ?array
    {
        foreach ($results as $r) {
            if ($r['item_id'] === $itemId) {
                return $r;
            }
        }

        return null;
    }

    public function test_insufficient_records_are_held_and_support_level_bases(): void
    {
        // DEV-1-1: 2件のみ → 記録不足(スコア行を作らない)
        $this->obs('DEV-1-1', 'SUP0', 'completed');
        $this->obs('DEV-1-1', 'SUP0', 'completed');

        // DEV-1-2: SUP4 が最頻 → 基準点3
        $this->obs('DEV-1-2', 'SUP4', 'completed');
        $this->obs('DEV-1-2', 'SUP4', 'completed');
        $this->obs('DEV-1-2', 'SUP4', 'partial');

        // DEV-1-3: SUP1 → 5
        $this->obs('DEV-1-3', 'SUP1', 'completed');
        $this->obs('DEV-1-3', 'SUP1', 'completed');
        $this->obs('DEV-1-3', 'SUP1', 'completed');

        // DEV-1-4: SUP5 完了中心 → 2 / DEV-1-5: SUP5 途中中心 → 1
        $this->obs('DEV-1-4', 'SUP5', 'completed');
        $this->obs('DEV-1-4', 'SUP5', 'completed');
        $this->obs('DEV-1-4', 'SUP5', 'partial');
        $this->obs('DEV-1-5', 'SUP5', 'partial');
        $this->obs('DEV-1-5', 'SUP5', 'partial');
        $this->obs('DEV-1-5', 'SUP5', 'completed');

        $results = (new AbilityScoringService())->scoreStudent($this->student);

        $this->assertSame('insufficient', $this->resultFor($results, 'DEV-1-1')['status']);
        $this->assertNull(AbilityScore::where('item_id', 'DEV-1-1')->first());

        $this->assertSame(3, AbilityScore::where('item_id', 'DEV-1-2')->value('score'));
        $this->assertSame(5, AbilityScore::where('item_id', 'DEV-1-3')->value('score'));
        $this->assertSame(2, AbilityScore::where('item_id', 'DEV-1-4')->value('score'));
        $this->assertSame(1, AbilityScore::where('item_id', 'DEV-1-5')->value('score'));
    }

    public function test_sup0_success_rate_and_generalization(): void
    {
        // DEV-2-1: 支援なし完了4件(同一場面扱い・新規なし) → 成功率100% → 8(般化せず)
        for ($i = 0; $i < 4; $i++) {
            $this->obs('DEV-2-1', 'SUP0', 'completed', false, 5 + $i);
        }
        // DEV-2-2: 支援なし、完了3/途中2 → 成功率60% → 6
        $this->obs('DEV-2-2', 'SUP0', 'completed');
        $this->obs('DEV-2-2', 'SUP0', 'completed');
        $this->obs('DEV-2-2', 'SUP0', 'completed');
        $this->obs('DEV-2-2', 'SUP0', 'partial');
        $this->obs('DEV-2-2', 'SUP0', 'partial');
        // DEV-2-3: 支援なし完了4件・うち1件は新規場面 → 般化 → 9
        $this->obs('DEV-2-3', 'SUP0', 'completed', true, 5);
        $this->obs('DEV-2-3', 'SUP0', 'completed', false, 7);
        $this->obs('DEV-2-3', 'SUP0', 'completed', false, 9);
        $this->obs('DEV-2-3', 'SUP0', 'completed', false, 11);

        (new AbilityScoringService())->scoreStudent($this->student);

        $this->assertSame(8, AbilityScore::where('item_id', 'DEV-2-1')->value('score'));
        $this->assertSame(6, AbilityScore::where('item_id', 'DEV-2-2')->value('score'));
        $this->assertSame(9, AbilityScore::where('item_id', 'DEV-2-3')->value('score'));
    }

    public function test_change_is_clamped_to_two_and_flags_review_and_unchanged_skips(): void
    {
        // 前回スコア4を用意
        AbilityScore::create([
            'student_id' => $this->student->id, 'item_id' => 'DEV-3-1', 'axis_id' => 'S3',
            'score' => 4, 'method' => 'rule_engine', 'evaluated_on' => Carbon::now()->subMonths(2)->toDateString(),
        ]);
        // 生スコア8相当(支援なし完了4) → 変動+4 → ±2制限で6、要人間確認
        for ($i = 0; $i < 4; $i++) {
            $this->obs('DEV-3-1', 'SUP0', 'completed', false, 5 + $i);
        }

        // 変化なしケース: 前回8、観察も8相当 → 追記しない
        AbilityScore::create([
            'student_id' => $this->student->id, 'item_id' => 'DEV-3-2', 'axis_id' => 'S3',
            'score' => 8, 'method' => 'rule_engine', 'evaluated_on' => Carbon::now()->subMonths(2)->toDateString(),
        ]);
        for ($i = 0; $i < 4; $i++) {
            $this->obs('DEV-3-2', 'SUP0', 'completed', false, 5 + $i);
        }

        (new AbilityScoringService())->scoreStudent($this->student);

        $latest = AbilityScore::where('item_id', 'DEV-3-1')->orderByDesc('id')->first();
        $this->assertSame(6, $latest->score);
        $this->assertSame(4, $latest->prev_score);
        $this->assertSame(2, $latest->change);
        $this->assertTrue($latest->needs_review);
        $this->assertNotEmpty($latest->evidence_record_ids);
        $this->assertNotEmpty($latest->notes);

        // DEV-3-2 は変化なし → 行は増えていない(1件のまま)
        $this->assertSame(1, AbilityScore::where('item_id', 'DEV-3-2')->count());
    }

    public function test_recompute_and_scores_api_with_gating(): void
    {
        $staff = User::create([
            'username' => 'staff_sc_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->obs('DEV-4-1', 'SUP1', 'completed');
        $this->obs('DEV-4-1', 'SUP1', 'completed');
        $this->obs('DEV-4-1', 'SUP1', 'completed');

        $rc = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/ability/students/{$this->student->id}/recompute-scores");
        $rc->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $rc->json('data.scored'));

        $sc = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$this->student->id}/scores");
        $sc->assertStatus(200);
        $this->assertSame(5, collect($sc->json('data'))->firstWhere('item_id', 'DEV-4-1')['score']);

        // OFF 教室は409
        $this->room->update(['ability_assessment_enabled' => false]);
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/ability/students/{$this->student->id}/recompute-scores")
            ->assertStatus(409);
    }
}
