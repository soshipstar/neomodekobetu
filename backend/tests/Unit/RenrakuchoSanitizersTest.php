<?php

namespace Tests\Unit;

use App\Http\Controllers\Staff\RenrakuchoController;
use App\Services\ServiceTypeRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * U002 RenrakuchoController::sanitizeStrengths / sanitizeServiceTypeData
 *
 * 差分カテゴリ: api / logic
 * 背景: 連絡帳・タブレット経由の入力は信頼できないため、サービス種別ごとに
 *       強み(才能)チェックは「許可キーのみ」「0-10 にクランプ」、
 *       service_type_data は「型別バリデーション」を行う。
 *       これらは保存パスの最後の砦なので、ホワイトリストの抜けや型変換の
 *       バグを直接ユニットテストで固定する。
 *
 * 同等のヘルパが TabletController にも複製されているが、本テストは
 * 仕様の正典として RenrakuchoController 側だけを検証する。
 */
class RenrakuchoSanitizersTest extends TestCase
{
    private function invokeSanitizeStrengths(?array $payload, ?string $serviceType = null): ?array
    {
        $controller = new RenrakuchoController();
        $method = (new ReflectionClass($controller))->getMethod('sanitizeStrengths');
        $method->setAccessible(true);
        return $method->invoke($controller, $payload, $serviceType);
    }

    private function invokeSanitizeServiceTypeData(?array $payload, string $serviceType): ?array
    {
        $controller = new RenrakuchoController();
        $method = (new ReflectionClass($controller))->getMethod('sanitizeServiceTypeData');
        $method->setAccessible(true);
        return $method->invoke($controller, $payload, $serviceType);
    }

    // =========================================================================
    // sanitizeStrengths
    // =========================================================================

    public function test_sanitize_strengths_returns_null_for_empty_input(): void
    {
        $this->assertNull($this->invokeSanitizeStrengths(null));
        $this->assertNull($this->invokeSanitizeStrengths([]));
    }

    public function test_sanitize_strengths_drops_unknown_keys_for_after_school(): void
    {
        $payload = [
            '集中力'       => 7,
            '持続力'       => 5,
            '不正キー'     => 9,           // 想定外キーは破棄
            '報連相'       => 8,           // 就労 A の項目は after_school では破棄
        ];
        $sanitized = $this->invokeSanitizeStrengths($payload, ServiceTypeRegistry::AFTER_SCHOOL);

        $this->assertSame(['集中力' => 7, '持続力' => 5], $sanitized);
        $this->assertArrayNotHasKey('不正キー', $sanitized);
        $this->assertArrayNotHasKey('報連相',   $sanitized);
    }

    public function test_sanitize_strengths_clamps_values_to_zero_to_ten(): void
    {
        $sanitized = $this->invokeSanitizeStrengths([
            '集中力' => 11,    // 上限超え → 10
            '持続力' => -3,    // 下限割れ → 0
            '丁寧さ' => '7',   // 数値文字列は許容
            '発想力' => 4.9,   // 小数は intval 切り捨て
        ], ServiceTypeRegistry::AFTER_SCHOOL);

        $this->assertSame(10, $sanitized['集中力']);
        $this->assertSame(0,  $sanitized['持続力']);
        $this->assertSame(7,  $sanitized['丁寧さ']);
        $this->assertSame(4,  $sanitized['発想力']);
    }

    public function test_sanitize_strengths_skips_null_and_empty_string(): void
    {
        $sanitized = $this->invokeSanitizeStrengths([
            '集中力' => 5,
            '持続力' => null,
            '丁寧さ' => '',
        ], ServiceTypeRegistry::AFTER_SCHOOL);

        $this->assertSame(['集中力' => 5], $sanitized);
    }

    public function test_sanitize_strengths_returns_null_when_all_filtered_out(): void
    {
        // 全項目が想定外キー → 空になり null を返す
        $sanitized = $this->invokeSanitizeStrengths([
            'unknown1' => 5,
            'unknown2' => 8,
        ], ServiceTypeRegistry::AFTER_SCHOOL);
        $this->assertNull($sanitized);
    }

    public function test_sanitize_strengths_uses_employment_a_keys(): void
    {
        $sanitized = $this->invokeSanitizeStrengths([
            '正確性'  => 8,    // 就労 A の項目
            '報連相'  => 9,    // 就労 A の項目
            '思いやり' => 6,   // 放デイ専用 → 破棄
        ], ServiceTypeRegistry::EMPLOYMENT_A);

        $this->assertSame(['正確性' => 8, '報連相' => 9], $sanitized);
    }

    public function test_sanitize_strengths_uses_transition_keys(): void
    {
        $sanitized = $this->invokeSanitizeStrengths([
            '自己理解'       => 7,
            'ビジネスマナー' => 4,
            '集中力'         => 9,   // 就移には存在しない → 破棄
        ], ServiceTypeRegistry::TRANSITION);

        $this->assertSame(['自己理解' => 7, 'ビジネスマナー' => 4], $sanitized);
    }

