<?php

namespace Tests\Feature;

use App\Models\AiEditReasonCategory;
use App\Models\ConsentDefinition;
use Database\Seeders\AiEditReasonCategorySeeder;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤(S1): 参照データ(同意定義4件・固定修正理由11件)の投入と冪等性を検証する。
 *
 * 差分カテゴリ: data
 */
class AiLearningSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_seeders_are_idempotent_and_complete(): void
    {
        // 2回流しても重複しない(冪等)
        $this->seed(ConsentDefinitionSeeder::class);
        $this->seed(ConsentDefinitionSeeder::class);
        $this->seed(AiEditReasonCategorySeeder::class);
        $this->seed(AiEditReasonCategorySeeder::class);

        $this->assertSame(4, ConsentDefinition::count());
        foreach (['service_generation', 'improvement_aggregate', 'model_learning', 'local_ai'] as $key) {
            $this->assertDatabaseHas('consent_definitions', ['consent_key' => $key, 'version' => 1, 'is_active' => true]);
        }
        // 施設同意は subject=company、その他は student
        $this->assertSame('company', ConsentDefinition::where('consent_key', 'improvement_aggregate')->value('subject_type'));
        $this->assertSame('student', ConsentDefinition::where('consent_key', 'model_learning')->value('subject_type'));

        $this->assertSame(11, AiEditReasonCategory::count());
        $this->assertSame(11, AiEditReasonCategory::where('is_seeded', true)->whereNull('company_id')->count());
        $this->assertDatabaseHas('ai_edit_reason_categories', ['code' => 'factual_error', 'company_id' => null, 'status' => 'active']);
        // 並び順が10刻みで一意
        $sorts = AiEditReasonCategory::orderBy('sort_order')->pluck('sort_order')->all();
        $this->assertSame(range(10, 110, 10), $sorts);
    }
}
