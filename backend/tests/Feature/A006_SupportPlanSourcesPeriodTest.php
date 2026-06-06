<?php

namespace Tests\Feature;

use App\Http\Controllers\Staff\SupportPlanController;
use Tests\TestCase;

/**
 * A-006: 観点7 説明可能性。AI個別支援計画生成が参照した連絡帳の期間(最古〜最新)を
 * 算出する SupportPlanController::dateRange のロジック検証。
 *
 * generateAi 本体は OpenAI を直接呼ぶため統合テストが困難なので、応答 sources に
 * 含める参照期間の算出部分を純粋関数として切り出し、ここで境界値を検証する。
 *
 * 差分カテゴリ: screen (AI生成根拠の表示 / 観点7)
 */
class A006_SupportPlanSourcesPeriodTest extends TestCase
{
    public function test_returns_null_when_no_dates(): void
    {
        $this->assertNull(SupportPlanController::dateRange([]));
        $this->assertNull(SupportPlanController::dateRange([null, '', null]));
    }

    public function test_returns_min_and_max_regardless_of_input_order(): void
    {
        $range = SupportPlanController::dateRange(['2026-05-28', '2026-01-05', '2026-03-10']);

        $this->assertSame(['from' => '2026-01-05', 'to' => '2026-05-28'], $range);
    }

    public function test_normalizes_datetime_strings_to_date(): void
    {
        // record_date が datetime 文字列で来ても先頭10文字へ正規化される
        $range = SupportPlanController::dateRange(['2026-02-01 09:30:00', '2026-04-15 18:00:00']);

        $this->assertSame(['from' => '2026-02-01', 'to' => '2026-04-15'], $range);
    }

    public function test_skips_null_and_empty_values(): void
    {
        $range = SupportPlanController::dateRange([null, '2026-06-01', '', '2026-06-03']);

        $this->assertSame(['from' => '2026-06-01', 'to' => '2026-06-03'], $range);
    }

    public function test_single_date_has_equal_from_and_to(): void
    {
        $range = SupportPlanController::dateRange(['2026-06-06']);

        $this->assertSame(['from' => '2026-06-06', 'to' => '2026-06-06'], $range);
    }
}
