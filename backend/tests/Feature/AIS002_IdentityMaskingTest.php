<?php

namespace Tests\Feature;

use App\Services\AiIdentityMasker;
use Tests\TestCase;

/**
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R2 / R12 (2026-05-17):
 *  - V5 プライバシー保護 / 表 3-6 ③ 入力時の個人情報マスキング・仮名化
 *  - 4.2.5 (1) プライバシー・バイ・デザイン
 *
 * AiIdentityMasker の register / mask / unmask の単体検証。
 */
class AIS002_IdentityMaskingTest extends TestCase
{
    public function test_register_returns_placeholder_per_category(): void
    {
        $m = new AiIdentityMasker();
        $student = $m->register('山田 太郎', 'student');
        $guardian = $m->register('山田 花子', 'guardian');
        $classroom = $m->register('港教室', 'classroom');

        $this->assertSame('対象児童 A', $student);
        $this->assertSame('保護者 A', $guardian);
        $this->assertSame('事業所 A', $classroom);
    }

    public function test_same_name_returns_same_placeholder(): void
    {
        $m = new AiIdentityMasker();
        $first  = $m->register('山田 太郎', 'student');
        $second = $m->register('山田 太郎', 'student');
        $this->assertSame($first, $second);
    }

    public function test_mask_replaces_registered_names_in_text(): void
    {
        $m = new AiIdentityMasker();
        $m->register('山田 太郎', 'student');
        $m->register('港教室', 'classroom');

        $original = '本日 山田 太郎 さんは 港教室 で活動しました。';
        $masked = $m->mask($original);

        $this->assertStringNotContainsString('山田 太郎', $masked);
        $this->assertStringNotContainsString('港教室', $masked);
        $this->assertStringContainsString('対象児童 A', $masked);
        $this->assertStringContainsString('事業所 A', $masked);
    }

    public function test_unmask_restores_original_names(): void
    {
        $m = new AiIdentityMasker();
        $m->register('山田 太郎', 'student');

        $aiOutput = '対象児童 A の本日の様子は良好でした。';
        $unmasked = $m->unmask($aiOutput);

        $this->assertSame('山田 太郎 の本日の様子は良好でした。', $unmasked);
    }

    public function test_long_names_replaced_before_short_to_avoid_partial_match(): void
    {
        $m = new AiIdentityMasker();
        $m->register('田中', 'student');
        $m->register('田中 太郎', 'student');

        $masked = $m->mask('田中 太郎 と 田中 さんが来ました');
        // 「田中 太郎」が「対象児童 A」に置換され、「田中」単独は「対象児童 B」に置換される
        $this->assertStringNotContainsString('田中 太郎', $masked);
        $this->assertStringContainsString('対象児童 A', $masked);
        $this->assertStringContainsString('対象児童 B', $masked);
    }

    public function test_register_all_handles_multi_category_input(): void
    {
        $m = new AiIdentityMasker();
        $m->registerAll([
            'student'   => ['山田 太郎', '佐藤 花子'],
            'classroom' => ['港教室'],
        ]);

        $this->assertNotEmpty($m->placeholderFor('山田 太郎'));
        $this->assertNotEmpty($m->placeholderFor('佐藤 花子'));
        $this->assertNotEmpty($m->placeholderFor('港教室'));
    }

    public function test_empty_name_is_ignored(): void
    {
        $m = new AiIdentityMasker();
        $result = $m->register('', 'student');
        $this->assertSame('', $result);
        $this->assertEmpty($m->getMap());
    }

    public function test_detect_placeholder_leakage_finds_remaining_markers(): void
    {
        $m = new AiIdentityMasker();
        $m->register('山田 太郎', 'student');

        $outputWithLeakage = '対象児童 A はがんばりました';
        $hits = $m->detectPlaceholderLeakage($outputWithLeakage);
        $this->assertContains('対象児童 A', $hits);

        $cleanOutput = '山田 太郎 はがんばりました';
        $hits2 = $m->detectPlaceholderLeakage($cleanOutput);
        $this->assertEmpty($hits2);
    }
}
