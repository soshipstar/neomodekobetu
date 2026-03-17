<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D-003: Verify daily_records.classroom_id is not NULL.
 *
 * After migration, classroom_id should be derived from the staff member's
 * classroom assignment (staff_id -> users.classroom_id).
 */
class D003_DailyRecordsClassroomIdTest extends TestCase
{
    /**
     * No daily_records should have NULL classroom_id when staff_id is set.
     */
    public function test_no_null_classroom_id_with_valid_staff(): void
    {
        $nullCount = DB::table('daily_records')
            ->whereNull('classroom_id')
            ->whereNotNull('staff_id')
            ->count();

        $this->assertEquals(
            0,
            $nullCount,
            "Found $nullCount daily_records with NULL classroom_id but valid staff_id. "
            . 'Run: UPDATE daily_records dr SET classroom_id = u.classroom_id FROM users u WHERE u.id = dr.staff_id AND dr.classroom_id IS NULL;'
        );
    }

    /**
     * Verify the conversion script includes post-migration SQL for classroom_id.
     */
    public function test_conversion_script_has_post_migration_fix(): void
    {
        $scriptPath = base_path('../convert_mysql_to_pg.py');
        if (!file_exists($scriptPath)) {
            $this->markTestSkipped('convert_mysql_to_pg.py not available in this environment');
        }
        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString(
            'POST_MIGRATION_SQL',
            $content,
            'convert_mysql_to_pg.py should define POST_MIGRATION_SQL'
        );

        $this->assertStringContainsString(
            'daily_records',
            $content,
            'POST_MIGRATION_SQL should reference daily_records'
        );

        $this->assertStringContainsString(
            'classroom_id = u.classroom_id',
            $content,
            'POST_MIGRATION_SQL should derive classroom_id from users table'
        );
    }
}
