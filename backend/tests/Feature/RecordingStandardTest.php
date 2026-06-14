<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\FacilityWritingStandard;
use App\Models\Student;
use App\Models\User;
use App\Services\RecordingStandardAdvisor;
use App\Services\WritingProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 施設記録基準(E1): セクション正規化・基準テキスト生成・保存API・生成プロンプト注入。
 * GPT5.4対話(chat)は外部AI依存のため本テストの対象外(authz/正規化/注入で品質を担保)。
 *
 * 差分カテゴリ: logic / api
 */
class RecordingStandardTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->company = Company::create(['name' => '企業A']); // 集計同意なし
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->admin = User::create([
            'username' => 'ca_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '施設管理者',
            'user_type' => 'admin', 'is_company_admin' => true, 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    public function test_normalize_sections_whitelists_and_caps(): void
    {
        $out = RecordingStandardAdvisor::normalizeSections([
            'tone' => '  敬体で簡潔に  ',
            'required_points' => ['本人を主語に', '事実と解釈を分ける', 123, '', '本人を主語に'],
            'bogus_key' => ['捨てる'],
        ]);
        $this->assertSame('敬体で簡潔に', $out['tone']);
        $this->assertSame(['本人を主語に', '事実と解釈を分ける'], $out['required_points']); // 非文字列/空/重複を除去
        $this->assertArrayNotHasKey('bogus_key', $out); // 未知キーは捨てる
    }

    public function test_compile_guidance_renders_sections(): void
    {
        $text = RecordingStandardAdvisor::compileGuidance([
            'tone' => '敬体で簡潔に',
            'required_points' => ['場面・頻度・手立てを書く'],
            'avoid' => ['断定的な表現'],
            'good_examples' => ['朝の支度に手順表を用いると自分で進められた'],
        ]);
        $this->assertStringContainsString('文体方針: 敬体で簡潔に', $text);
        $this->assertStringContainsString('場面・頻度・手立てを書く', $text);
        $this->assertStringContainsString('断定的な表現', $text);
        $this->assertStringContainsString('朝の支度に手順表', $text);
    }

    public function test_save_normalizes_compiles_and_bumps_version(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->putJson('/api/admin/recording-standard', [
            'sections' => ['tone' => '敬体', 'required_points' => ['本人主語', '事実と解釈を分ける'], 'bogus' => ['x']],
        ])->assertStatus(200)->assertJsonPath('data.version', 1)->assertJsonPath('data.status', 'active');

        $std = FacilityWritingStandard::where('company_id', $this->company->id)->firstOrFail();
        $this->assertArrayNotHasKey('bogus', $std->sections);
        $this->assertStringContainsString('本人主語', $std->guidance_text);

        // 再保存で版が上がる
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/admin/recording-standard', [
            'sections' => ['tone' => '敬体で簡潔に'],
        ])->assertStatus(200)->assertJsonPath('data.version', 2);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/recording-standard')
            ->assertStatus(200)->assertJsonPath('data.status', 'active');
    }

    public function test_save_forbidden_for_non_company_admin(): void
    {
        $plain = User::create([
            'username' => 'a_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '一般管理者',
            'user_type' => 'admin', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
        $this->actingAs($plain, 'sanctum')->putJson('/api/admin/recording-standard', ['sections' => ['tone' => 'x']])
            ->assertStatus(403);
    }

    public function test_active_standard_injected_into_guidance_without_aggregate_consent(): void
    {
        // 集計同意が無くても、施設が明示した基準は生成ガイダンスに注入される(施設自身の方針)。
        $student = Student::create(['student_name' => '児A', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_3', 'status' => 'active', 'is_active' => true]);
        FacilityWritingStandard::create([
            'company_id' => $this->company->id, 'status' => 'active', 'version' => 1,
            'sections' => ['tone' => '敬体で簡潔に'], 'guidance_text' => '■ 文体方針: 敬体で簡潔に',
        ]);

        $guidance = app(WritingProfileService::class)->buildGuidance($student, 'support_plan');
        $this->assertNotNull($guidance);
        $this->assertStringContainsString('この施設が定めた記録基準', $guidance);
        $this->assertStringContainsString('敬体で簡潔に', $guidance);
    }

    public function test_no_guidance_without_standard_or_consent(): void
    {
        $student = Student::create(['student_name' => '児B', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_3', 'status' => 'active', 'is_active' => true]);
        // 基準なし・集計同意なし → null(従来どおり)
        $this->assertNull(app(WritingProfileService::class)->buildGuidance($student, 'support_plan'));
    }
}
