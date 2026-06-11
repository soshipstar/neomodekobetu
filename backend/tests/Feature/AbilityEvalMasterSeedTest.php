<?php

namespace Tests\Feature;

use App\Models\AbilityEvalAxis;
use App\Models\AbilityEvalBenchmark;
use App\Models\AbilityEvalItem;
use App\Models\AbilityEvalScoreCriterion;
use App\Models\AbilityEvalTool;
use App\Models\AbilitySupportCode;
use App\Models\AbilityTalentCriterion;
use App\Models\AbilityTalentObservationTask;
use App\Models\AbilityTalentSign;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 能力評価システム P0: 評価マスタ(ものさし)が JSON 正本から正しく投入されることを保証する。
 *
 * docs/評価表「能力評価データベース.xlsx」由来の database/data/ability_eval/*.json を
 * AbilityEvalMasterSeeder が取り込む。件数・主要リレーション・冪等性を検証する。
 *
 * 差分カテゴリ: data
 */
class AbilityEvalMasterSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_master_is_seeded_with_expected_counts_relations_and_is_idempotent(): void
    {
        $this->seed(AbilityEvalMasterSeeder::class);

        // 件数(xlsx 正本と一致)
        $this->assertSame(4, AbilityEvalTool::count());
        $this->assertSame(12, AbilityEvalAxis::count());
        $this->assertSame(80, AbilityEvalItem::count());
        $this->assertSame(246, AbilityEvalBenchmark::count());
        $this->assertSame(11, AbilityEvalScoreCriterion::count());
        $this->assertSame(7, AbilitySupportCode::count());
        $this->assertSame(14, AbilityTalentSign::count());
        $this->assertSame(14, AbilityTalentObservationTask::count());
        $this->assertSame(56, AbilityTalentCriterion::count());

        // ツール別の項目内訳(DEV 発達25項目 = 5領域×5項目)
        $this->assertSame(25, AbilityEvalItem::where('tool_id', 'DEV')->count());

        // 代表項目のリレーション: DEV-1-1 は成長段階 S1〜S6 の到達目安6件を持つ
        $item = AbilityEvalItem::find('DEV-1-1');
        $this->assertNotNull($item);
        $this->assertSame('DEV', $item->tool->tool_id);
        $this->assertSame(6, $item->benchmarks()->count());

        // 評価基準は 0〜10 が揃う
        $this->assertNotNull(AbilityEvalScoreCriterion::find(0));
        $this->assertNotNull(AbilityEvalScoreCriterion::find(10));

        // 支援コード SUP0 が存在
        $this->assertNotNull(AbilitySupportCode::find('SUP0'));

        // 才能サインは観察課題と判定基準を持つ
        $sign = AbilityTalentSign::find('TAL-01');
        $this->assertNotNull($sign);
        $this->assertNotNull($sign->observationTask);
        $this->assertGreaterThanOrEqual(1, $sign->criteria()->count());

        // 冪等性: 再実行しても件数は増えない
        $this->seed(AbilityEvalMasterSeeder::class);
        $this->assertSame(80, AbilityEvalItem::count());
        $this->assertSame(246, AbilityEvalBenchmark::count());
    }
}
