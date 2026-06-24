<?php

namespace Tests\Feature;

use App\Models\AbilityScore;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 能力評価 到達マップ(P-B1): GET /api/staff/ability/students/{student}/progress-map
 *
 * 差分カテゴリ: api
 * - 項目×学年帯のセル状態(未着手/途上/到達/般化)・current/reached軸を返す。
 * - 期間内(既定6か月)の成長(新規到達マス数・スコア増分・伸びた項目)を履歴から算出。
 * - トグルOFF=409 / 越境=403。
 */
class AbilityProgressMapTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;
    private User $staff;
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
        $this->staff = User::create([
            'username' => 'staff_pm_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->student = Student::create([
            'student_name' => '児A', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
        ]);
    }

    private function score(string $itemId, string $axis, int $score, int $monthsAgo): void
    {
        AbilityScore::create([
            'student_id' => $this->student->id, 'item_id' => $itemId, 'axis_id' => $axis,
            'score' => $score, 'method' => 'rule_engine',
            'evaluated_on' => Carbon::now()->subMonths($monthsAgo)->toDateString(),
        ]);
    }

    public function test_map_cells_and_growth_over_window(): void
    {
        // DEV-1-1(健康・生活): S1 は窓の前(7か月前)に5、窓内(1か月前)に8で到達。S2 は窓内に6(途上)。
        $this->score('DEV-1-1', 'S1', 5, 7);
        $this->score('DEV-1-1', 'S1', 8, 1);
        $this->score('DEV-1-1', 'S2', 6, 1);

        $res = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$this->student->id}/progress-map");
        $res->assertStatus(200);

        // 非高校生は DEV のみ・列は S1〜S6
        $tools = collect($res->json('data.tools'));
        $dev = $tools->firstWhere('tool_id', 'DEV');
        $this->assertNotNull($dev);
        $this->assertSame(['S1', 'S2', 'S3', 'S4', 'S5', 'S6'], collect($dev['axes'])->pluck('axis_id')->all());

        // DEV-1-1 のマス・到達状況
        $items = collect($dev['domains'])->flatMap(fn ($d) => $d['items']);
        $item = $items->firstWhere('item_id', 'DEV-1-1');
        $this->assertSame('S1', $item['reached_axis']);   // 到達済み最高 = S1
        $this->assertSame('S2', $item['current_axis']);   // 今取り組む = S2
        $cells = collect($item['cells'])->keyBy('axis_id');
        $this->assertSame('achieved', $cells['S1']['status']);
        $this->assertSame(8, $cells['S1']['score']);
        $this->assertNotNull($cells['S1']['achieved_on']);
        $this->assertSame('in_progress', $cells['S2']['status']);
        $this->assertSame(6, $cells['S2']['score']);
        $this->assertSame('not_started', $cells['S3']['status']);

        // 成長: S1 が窓内で新規到達(+1)、スコア増分 = S1(8-5=3) + S2(6-0=6) = 9
        $this->assertSame(1, $res->json('data.growth.achieved_delta'));
        $this->assertSame(9, $res->json('data.growth.score_gain_total'));
        $top = collect($res->json('data.growth.top_items'))->firstWhere('item_id', 'DEV-1-1');
        $this->assertNotNull($top); // 到達段階が前進した項目として出る
    }

    public function test_toggle_off_returns_409_and_cross_classroom_403(): void
    {
        // 越境(別教室の児童)→ 403
        $otherCompany = Company::create(['name' => '企業B']);
        $otherRoom = Classroom::create(['classroom_name' => '別', 'company_id' => $otherCompany->id, 'is_active' => true, 'ability_assessment_enabled' => true]);
        $otherStudent = Student::create(['student_name' => '他児', 'classroom_id' => $otherRoom->id, 'status' => 'active', 'is_active' => true]);
        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$otherStudent->id}/progress-map")
            ->assertStatus(403);

        // トグルOFF → 409
        $this->room->update(['ability_assessment_enabled' => false]);
        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/staff/ability/students/{$this->student->id}/progress-map")
            ->assertStatus(409);
    }
}
