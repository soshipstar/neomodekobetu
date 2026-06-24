<?php

namespace Tests\Feature;

use App\Models\AbilityStageQuestion;
use App\Services\AiGenerationService;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 能力評価 P-C2: 段階別具体設問の一括生成コマンド ability:generate-stage-questions。
 *
 * 差分カテゴリ: logic
 * - DEV 5領域 × S1〜S6 = 150マスに AI生成の question/hint を保存。
 * - 既存はスキップ(冪等)、生成直後から is_active=true。
 * - AIクライアントはモックし、外部API・課金を発生させない。
 */
class AbilityStageQuestionGenerateTest extends TestCase
{
    use RefreshDatabase;

    private string $reviewJson;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AbilityEvalMasterSeeder::class);
        $this->reviewJson = storage_path('app/ability_stage_questions_DEV.json');
        $this->cleanupJson();
    }

    protected function tearDown(): void
    {
        $this->cleanupJson(); // 確認用JSONの後片付け(作業ツリーを汚さない)
        parent::tearDown();
    }

    private function cleanupJson(): void
    {
        foreach (['DEV', 'ADV', 'WRK', 'UNV'] as $t) {
            @unlink(storage_path("app/ability_stage_questions_{$t}.json"));
        }
    }

    public function test_generates_dev_150_questions_and_is_idempotent(): void
    {
        $this->mock(AiGenerationService::class, function ($m) {
            $m->shouldReceive('generateStageQuestion')
                ->andReturn(['question' => 'これができていますか?', 'hint' => '観察のヒント']);
        });

        $this->artisan('ability:generate-stage-questions', ['--tool' => 'DEV'])
            ->assertExitCode(0);

        // DEV 25項目 × 6段階 = 150
        $this->assertSame(150, AbilityStageQuestion::count());

        $row = AbilityStageQuestion::firstWhere(['item_id' => 'DEV-1-1', 'axis_id' => 'S1']);
        $this->assertNotNull($row);
        $this->assertSame('これができていますか?', $row->question);
        $this->assertSame('観察のヒント', $row->hint);
        $this->assertTrue($row->is_active);

        // 確認用JSONが出力される
        $this->assertFileExists($this->reviewJson);

        // 冪等: 二回目は既存スキップ(件数不変)
        $this->artisan('ability:generate-stage-questions', ['--tool' => 'DEV'])
            ->assertExitCode(0);
        $this->assertSame(150, AbilityStageQuestion::count());
    }

    public function test_generates_wrk_from_perspective_when_no_benchmark(): void
    {
        // WRK は到達目安マスタが無いが、判断の観点を基準に各項目1問(単一軸P2)生成される
        $this->mock(AiGenerationService::class, function ($m) {
            $m->shouldReceive('generateStageQuestion')
                ->andReturn(['question' => '就業に必要なこれができていますか?', 'hint' => '観察ヒント']);
        });

        $this->artisan('ability:generate-stage-questions', ['--tool' => 'WRK'])
            ->assertExitCode(0);

        // WRK 17項目 × 単一軸P2 = 17
        $this->assertSame(17, AbilityStageQuestion::where('item_id', 'like', 'WRK-%')->count());
        $row = AbilityStageQuestion::where('item_id', 'like', 'WRK-%')->first();
        $this->assertSame('P2', $row->axis_id);
        $this->assertSame('就業に必要なこれができていますか?', $row->question);
    }
}
