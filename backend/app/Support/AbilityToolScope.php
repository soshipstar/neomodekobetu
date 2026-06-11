<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * 能力評価: 児童ごとに「どの評価ツールを出題対象とするか」と「ツール別の評価軸」を決める。
 *
 * 評価表マスタの「対象」に準拠した年齢/学年での出し分け(ユーザー合意):
 *  - DEV(発達段階別): 全児童。軸=成長段階 S1〜S6([[AbilityGrowthStage]] で判定)
 *  - ADV(高卒標準・発展/学力・強み): 全児童。軸=到達水準 L2(高卒標準)を既定の目安とする
 *  - WRK(就業) / UNV(大学・研究): 中学生以上(S4〜)のみ。軸=時期 中学期P1 / 高校期P2
 *
 * これにより個別支援計画の中核項目(DEV)以外の就業・学力・進学の指標も設問として出題し、
 * 別添資料(全体像)に反映できる。
 */
class AbilityToolScope
{
    /**
     * 児童に出題するツールID群を返す。
     *
     * @return array<int, string>
     */
    public static function toolsFor(Student $student, ?Carbon $asOf = null): array
    {
        $tools = ['DEV', 'ADV'];

        if (self::isJuniorHighOrAbove($student, $asOf)) {
            $tools[] = 'WRK';
            $tools[] = 'UNV';
        }

        return $tools;
    }

    /**
     * ツール別に、出題/採点で用いる評価軸ID(到達目安の参照軸)を返す。
     */
    public static function axisFor(Student $student, string $toolId, ?Carbon $asOf = null): string
    {
        return match ($toolId) {
            'ADV' => 'L2', // 高卒標準を既定の目安にする
            'WRK', 'UNV' => self::isHighSchool($student, $asOf) ? 'P2' : 'P1',
            default => AbilityGrowthStage::forStudent($student, $asOf), // DEV: 成長段階
        };
    }

    private static function isJuniorHighOrAbove(Student $student, ?Carbon $asOf): bool
    {
        return in_array(AbilityGrowthStage::forStudent($student, $asOf), ['S4', 'S5', 'S6'], true);
    }

    private static function isHighSchool(Student $student, ?Carbon $asOf): bool
    {
        return in_array(AbilityGrowthStage::forStudent($student, $asOf), ['S5', 'S6'], true);
    }
}
