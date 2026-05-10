<?php

namespace Tests\Unit;

use App\Services\ServiceTypeRegistry;
use PHPUnit\Framework\TestCase;

/**
 * U001 ServiceTypeRegistry: 4 サービス種別ごとの labels / strengthKeys /
 * strengthDomainMapping / planDomains / terms / aiServiceFocus が syuro26 と
 * 同じ値を返すことを検証する。
 *
 * 差分カテゴリ: logic
 * 背景: serviceType 駆動設計 (Phase G〜) のレジストリは画面・API・PDF・AI の
 *       全レイヤから参照される。各種別の値が壊れると全画面で文言と項目が崩れる
 *       ため、ここで値そのものを正典として固定する。
 */
class ServiceTypeRegistryTest extends TestCase
{
    public function test_isValid_accepts_only_known_service_types(): void
    {
        $this->assertTrue(ServiceTypeRegistry::isValid('after_school'));
        $this->assertTrue(ServiceTypeRegistry::isValid('employment_a'));
        $this->assertTrue(ServiceTypeRegistry::isValid('employment_b'));
        $this->assertTrue(ServiceTypeRegistry::isValid('transition'));

        $this->assertFalse(ServiceTypeRegistry::isValid(''));
        $this->assertFalse(ServiceTypeRegistry::isValid('unknown'));
        $this->assertFalse(ServiceTypeRegistry::isValid('放課後等デイサービス'));
    }

    public function test_label_and_short_label_for_all_service_types(): void
    {
        $this->assertSame('放課後等デイサービス', ServiceTypeRegistry::label(ServiceTypeRegistry::AFTER_SCHOOL));
        $this->assertSame('就労継続支援A型',     ServiceTypeRegistry::label(ServiceTypeRegistry::EMPLOYMENT_A));
        $this->assertSame('就労継続支援B型',     ServiceTypeRegistry::label(ServiceTypeRegistry::EMPLOYMENT_B));
        $this->assertSame('就労移行支援',         ServiceTypeRegistry::label(ServiceTypeRegistry::TRANSITION));

        $this->assertSame('放デイ', ServiceTypeRegistry::shortLabel(ServiceTypeRegistry::AFTER_SCHOOL));
        $this->assertSame('就A',   ServiceTypeRegistry::shortLabel(ServiceTypeRegistry::EMPLOYMENT_A));
        $this->assertSame('就B',   ServiceTypeRegistry::shortLabel(ServiceTypeRegistry::EMPLOYMENT_B));
        $this->assertSame('就移',   ServiceTypeRegistry::shortLabel(ServiceTypeRegistry::TRANSITION));
    }

    public function test_label_falls_back_to_input_for_unknown_value(): void
    {
        $this->assertSame('unknown',  ServiceTypeRegistry::label('unknown'));
        $this->assertSame('-bogus-', ServiceTypeRegistry::shortLabel('-bogus-'));
    }

    public function test_strength_keys_have_exactly_ten_per_service_type(): void
    {
        foreach (ServiceTypeRegistry::ALL as $type) {
            $keys = ServiceTypeRegistry::strengthKeys($type);
            $this->assertCount(10, $keys, "{$type} の強み項目数は 10 でなければならない");
            $this->assertSame(array_unique($keys), $keys, "{$type} の強み項目に重複がある");
        }
    }

    public function test_after_school_strength_keys_match_syuro26_order(): void
    {
        // syuro26 diaries/new/page.tsx (after_school) と同順
        $this->assertSame([
            '集中力', '持続力', '丁寧さ', '発想力', '観察力',
            '思いやり', '情報処理の速さ', '手先の器用さ', '自分で選ぶ力', 'コミュニケーションの工夫',
        ], ServiceTypeRegistry::strengthKeys(ServiceTypeRegistry::AFTER_SCHOOL));
    }

    public function test_employment_and_transition_strength_keys_are_distinct_sets(): void
    {
        $a = ServiceTypeRegistry::strengthKeys(ServiceTypeRegistry::EMPLOYMENT_A);
        $b = ServiceTypeRegistry::strengthKeys(ServiceTypeRegistry::EMPLOYMENT_B);
        $t = ServiceTypeRegistry::strengthKeys(ServiceTypeRegistry::TRANSITION);

        // 就A は「正確性」「報連相」「冷静さ」、就B は「穏やかさ」「興味の芽ばえ」を含む等
        $this->assertContains('正確性',   $a);
        $this->assertContains('報連相',   $a);
        $this->assertContains('冷静さ',   $a);
        $this->assertContains('穏やかさ', $b);
        $this->assertContains('興味の芽ばえ', $b);

        // 就移は「自己理解」「ビジネスマナー」「時間管理」を含み、放デイ用キーは含まない
        $this->assertContains('自己理解',     $t);
        $this->assertContains('ビジネスマナー', $t);
        $this->assertContains('時間管理',     $t);
        $this->assertNotContains('発想力',     $t);
        $this->assertNotContains('思いやり',   $t);
    }

    public function test_strength_keys_unknown_falls_back_to_after_school(): void
    {
        $this->assertSame(
            ServiceTypeRegistry::strengthKeys(ServiceTypeRegistry::AFTER_SCHOOL),
            ServiceTypeRegistry::strengthKeys('unknown'),
        );
    }

