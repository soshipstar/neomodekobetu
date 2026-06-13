<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\ProgramCategory;
use App\Models\Student;
use Database\Seeders\ProgramCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AI学習基盤 S4a: 実施プログラム分類の初期語彙(冪等)と、分析次元のスキーマ拡張を検証する。
 *
 * 差分カテゴリ: data
 */
class ProgramTaxonomySeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_program_categories_seed_is_idempotent_and_complete(): void
    {
        $this->seed(ProgramCategorySeeder::class);
        $this->seed(ProgramCategorySeeder::class);

        $this->assertSame(16, ProgramCategory::count());
        $this->assertSame(16, ProgramCategory::where('is_seeded', true)->whereNull('company_id')->count());

        // 5領域の代表 + 横断
        $this->assertDatabaseHas('program_categories', ['code' => 'sst', 'domain' => 'social_relations']);
        $this->assertDatabaseHas('program_categories', ['code' => 'learning', 'domain' => 'cognitive_behavior']);
        $this->assertDatabaseHas('program_categories', ['code' => 'other', 'domain' => null]);

        // aliases がキーワード配列で入る(自動分類用)
        $sst = ProgramCategory::where('code', 'sst')->first();
        $this->assertIsArray($sst->aliases);
        $this->assertContains('SST', $sst->aliases);

        // 並び順は10刻みで一意
        $this->assertSame(range(10, 160, 10), ProgramCategory::orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_dimension_schema_added(): void
    {
        // 児童に性別カラム
        $this->assertTrue(Schema::hasColumn('students', 'gender'));
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $company->id, 'is_active' => true]);
        $s = Student::create(['student_name' => '児A', 'classroom_id' => $room->id, 'gender' => 'female', 'status' => 'active', 'is_active' => true]);
        $this->assertSame('female', $s->fresh()->gender);

        // 修正イベントの次元スナップショット列
        foreach (['subj_cohort', 'subj_growth_stage', 'subj_grade_level', 'subj_gender', 'support_category', 'program_category_id', 'dim_meta'] as $col) {
            $this->assertTrue(Schema::hasColumn('ai_revision_events', $col), "ai_revision_events.$col missing");
        }
        foreach (['subj_cohort', 'subj_growth_stage', 'subj_grade_level', 'subj_gender'] as $col) {
            $this->assertTrue(Schema::hasColumn('ai_generation_events', $col), "ai_generation_events.$col missing");
        }

        // 分類テーブル
        $this->assertTrue(Schema::hasTable('program_classifications'));
        $this->assertTrue(Schema::hasTable('program_category_candidates'));
    }
}
