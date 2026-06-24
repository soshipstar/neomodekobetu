<?php

namespace Tests\Feature;

use App\Models\AbilityStageQuestion;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 能力評価 P-C1: 段階別具体設問テーブル ability_stage_questions のスキーマ/モデル。
 *
 * 差分カテゴリ: schema
 */
class AbilityStageQuestionsSchemaTest extends TestCase
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

    public function test_can_store_and_read_stage_question(): void
    {
        $q = AbilityStageQuestion::create([
            'item_id' => 'DEV-1-1',
            'axis_id' => 'S1',
            'question' => '声かけや見守りがあれば、手洗い・歯磨き・身だしなみを自分で行えていますか?',
            'hint' => '食事前後・トイレ後の手洗い、朝の整容 など',
            'model' => 'gpt-5.4',
            'generated_at' => Carbon::now(),
        ]);

        $fresh = AbilityStageQuestion::firstWhere(['item_id' => 'DEV-1-1', 'axis_id' => 'S1']);
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_active); // 既定で出題に使う
        $this->assertSame('gpt-5.4', $fresh->model);
        $this->assertStringContainsString('手洗い', $fresh->question);
        // 項目リレーション
        $this->assertSame('基本的生活習慣(睡眠・衛生・身だしなみ)', $fresh->item->name);
    }

    public function test_unique_per_item_and_axis(): void
    {
        AbilityStageQuestion::create([
            'item_id' => 'DEV-1-1', 'axis_id' => 'S1', 'question' => 'A',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        AbilityStageQuestion::create([
            'item_id' => 'DEV-1-1', 'axis_id' => 'S1', 'question' => 'B(重複)',
        ]);
    }
}
