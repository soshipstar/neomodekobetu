<?php

namespace Tests\Feature;

use App\Services\AiSafetyTriage;
use Tests\TestCase;

/**
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R10 / R12 (2026-05-17):
 *  - V1 有害情報の出力制御 / 表 3-2 ③ 高リスク文脈での安全優先モード切替
 *  - V4 ハイリスク利用 / 表 3-5 ③ 相談窓口・救急対応への誘導
 *
 * AiSafetyTriage の高リスク文脈検出と安全バナー生成の単体検証。
 */
class AIS003_SafetyTriageTest extends TestCase
{
    public function test_detects_self_harm_keywords(): void
    {
        $triage = new AiSafetyTriage();
        $result = $triage->containsHighRiskContent('本人は最近「死にたい」と発言することがあった');

        $this->assertTrue($result['detected']);
        $this->assertContains('self_harm', $result['categories']);
    }

    public function test_detects_violence_keywords(): void
    {
        $triage = new AiSafetyTriage();
        $result = $triage->containsHighRiskContent('保護者から虐待の疑いがある旨の相談があった');

        $this->assertTrue($result['detected']);
        $this->assertContains('violence', $result['categories']);
    }

    public function test_detects_emergency_symptoms(): void
    {
        $triage = new AiSafetyTriage();
        $result = $triage->containsHighRiskContent('活動中に痙攣を起こしたため救急要請した');

        $this->assertTrue($result['detected']);
        $this->assertContains('emergency', $result['categories']);
    }

    public function test_returns_no_detection_for_clean_text(): void
    {
        $triage = new AiSafetyTriage();
        $result = $triage->containsHighRiskContent('本日は工作活動に集中して取り組めました。');

        $this->assertFalse($result['detected']);
        $this->assertEmpty($result['categories']);
        $this->assertEmpty($result['hits']);
    }

    public function test_safety_banner_contains_contact_numbers(): void
    {
        $triage = new AiSafetyTriage();
        $banner = $triage->safetyBanner(['self_harm']);

        $this->assertStringContainsString('いのちの電話', $banner);
        $this->assertStringContainsString('189', $banner);
        $this->assertStringContainsString('119', $banner);
    }

    public function test_safety_banner_differentiates_categories(): void
    {
        $triage = new AiSafetyTriage();

        $selfHarm = $triage->safetyBanner(['self_harm']);
        $this->assertStringContainsString('自傷・自殺', $selfHarm);

        $violence = $triage->safetyBanner(['violence']);
        $this->assertStringContainsString('暴力・虐待', $violence);

        $emergency = $triage->safetyBanner(['emergency']);
        $this->assertStringContainsString('急性症状', $emergency);
    }

    public function test_detects_multiple_categories_at_once(): void
    {
        $triage = new AiSafetyTriage();
        $result = $triage->containsHighRiskContent('リストカットの跡があり、また殴られたとの相談があった');

        $this->assertTrue($result['detected']);
        $this->assertContains('self_harm', $result['categories']);
        $this->assertContains('violence', $result['categories']);
    }

    public function test_hits_deduplicate_on_multiple_occurrences(): void
    {
        $triage = new AiSafetyTriage();
        $result = $triage->containsHighRiskContent('自殺 自殺 自殺');

        $this->assertTrue($result['detected']);
        // 同じワードが複数回ヒットしても hits 配列には一度だけ
        $this->assertSame(['自殺'], $result['hits']);
    }
}
