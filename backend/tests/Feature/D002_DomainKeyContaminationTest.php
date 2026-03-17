<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D-002: Verify no domain key names appear as values in student_records domain columns.
 *
 * The 5 domain columns (health_life, motor_sensory, cognitive_behavior,
 * language_communication, social_relations) should contain observation text,
 * not the key names of other domains.
 */
class D002_DomainKeyContaminationTest extends TestCase
{
    private array $domainColumns = [
        'health_life',
        'motor_sensory',
        'cognitive_behavior',
        'language_communication',
        'social_relations',
    ];

    /**
     * No domain column should contain a domain key name as its value.
     */
    public function test_no_domain_key_names_as_values(): void
    {
        $domainKeys = $this->domainColumns;
        $violations = [];

        foreach ($this->domainColumns as $column) {
            $count = DB::table('student_records')
                ->whereIn($column, $domainKeys)
                ->count();

            if ($count > 0) {
                $values = DB::table('student_records')
                    ->select('id', $column)
                    ->whereIn($column, $domainKeys)
                    ->limit(5)
                    ->get();

                $examples = $values->map(fn($r) => "id={$r->id}: {$r->$column}")->implode(', ');
                $violations[] = "student_records.$column has $count rows with domain key name values ($examples)";
            }
        }

        $this->assertEmpty(
            $violations,
            "Found domain key name contamination:\n" . implode("\n", $violations)
        );
    }

    /**
     * Verify the conversion script has domain contamination protection.
     */
    public function test_conversion_script_has_domain_sanitization(): void
    {
        $scriptPath = base_path('../convert_mysql_to_pg.py');
        if (!file_exists($scriptPath)) {
            $this->markTestSkipped('convert_mysql_to_pg.py not available in this environment');
        }
        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString(
            'DOMAIN_KEY_NAMES',
            $content,
            'convert_mysql_to_pg.py should define DOMAIN_KEY_NAMES'
        );

        $this->assertStringContainsString(
            'sanitize_domain_value',
            $content,
            'convert_mysql_to_pg.py should have sanitize_domain_value function'
        );
    }
}
