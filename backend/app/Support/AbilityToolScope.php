<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * 能力評価: 児童ごとに「どの評価ツールを出題対象とするか」と「ツール別の評価軸」を決める。
 *
 * 出し分け方針(現場フィードバック 2026-06-24 で見直し):
 *  - DEV(発達段階別・5領域): 全児童の主軸。日々の連絡帳の設問はここを中心に出題する。
 *    軸=成長段階。発達段階別評価(放デイ無学年)の趣旨に沿い、小学校低学年(S1)から開始する。
 *    ※達成(スコア≥8)に応じて S2→S3… と段階を上げる自動進行は次フェーズで対応。
 *  - ADV(高卒標準・発展/学力)・WRK(就業)・UNV(大学・研究): 高校段階(S5以上)に達した
 *    児童のみ出題する。中学生以下に高卒標準・科目指導的な設問を出さない(現場要望)。
 *    軸=ADVは到達水準 L2(高卒標準)、WRK/UNVは高校期 P2。
 *
 * DEV 以外(就業・学力・進学)の指標も、高校段階の児童には設問・別添(全体像)へ反映できる。
 */
class AbilityToolScope
{
    /**
     * 到達しきい値: この点以上で当該学年帯(軸)を「到達」とみなし、次の段階へ前進する。
     * 8 = 「一人で安定してできる(慣れた場面で9割以上・支援なし)」。
     */
    public const ACHIEVED_THRESHOLD = 8;

    /**
     * ツール別の評価軸を「低い→高い」順に並べたもの(到達マップの列＝段階進行の順序)。
     *  - DEV: 成長段階 S1(小低)〜S6(高後)
     *  - ADV: 到達水準 L1(基礎)〜L4(卓越)
     *  - WRK/UNV: 到達目安マスタが無いため段階進行せず、高校期 P2 の単一軸で扱う。
     */
    public const TOOL_AXES = [
        'DEV' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'],
        'ADV' => ['L1', 'L2', 'L3', 'L4'],
        'WRK' => ['P2'],
        'UNV' => ['P2'],
    ];

    /**
     * ツールの評価軸を低い順に返す。
     *
     * @return array<int, string>
     */
    public static function axesForTool(string $toolId): array
    {
        return self::TOOL_AXES[$toolId] ?? ['S1'];
    }

    /**
     * 児童に出題するツールID群を返す。
     *
     * DEV は全児童の主軸。ADV/WRK/UNV は高校段階(S5以上)に達した児童のみ。
     *
     * @return array<int, string>
     */
    public static function toolsFor(Student $student, ?Carbon $asOf = null): array
    {
        if (self::isHighSchool($student, $asOf)) {
            return ['DEV', 'ADV', 'WRK', 'UNV'];
        }

        return ['DEV'];
    }

    /**
     * ツールの開始軸(最も低い段階/水準)を返す。
     * スコアを参照した実際の出題軸(到達で前進)は [[AbilityQuestionService]]::currentAxisFor。
     */
    public static function axisFor(Student $student, string $toolId, ?Carbon $asOf = null): string
    {
        return self::axesForTool($toolId)[0];
    }

    private static function isHighSchool(Student $student, ?Carbon $asOf): bool
    {
        return in_array(AbilityGrowthStage::forStudent($student, $asOf), ['S5', 'S6'], true);
    }
}
