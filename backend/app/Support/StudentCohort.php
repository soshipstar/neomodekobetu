<?php

namespace App\Support;

use App\Models\Student;

/**
 * AI学習基盤 分析次元: 児童の対象コホート(年齢層)を grade_level のプレフィックスから判定する。
 * 未就学/小学生/中学生/高校生/その他 の5区分。成長段階(S1〜S6)は別途 [[AbilityGrowthStage]]。
 */
class StudentCohort
{
    public const PRESCHOOL = 'preschool';
    public const ELEMENTARY = 'elementary';
    public const JUNIOR_HIGH = 'junior_high';
    public const HIGH_SCHOOL = 'high_school';
    public const OTHER = 'other';

    /** grade_level(preschool/elementary_3/junior_high_1/high_school_2 等)→ コホート。 */
    public static function fromGradeLevel(?string $gradeLevel): string
    {
        $g = (string) $gradeLevel;
        if (str_starts_with($g, 'preschool')) {
            return self::PRESCHOOL;
        }
        if (str_starts_with($g, 'elementary')) {
            return self::ELEMENTARY;
        }
        if (str_starts_with($g, 'junior_high')) {
            return self::JUNIOR_HIGH;
        }
        if (str_starts_with($g, 'high_school')) {
            return self::HIGH_SCHOOL;
        }

        return self::OTHER;
    }

    public static function forStudent(Student $student): string
    {
        return self::fromGradeLevel($student->grade_level);
    }
}
