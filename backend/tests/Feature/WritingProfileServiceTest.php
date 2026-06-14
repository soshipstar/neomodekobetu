<?php

namespace Tests\Feature;

use App\Models\AiEditMetric;
use App\Models\AiEditReasonCategory;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use App\Services\AiLearningCapture;
use App\Services\ConsentService;
use App\Services\WritingProfileService;
use Database\Seeders\AiEditReasonCategorySeeder;
use Database\Seeders\ConsentDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AI学習基盤 S5: 自己改善ループのプロファイル生成。
 * 施設の確定記述をマスクして注入用ガイダンスを作る。同意ゲート・PII非送出・指示文を検証。
 *
 * 差分カテゴリ: logic
 */
class WritingProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsentService $consent;
    private AiLearningCapture $capture;
    private WritingProfileService $svc;
    private Company $company;
    private Classroom $room;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(ConsentDefinitionSeeder::class);
        $this->seed(AiEditReasonCategorySeeder::class);
        $this->consent = new ConsentService();
        $this->capture = new AiLearningCapture($this->consent);
        $this->svc = new WritingProfileService($this->consent);

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_wp_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    private function fullyConsentedStudent(string $name): Student
    {
        $s = Student::create(['student_name' => $name, 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5', 'status' => 'active', 'is_active' => true]);
        $this->consent->recordStudentConsent($s, true);

        return $s->refresh();
    }

    public function test_guidance_includes_masked_examples(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);

        $past = $this->fullyConsentedStudent('山田太郎');
        $this->capture->recordSectionRevisions($past, 'support_plan', $past->id, [
            'long_term_goal' => ['初期', '山田太郎は集団活動の中で自分の役割を担うことができる'],
            'detail:health_life:support_content' => ['初期', '朝の支度を手順表を用いて自分で進める'],
        ], editorUserId: $this->staff->id);

        $target = $this->fullyConsentedStudent('別の児童');
        $guidance = $this->svc->buildGuidance($target, 'support_plan');

        $this->assertNotNull($guidance);
        $this->assertStringContainsString('長期目標', $guidance);
        $this->assertStringContainsString('集団活動の中で自分の役割', $guidance); // 例の本文(マスク後)
        $this->assertStringContainsString('健康・生活', $guidance);             // detail のセクション名
        // ★実名はマスクされ、プレースホルダに置換される
        $this->assertStringNotContainsString('山田太郎', $guidance);
        $this->assertStringContainsString('【児童】', $guidance);
    }

    public function test_masks_other_children_mentioned_in_examples(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);
        // 同施設の別児童(例文中で言及される)
        Student::create(['student_name' => '鈴木花子', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);

        $past = $this->fullyConsentedStudent('山田太郎');
        $this->capture->recordSectionRevisions($past, 'support_plan', $past->id, [
            'long_term_goal' => ['初期', '山田太郎は鈴木花子と協力して活動に取り組むことができる'],
        ], editorUserId: $this->staff->id);

        $guidance = $this->svc->buildGuidance($this->fullyConsentedStudent('対象児'), 'support_plan');
        $this->assertNotNull($guidance);
        $this->assertStringNotContainsString('山田太郎', $guidance);
        // ★例文が他児に言及していても、施設全体マスカーで他児名もマスクされる
        $this->assertStringNotContainsString('鈴木花子', $guidance);
    }

    public function test_scrubs_non_name_pii_from_examples(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);
        $past = $this->fullyConsentedStudent('山田太郎');
        $this->capture->recordSectionRevisions($past, 'support_plan', $past->id, [
            'long_term_goal' => ['初期', '2018年4月3日生まれ。連絡先090-1234-5678。田中先生と通院する'],
        ], editorUserId: $this->staff->id);

        $g = $this->svc->buildGuidance($this->fullyConsentedStudent('対象'), 'support_plan');
        $this->assertNotNull($g);
        // 生年月日・電話・敬称付き人物名(他者)が外部AI向け文面に残らない
        $this->assertStringNotContainsString('2018年4月3日', $g);
        $this->assertStringNotContainsString('090-1234-5678', $g);
        $this->assertStringNotContainsString('田中先生', $g);
        $this->assertStringContainsString('【日付】', $g);
        $this->assertStringContainsString('【電話】', $g);
    }

    public function test_drops_examples_with_unmaskable_short_names(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);
        Student::create(['student_name' => '林', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]); // 1文字氏名

        $past = $this->fullyConsentedStudent('山田太郎');
        $this->capture->recordSectionRevisions($past, 'support_plan', $past->id, [
            'long_term_goal' => ['初期', '林と一緒に活動に取り組めた'],   // 1文字氏名を含む→捨てる
            'short_term_goal' => ['初期', '集団活動に自分から参加できた'], // 安全→残す
        ], editorUserId: $this->staff->id);

        $g = $this->svc->buildGuidance($this->fullyConsentedStudent('対象'), 'support_plan');
        $this->assertNotNull($g);
        $this->assertStringNotContainsString('林と一緒', $g);       // 1文字氏名を含む例は除外
        $this->assertStringContainsString('集団活動に自分から参加', $g); // 安全な例は残る
    }

    public function test_directives_from_metrics(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);
        $cat = AiEditReasonCategory::where('code', 'too_abstract')->first();
        AiEditMetric::create([
            'period_ym' => Carbon::now()->format('Y-m'), 'facet' => 'company', 'company_id' => $this->company->id,
            'revision_count' => 5, 'distinct_students' => 5,
            'top_reason_categories' => [['category_id' => $cat->id, 'count' => 5]], 'computed_at' => Carbon::now(),
        ]);

        $target = $this->fullyConsentedStudent('児');
        $guidance = $this->svc->buildGuidance($target, 'support_plan');
        $this->assertNotNull($guidance);
        $this->assertStringContainsString('具体的に', $guidance); // too_abstract → 指示文
    }

    public function test_null_without_company_consent(): void
    {
        // 施設が集計同意していない → プロファイルを使わない
        $past = $this->fullyConsentedStudent('山田太郎');
        $this->capture->recordSectionRevisions($past, 'support_plan', $past->id, ['long_term_goal' => ['初期', '改訂']], editorUserId: $this->staff->id);

        $target = $this->fullyConsentedStudent('別児');
        $this->assertNull($this->svc->buildGuidance($target, 'support_plan'));
    }

    public function test_null_without_data(): void
    {
        $this->consent->recordCompanyConsent($this->company, true);
        $target = $this->fullyConsentedStudent('児');
        $this->assertNull($this->svc->buildGuidance($target, 'support_plan'));
    }
}
