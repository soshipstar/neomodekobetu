<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\ProgramCategory;
use App\Models\ProgramClassification;
use App\Models\User;
use Database\Seeders\ProgramCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 S4b: 実施プログラム分類の閲覧・訂正API(職員)。
 *
 * 差分カテゴリ: api
 */
class ProgramClassificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private User $staff;
    private DailyRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ProgramCategorySeeder::class);

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_pca_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->record = DailyRecord::create([
            'classroom_id' => $this->room->id, 'record_date' => '2026-06-12',
            'activity_name' => '制作活動', 'staff_id' => $this->staff->id,
        ]);
    }

    public function test_categories_list_returns_global_set(): void
    {
        $res = $this->actingAs($this->staff, 'sanctum')->getJson('/api/staff/program-categories');
        $res->assertStatus(200);
        $this->assertGreaterThanOrEqual(16, count($res->json('data')));
    }

    public function test_staff_can_correct_classification(): void
    {
        $sst = ProgramCategory::where('code', 'sst')->value('id');

        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/staff/renrakucho/{$this->record->id}/program-classification", ['program_category_id' => $sst])
            ->assertStatus(200)
            ->assertJsonPath('data.method', 'manual');

        $pc = ProgramClassification::where('classifiable_type', 'daily_record')->where('classifiable_id', $this->record->id)->first();
        $this->assertSame('manual', $pc->method);
        $this->assertSame($sst, $pc->program_category_id);
        $this->assertSame($this->staff->id, $pc->classified_by);
    }

    public function test_cannot_use_other_company_category(): void
    {
        $other = Company::create(['name' => '企業B']);
        $otherCat = ProgramCategory::create([
            'domain' => null, 'code' => 'custom_b', 'label_ja' => 'B社独自', 'company_id' => $other->id,
            'is_seeded' => false, 'status' => 'active', 'sort_order' => 200,
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/staff/renrakucho/{$this->record->id}/program-classification", ['program_category_id' => $otherCat->id])
            ->assertStatus(403);
    }

    public function test_cross_classroom_staff_forbidden(): void
    {
        $otherRoom = Classroom::create(['classroom_name' => '別', 'company_id' => $this->company->id, 'is_active' => true]);
        $otherStaff = User::create([
            'username' => 'staff_o_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '別',
            'user_type' => 'staff', 'classroom_id' => $otherRoom->id, 'is_active' => true,
        ]);
        $sst = ProgramCategory::where('code', 'sst')->value('id');

        $this->actingAs($otherStaff, 'sanctum')
            ->putJson("/api/staff/renrakucho/{$this->record->id}/program-classification", ['program_category_id' => $sst])
            ->assertStatus(403);
    }
}
