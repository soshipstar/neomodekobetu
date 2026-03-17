<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S-004: Verify chat_messages message_type migration from old format to new format.
 *
 * Old format values (normal, absence_notification, event_registration) must not
 * exist in the database after migration. The convert_mysql_to_pg.py script must
 * contain the VALUE_MAPPINGS configuration for these conversions.
 */
class S004_ChatMessageTypeTest extends TestCase
{
    /**
     * Assert no rows have old-format message_type values.
     */
    public function test_no_old_format_message_types_exist(): void
    {
        $oldTypes = ['normal', 'absence_notification', 'event_registration'];

        $count = DB::table('chat_messages')
            ->whereIn('message_type', $oldTypes)
            ->count();

        $this->assertEquals(
            0,
            $count,
            "Found {$count} chat_messages with old-format message_type values. "
            . "Run the S-004 migration SQL to fix: "
            . "UPDATE chat_messages SET message_type = 'text' WHERE message_type IN ('normal','absence_notification','event_registration');"
        );
    }

    /**
     * Assert all message_type values are valid new-format values.
     */
    public function test_all_message_types_are_valid(): void
    {
        $validTypes = ['text', 'image', 'file', 'meeting_request', 'meeting_counter', 'meeting_confirmed', 'broadcast'];

        $invalidCount = DB::table('chat_messages')
            ->whereNotIn('message_type', $validTypes)
            ->count();

        $this->assertEquals(
            0,
            $invalidCount,
            "Found {$invalidCount} chat_messages with invalid message_type values."
        );
    }

    /**
     * Assert convert_mysql_to_pg.py contains the VALUE_MAPPINGS for message_type.
     */
    public function test_migration_script_contains_value_mappings(): void
    {
        $scriptPath = base_path('../convert_mysql_to_pg.py');

        if (! file_exists($scriptPath)) {
            $this->markTestSkipped('convert_mysql_to_pg.py not found at project root.');
        }

        $content = file_get_contents($scriptPath);

        $this->assertStringContainsString('VALUE_MAPPINGS', $content, 'Script must define VALUE_MAPPINGS configuration.');
        $this->assertStringContainsString("'normal': 'text'", $content, 'Script must map normal -> text.');
        $this->assertStringContainsString("'absence_notification': 'text'", $content, 'Script must map absence_notification -> text.');
        $this->assertStringContainsString("'event_registration': 'text'", $content, 'Script must map event_registration -> text.');
    }
}
