<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * 能力評価: 児童の成長段階軸(DEV ツールの S1〜S6)を判定する。
 *
 * 判定優先順:
 *  1. grade_level が学年単位(elementary_3 等)で設定済みならそれを使う(支援者の明示入力)
 *  2. birth_date があれば日本の学年(年度=4月始まり)を計算し grade_adjustment を加味
 *  3. 粗い grade_level(elementary/junior_high/high_school)から既定段階
 *  4. いずれも無ければ中間の S3
 *
 * 段階対応: S1=小低(1・2年) S2=小中(3・4年) S3=小高(5・6年) S4=中学 S5=高1 S6=高2・3。
 */
class AbilityGrowthStage
{
    /** 児童の成長段階軸ID(S1〜S6)を返す。 */
    public static function forStudent(Student $student, ?Carbon $asOf = null): string
    {
        // 1) 学年単位の grade_level
        if ($student->grade_level && ($stage = self::stageFromGradeLevel($student->grade_level)) !== null) {
            return $stage;
        }

        // 2) birth_date から学年を計算
        if ($student->birth_date) {
            $grade = self::japaneseGrade(Carbon::parse($student->birth_date), $asOf ?? Carbon::now());
            $grade += (int) ($student->grade_adjustment ?? 0);

            return self::stageFromGrade($grade);
        }

        // 3) 粗い grade_level の既定
        return match ($student->grade_level) {
            'junior_high' => 'S4',
            'high_school' => 'S5',
            'preschool' => 'S1',
            'elementary' => 'S2',
            default => 'S3',
        };
    }

    /**
     * 日本の学年(1=小1 〜 12=高3)を年度(4月始まり)で計算する。
     * 早生まれ(1〜3月)は前年度の学年集団に属するものとして年度を1つ繰り下げる。
     */
    public static function japaneseGrade(Carbon $birth, Carbon $asOf): int
    {
        $birthNendo = $birth->month <= 3 ? $birth->year - 1 : $birth->year;
        $currentNendo = $asOf->month <= 3 ? $asOf->year - 1 : $asOf->year;

        return $currentNendo - $birthNendo - 6 + 1; // 小1 のとき 1
    }

    /** 学年(1〜12)→ 成長段階軸。範囲外はクランプ。 */
    private static function stageFromGrade(int $grade): string
    {
        return match (true) {
            $grade <= 2 => 'S1',
            $grade <= 4 => 'S2',
            $grade <= 6 => 'S3',
            $grade <= 9 => 'S4',
            $grade === 10 => 'S5',
            default => 'S6',
        };
    }

    /** 学年単位の grade_level 文字列 → 成長段階軸。学年単位でなければ null。 */
    private static function stageFromGradeLevel(string $gradeLevel): ?string
    {
        return match ($gradeLevel) {
            'elementary_1', 'elementary_2' => 'S1',
            'elementary_3', 'elementary_4' => 'S2',
            'elementary_5', 'elementary_6' => 'S3',
            'junior_high_1', 'junior_high_2', 'junior_high_3' => 'S4',
            'high_school_1' => 'S5',
            'high_school_2', 'high_school_3' => 'S6',
            default => null,
        };
    }
}
