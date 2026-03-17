<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D-004: Verify weekly_plans.student_id is not NULL.
 *
 * A previous bug in convert_mysql_to_pg.py renamed student_id -> classroom_id,
 * which has been fixed. This test verifies no NULL student_id values remain.
 */
class D004_WeeklyPlansStudentIdTest extends TestCase
{
    /**
     * No weekly_plans should have NULL student_id.
     */
    public function test_no_null_student_id(): void
    {
        $nullCount = DB::table('weekly_plans')
            ->whereNull('student_id')
            ->count();

        $this->assertEquals(
            0,
            $nullCount,
            "Found $nullCount weekly_plans with NULL student_id. "
            . 'The student_id -> classroom_id rename bug may need re-import.'
        );
    }

    /**
     * Verify the conversion script no longer renames student_id to classroom_id.
     */
    public function test_conversion_script_preserves_student_id(): void
    {
        $scriptPath = base_path('../convert_mysql_to_pg.py');
        if (!file_exists($scriptPath)) {
            $this->markTestSkipped('convert_mysql_to_pg.py not available in this environment');
        }
        $content = file_get_contents($scriptPath);

        // The script should NOT have 'student_id': 'classroom_id' in weekly_plans renames
        $this->assertStringNotContainsString(
            "'student_id': 'classroom_id'",
            $content,
            'convert_mysql_to_pg.py should NOT rename student_id to classroom_id for weekly_plans'
        );

        // It should have a comment indicating the fix
        $this->assertStringContainsString(
            'student_id is now kept as-is',
            $content,
            'convert_mysql_to_pg.py should note that student_id is preserved'
        );
    }
}
