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
        @unlink($this->reviewJson);
    }

    protected function tearDown(): void
    {
        @unlink($this->reviewJson); // 確認用JSONの後片付け(作業ツリーを汚さない)
        parent::tearDown();
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
}