    public function test_strength_domain_mapping_covers_all_strength_keys(): void
    {
        // 各種別で「強みキー」が「ドメインマッピング」のキー集合に含まれる必要がある
        // (集計 trends で domain ラベルを引けないと UI/PDF が壊れる)
        foreach (ServiceTypeRegistry::ALL as $type) {
            $keys    = ServiceTypeRegistry::strengthKeys($type);
            $mapping = ServiceTypeRegistry::strengthDomainMapping($type);
            foreach ($keys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $mapping,
                    "{$type}: 強み '{$key}' に対応する domain マッピングがない",
                );
            }
        }
    }

    public function test_employment_a_and_b_share_same_domain_mapping(): void
    {
        // 就労 A/B は領域構成が同じ (実装側が match で同じ枝に乗っている)。
        // 将来分岐を分ける場合はこのテストも分割する。
        $this->assertSame(
            ServiceTypeRegistry::strengthDomainMapping(ServiceTypeRegistry::EMPLOYMENT_A),
            ServiceTypeRegistry::strengthDomainMapping(ServiceTypeRegistry::EMPLOYMENT_B),
        );
    }

    public function test_plan_domains_per_service_type(): void
    {
        // 放デイ: 5 領域
        $afterSchool = ServiceTypeRegistry::planDomains(ServiceTypeRegistry::AFTER_SCHOOL);
        $this->assertCount(5, $afterSchool);
        $this->assertSame('health_life', $afterSchool[0]['key']);
        $this->assertSame('健康・生活',  $afterSchool[0]['label']);

        // 就労 A/B: 6 領域 (同一)
        $a = ServiceTypeRegistry::planDomains(ServiceTypeRegistry::EMPLOYMENT_A);
        $b = ServiceTypeRegistry::planDomains(ServiceTypeRegistry::EMPLOYMENT_B);
        $this->assertCount(6, $a);
        $this->assertSame($a, $b);
        $labels = array_column($a, 'label');
        $this->assertContains('就労スキル',  $labels);
        $this->assertContains('行動特性',    $labels);

        // 就移: 5 領域
        $t = ServiceTypeRegistry::planDomains(ServiceTypeRegistry::TRANSITION);
        $this->assertCount(5, $t);
        $tlabels = array_column($t, 'label');
        $this->assertContains('就職準備',    $tlabels);
        $this->assertContains('自己理解',    $tlabels);
    }

    public function test_terms_for_after_school_uses_education_vocabulary(): void
    {
        $terms = ServiceTypeRegistry::terms(ServiceTypeRegistry::AFTER_SCHOOL);
        $this->assertSame([
            'client'          => '生徒',
            'client_plural'   => '生徒',
            'guardian'        => '保護者',
            'facility_role'   => '児童発達支援施設',
            'service_manager' => '児童発達支援管理責任者',
            'diary'           => '連絡帳',
        ], $terms);
    }

    public function test_terms_for_employment_uses_user_vocabulary(): void
    {
        // 就労 A/B は呼称セットが同じ
        $a = ServiceTypeRegistry::terms(ServiceTypeRegistry::EMPLOYMENT_A);
        $b = ServiceTypeRegistry::terms(ServiceTypeRegistry::EMPLOYMENT_B);
        $this->assertSame($a, $b);

        $this->assertSame('利用者',           $a['client']);
        $this->assertSame('家族',             $a['guardian']);
        $this->assertSame('就労継続支援事業所', $a['facility_role']);
        $this->assertSame('サービス管理責任者', $a['service_manager']);
        $this->assertSame('利用者日誌',       $a['diary']);
    }

    public function test_terms_for_transition_uses_transition_facility_role(): void
    {
        $t = ServiceTypeRegistry::terms(ServiceTypeRegistry::TRANSITION);
        $this->assertSame('利用者',           $t['client']);
        $this->assertSame('家族',             $t['guardian']);
        $this->assertSame('就労移行支援事業所', $t['facility_role']);
        $this->assertSame('サービス管理責任者', $t['service_manager']);
    }

    public function test_terms_unknown_falls_back_to_after_school(): void
    {
        $this->assertSame(
            ServiceTypeRegistry::terms(ServiceTypeRegistry::AFTER_SCHOOL),
            ServiceTypeRegistry::terms('-unknown-'),
        );
    }

    public function test_ai_service_focus_contains_service_specific_keywords(): void
    {
        // AI プロンプトの視点説明に種別固有のキーワードが含まれていることを検証する
        $this->assertStringContainsString('5領域',      ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::AFTER_SCHOOL));
        $this->assertStringContainsString('健康・生活', ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::AFTER_SCHOOL));

        $this->assertStringContainsString('工賃',     ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::EMPLOYMENT_A));
        $this->assertStringContainsString('一般就労', ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::EMPLOYMENT_A));

        $this->assertStringContainsString('A型',     ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::EMPLOYMENT_B));

        $this->assertStringContainsString('就職準備',     ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::TRANSITION));
        $this->assertStringContainsString('ビジネスマナー', ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::TRANSITION));
    }

    public function test_ai_service_focus_unknown_falls_back_to_after_school(): void
    {
        $this->assertSame(
            ServiceTypeRegistry::aiServiceFocus(ServiceTypeRegistry::AFTER_SCHOOL),
            ServiceTypeRegistry::aiServiceFocus('garbage'),
        );
    }
}
