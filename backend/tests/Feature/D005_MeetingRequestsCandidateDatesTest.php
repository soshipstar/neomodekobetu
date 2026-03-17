<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * D-005: Test that convert_mysql_to_pg.py correctly converts
 * candidate_date1/2/3 individual columns into a candidate_dates JSONB array.
 *
 * This test verifies the Python conversion script logic by running it
 * against sample INSERT statements and checking the output.
 */
class D005_MeetingRequestsCandidateDatesTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scriptPath = base_path('../convert_mysql_to_pg.py');
    }

    /**
     * Verify the conversion script exists.
     */
    public function test_conversion_script_exists(): void
    {
        $this->assertFileExists(
            $this->scriptPath,
            'convert_mysql_to_pg.py should exist at project root'
        );
    }

    /**
     * Verify that meeting_requests STRIP_COLUMNS includes candidate_date1/2/3.
     */
    public function test_strip_columns_includes_candidate_dates(): void
    {
        $content = file_get_contents($this->scriptPath);

        $this->assertStringContainsString("'candidate_date1'", $content);
        $this->assertStringContainsString("'candidate_date2'", $content);
        $this->assertStringContainsString("'candidate_date3'", $content);
    }

    /**
     * Verify that meeting_requests has a TRANSFORM_COLUMNS entry for
     * converting candidate_date1/2/3 into candidate_dates JSONB.
     */
    public function test_transform_columns_has_candidate_dates_conversion(): void
    {
        $content = file_get_contents($this->scriptPath);

        // The script should contain logic to transform candidate_date columns
        // into a candidate_dates JSONB array
        $this->assertStringContainsString('candidate_dates', $content);
        $this->assertStringContainsString('TRANSFORM_COLUMNS', $content,
            'Script should have TRANSFORM_COLUMNS config for candidate_dates conversion'
        );
    }

    /**
     * Run the conversion script on a sample meeting_requests INSERT and verify
     * the candidate_dates JSONB output.
     */
    public function test_candidate_dates_conversion_output(): void
    {
        // Create a temp file with a sample MySQL INSERT for meeting_requests
        $sampleSql = <<<'SQL'
INSERT INTO `meeting_requests` (`id`,`classroom_id`,`student_id`,`guardian_id`,`staff_id`,`purpose`,`purpose_detail`,`meeting_notes`,`meeting_guidance`,`related_plan_id`,`related_monitoring_id`,`candidate_date1`,`candidate_date2`,`candidate_date3`,`confirmed_date`,`status`,`is_completed`,`created_at`,`updated_at`) VALUES (1,1,1,2,3,'面談','詳細',NULL,NULL,NULL,NULL,'2026-03-20 10:00:00','2026-03-21 14:00:00',NULL,NULL,'pending',0,'2026-03-01 00:00:00','2026-03-01 00:00:00');
SQL;

        $inputFile = tempnam(sys_get_temp_dir(), 'mysql_test_');
        $outputFile = tempnam(sys_get_temp_dir(), 'pg_test_');
        file_put_contents($inputFile, $sampleSql);

        try {
            // Run the conversion script
            $cmd = sprintf(
                'python3 %s --input %s --output %s --full 2>&1',
                escapeshellarg($this->scriptPath),
                escapeshellarg($inputFile),
                escapeshellarg($outputFile)
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                // Try python instead of python3
                $cmd = sprintf(
                    'python %s --input %s --output %s --full 2>&1',
                    escapeshellarg($this->scriptPath),
                    escapeshellarg($inputFile),
                    escapeshellarg($outputFile)
                );
                exec($cmd, $output, $exitCode);
            }

            $this->assertEquals(0, $exitCode,
                'Conversion script should exit successfully. Output: ' . implode("\n", $output)
            );

            $pgSql = file_get_contents($outputFile);

            // Verify the output contains candidate_dates as a JSONB value
            $this->assertStringContainsString('"candidate_dates"', $pgSql,
                'Output should contain candidate_dates column'
            );

            // The candidate_date1/2/3 columns should NOT appear in the output
            $this->assertStringNotContainsString('"candidate_date1"', $pgSql,
                'Output should NOT contain candidate_date1 column'
            );
            $this->assertStringNotContainsString('"candidate_date2"', $pgSql,
                'Output should NOT contain candidate_date2 column'
            );
            $this->assertStringNotContainsString('"candidate_date3"', $pgSql,
                'Output should NOT contain candidate_date3 column'
            );

            // Verify the JSONB array contains the non-NULL dates
            $this->assertMatchesRegularExpression(
                '/\[.*2026-03-20.*2026-03-21.*\]/',
                $pgSql,
                'Output should contain a JSON array with the two non-NULL candidate dates'
            );

        } finally {
            @unlink($inputFile);
            @unlink($outputFile);
        }
    }
}
