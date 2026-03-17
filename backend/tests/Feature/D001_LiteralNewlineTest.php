<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D-001: Verify no literal \r\n (4-byte escape sequences) remain in text fields.
 *
 * After migration, text columns should contain actual newlines (\n),
 * not the literal string '\r\n'.
 */
class D001_LiteralNewlineTest extends TestCase
{
    /**
     * Tables and their text columns to check for literal \r\n.
     */
    private array $tablesToCheck = [
        'integrated_notes' => ['integrated_content'],
        'student_records' => ['health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations', 'notes'],
        'kakehashi_guardian' => ['home_situation', 'concerns', 'requests', 'student_wish', 'home_challenges', 'short_term_goal', 'long_term_goal', 'domain_health_life', 'domain_motor_sensory', 'domain_cognitive_behavior', 'domain_language_communication', 'domain_social_relations', 'other_challenges'],
        'kakehashi_staff' => ['student_wish', 'short_term_goal', 'long_term_goal', 'health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations', 'other_challenges'],
        'individual_support_plans' => ['life_intention', 'overall_policy', 'long_term_goal', 'short_term_goal', 'basis_content', 'guardian_review_comment'],
        'monitoring_records' => ['overall_comment', 'short_term_goal_achievement', 'long_term_goal_achievement', 'short_term_goal_comment', 'long_term_goal_comment'],
        'monitoring_details' => ['comment', 'next_action'],
        'support_plan_details' => ['current_status', 'goal', 'support_content', 'notes'],
        'weekly_plans' => ['weekly_goal', 'shared_goal', 'must_do', 'should_do', 'want_to_do', 'weekly_goal_comment', 'shared_goal_comment', 'must_do_comment', 'should_do_comment', 'want_to_do_comment', 'overall_comment'],
        'newsletters' => ['greeting', 'event_calendar', 'event_details', 'weekly_reports', 'event_results', 'requests', 'others', 'weekly_intro', 'elementary_report', 'junior_report'],
        'chat_messages' => ['message'],
        'events' => ['event_description', 'staff_comment', 'guardian_message'],
        'daily_records' => ['activity_name', 'common_activity'],
    ];

    /**
     * No text column should contain literal \r\n sequences.
     */
    public function test_no_literal_backslash_r_backslash_n_in_text_fields(): void
    {
        $violations = [];

        foreach ($this->tablesToCheck as $table => $columns) {
            foreach ($columns as $column) {
                $count = DB::table($table)
                    ->whereRaw("\"$column\" LIKE E'%\\\\\\\\r\\\\\\\\n%'")
                    ->count();

                if ($count > 0) {
                    $violations[] = "$table.$column has $count rows with literal \\r\\n";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found literal \\r\\n in text fields:\n" . implode("\n", $violations)
        );
    }

    /**
     * Verify the conversion script has the sanitize_text_value function.
     */
    public function test_conversion_script_has_text_sanitization(): void
    {
        $scriptPath = base_path('../convert_mysql_to_pg.py');
        if (!file_exists($scriptPath)) {
            $this->markTestSkipped('convert_mysql_to_pg.py not available in this environment');
        }
        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString(
            'sanitize_text_value',
            $content,
            'convert_mysql_to_pg.py should have sanitize_text_value function'
        );

        $this->assertStringContainsString(
            "replace('\\\\r\\\\n', '\\n')",
            $content,
            'sanitize_text_value should replace \\r\\n with actual newline'
        );
    }
}