    public function test_sanitize_strengths_defaults_to_after_school_when_service_type_omitted(): void
    {
        // 第二引数省略時は after_school キー一覧で判定される
        $sanitized = $this->invokeSanitizeStrengths(['集中力' => 6, '報連相' => 5]);
        $this->assertSame(['集中力' => 6], $sanitized);
    }

    // =========================================================================
    // sanitizeServiceTypeData
    // =========================================================================

    public function test_sanitize_service_type_data_returns_null_for_after_school(): void
    {
        // after_school は固有データなしのため、何を投げても null
        $result = $this->invokeSanitizeServiceTypeData(
            ['wage_eligible_hours' => 4.5, 'work_content' => 'test'],
            ServiceTypeRegistry::AFTER_SCHOOL,
        );
        $this->assertNull($result);
    }

    public function test_sanitize_service_type_data_returns_null_for_empty_input(): void
    {
        $this->assertNull($this->invokeSanitizeServiceTypeData(null, ServiceTypeRegistry::EMPLOYMENT_A));
        $this->assertNull($this->invokeSanitizeServiceTypeData([], ServiceTypeRegistry::EMPLOYMENT_A));
    }

    public function test_sanitize_service_type_data_employment_keeps_known_keys_and_types(): void
    {
        $result = $this->invokeSanitizeServiceTypeData([
            'wage_eligible_hours' => '4.5',          // float (数値文字列)
            'clock_in'            => '09:00',         // time
            'clock_out'           => '16:30',         // time
            'work_content'        => '  袋詰め作業  ', // string (trim)
            'evil'                => 'should be dropped',
        ], ServiceTypeRegistry::EMPLOYMENT_A);

        $this->assertSame(4.5,       $result['wage_eligible_hours']);
        $this->assertSame('09:00',   $result['clock_in']);
        $this->assertSame('16:30',   $result['clock_out']);
        $this->assertSame('袋詰め作業', $result['work_content']);
        $this->assertArrayNotHasKey('evil', $result);
    }

    public function test_sanitize_service_type_data_employment_drops_invalid_time(): void
    {
        $result = $this->invokeSanitizeServiceTypeData([
            'clock_in'  => 'AM 9:00',       // 形式不一致 → 破棄
            'clock_out' => '17時00分',      // 形式不一致 → 破棄
        ], ServiceTypeRegistry::EMPLOYMENT_B);

        $this->assertNull($result);  // 全項目が破棄され null
    }

    public function test_sanitize_service_type_data_employment_drops_non_numeric_wage(): void
    {
        $result = $this->invokeSanitizeServiceTypeData([
            'wage_eligible_hours' => 'four hours',
            'work_content'        => '梱包',
        ], ServiceTypeRegistry::EMPLOYMENT_B);

        $this->assertSame(['work_content' => '梱包'], $result);
        $this->assertArrayNotHasKey('wage_eligible_hours', $result);
    }

    public function test_sanitize_service_type_data_employment_drops_empty_string_work_content(): void
    {
        $result = $this->invokeSanitizeServiceTypeData([
            'work_content' => '   ',  // trim 後に空 → 破棄
            'clock_in'     => '08:00',
        ], ServiceTypeRegistry::EMPLOYMENT_A);

        $this->assertSame(['clock_in' => '08:00'], $result);
    }

    public function test_sanitize_service_type_data_transition_clamps_business_manner_score(): void
    {
        // business_manner_score は 1-5 にクランプ
        $result = $this->invokeSanitizeServiceTypeData([
            'practice_content'      => '○○商事 接客実習',
            'job_search_record'     => 'ハローワーク訪問',
            'business_manner_score' => 8,    // 上限超え → 5
        ], ServiceTypeRegistry::TRANSITION);

        $this->assertSame('○○商事 接客実習', $result['practice_content']);
        $this->assertSame('ハローワーク訪問',   $result['job_search_record']);
        $this->assertSame(5,                   $result['business_manner_score']);

        $low = $this->invokeSanitizeServiceTypeData(['business_manner_score' => 0], ServiceTypeRegistry::TRANSITION);
        $this->assertSame(1, $low['business_manner_score']);

        $negative = $this->invokeSanitizeServiceTypeData(['business_manner_score' => -10], ServiceTypeRegistry::TRANSITION);
        $this->assertSame(1, $negative['business_manner_score']);
    }

    public function test_sanitize_service_type_data_transition_drops_employment_keys(): void
    {
        // 就労 A 用キーを就移に投げても破棄される (種別ごとの allowed が分離)
        $result = $this->invokeSanitizeServiceTypeData([
            'wage_eligible_hours' => 5,
            'clock_in'            => '09:00',
            'practice_content'    => '実習',
        ], ServiceTypeRegistry::TRANSITION);

        $this->assertSame(['practice_content' => '実習'], $result);
    }
}
