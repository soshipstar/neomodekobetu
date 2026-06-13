<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\ProgramCategory;
use App\Models\ProgramClassification;
use App\Models\Student;
use App\Models\User;
use App\Services\ProgramClassifier;
use Database\Seeders\ProgramCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI学習基盤 S4b: 実施プログラム分類エンジン(ルール)と、連絡帳(活動)保存時の自動分類を検証する。
 *
 * 差分カテゴリ: logic
 */
class ProgramClassifierTest extends TestCase
{
    use RefreshDatabase;

    private ProgramClassifier $svc;
    private Company $company;
    private Classroom $room;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ProgramCategorySeeder::class);
        $this->svc = new ProgramClassifier();

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_pc_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    private function codeOf(int $categoryId): string
    {
        return ProgramCategory::whereKey($categoryId)->value('code');
    }

    public function test_rule_classification_matches_best_category(): void
    {
        $r1 = $this->svc->classify('今日は公園で運動遊びと体操をしました');
        $this->assertNotNull($r1);
        $this->assertSame('gross_motor', $this->codeOf($r1['program_category_id']));

        $r2 = $this->svc->classify('宿題と読み書きの学習支援を行いました');
        $this->assertSame('learning', $this->codeOf($r2['program_category_id']));

        // キーワードに該当しない場合は null(その他へ無理に寄せない)
        $this->assertNull($this->svc->classify('zzz 無関係なテキスト qqq'));
    }

    public function test_classify_and_store_then_manual_is_respected(): void
    {
        $pc = $this->svc->classifyAndStore('daily_record', 100, '感覚遊びで水遊びをした', $this->company->id);
        $this->assertNotNull($pc);
        $this->assertSame('rule', $pc->method);
        $this->assertSame('sensory', $this->codeOf($pc->program_category_id));

        // 人手で訂正(SSTへ)
        $sst = ProgramCategory::where('code', 'sst')->value('id');
        $this->svc->setManual('daily_record', 100, $sst, $this->staff->id);
        $this->assertSame(1, ProgramClassification::where('classifiable_type', 'daily_record')->where('classifiable_id', 100)->count());
        $manual = ProgramClassification::where('classifiable_id', 100)->first();
        $this->assertSame('manual', $manual->method);
        $this->assertSame($sst, $manual->program_category_id);

        // 自動分類を再実行しても manual を尊重(上書きしない)
        $this->assertNull($this->svc->classifyAndStore('daily_record', 100, '感覚遊びで水遊びをした', $this->company->id));
        $this->assertSame('manual', ProgramClassification::where('classifiable_id', 100)->first()->method);
    }

    public function test_usage_count_is_diff_updated(): void
    {
        $sensory = ProgramCategory::where('code', 'sensory')->value('id');
        $gross = ProgramCategory::where('code', 'gross_motor')->value('id');

        // 同一カテゴリへ2回再分類 → usage_count は1のまま(過剰加算しない)
        $this->svc->classifyAndStore('daily_record', 200, '水遊び 感覚遊び', $this->company->id);
        $this->svc->classifyAndStore('daily_record', 200, '感覚遊び 粘土', $this->company->id);
        $this->assertSame('sensory', $this->codeOf(\App\Models\ProgramClassification::where('classifiable_id', 200)->first()->program_category_id));
        $this->assertSame(1, ProgramCategory::whereKey($sensory)->value('usage_count'));

        // 別カテゴリへ訂正 → 旧-1 / 新+1
        $this->svc->setManual('daily_record', 200, $gross, $this->staff->id);
        $this->assertSame(0, ProgramCategory::whereKey($sensory)->value('usage_count'));
        $this->assertSame(1, ProgramCategory::whereKey($gross)->value('usage_count'));
    }

    public function test_renrakucho_store_auto_classifies_activity(): void
    {
        $student = Student::create(['student_name' => '児A', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);

        $res = $this->actingAs($this->staff, 'sanctum')->postJson('/api/staff/renrakucho', [
            'record_date' => '2026-06-12',
            'activity_name' => '公園で運動遊び',
            'common_activity' => 'みんなで体を動かして遊びました',
            'students' => [['id' => $student->id, 'health_life' => '元気に活動']],
        ]);
        $res->assertStatus(201);
        $recordId = $res->json('data.id');

        $pc = ProgramClassification::where('classifiable_type', 'daily_record')->where('classifiable_id', $recordId)->first();
        $this->assertNotNull($pc);
        $this->assertSame('rule', $pc->method);
        $this->assertSame('gross_motor', $this->codeOf($pc->program_category_id));
    }
}
