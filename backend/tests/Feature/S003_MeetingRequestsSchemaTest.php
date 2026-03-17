<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class S003_MeetingRequestsSchemaTest extends TestCase
{
    /**
     * S-003: Verify meeting_requests has all 6 new columns.
     */
    public function test_meeting_requests_has_confirmed_by_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('meeting_requests', 'confirmed_by'),
            'Column confirmed_by should exist on meeting_requests'
        );
    }

    public function test_meeting_requests_has_confirmed_at_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('meeting_requests', 'confirmed_at'),
            'Column confirmed_at should exist on meeting_requests'
        );
    }

    public function test_meeting_requests_has_is_completed_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('meeting_requests', 'is_completed'),
            'Column is_completed should exist on meeting_requests'
        );
    }

    public function test_meeting_requests_has_completed_at_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('meeting_requests', 'completed_at'),
            'Column completed_at should exist on meeting_requests'
        );
    }

    public function test_meeting_requests_has_guardian_counter_message_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('meeting_requests', 'guardian_counter_message'),
            'Column guardian_counter_message should exist on meeting_requests'
        );
    }

    public function test_meeting_requests_has_staff_counter_message_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('meeting_requests', 'staff_counter_message'),
            'Column staff_counter_message should exist on meeting_requests'
        );
    }

    /**
     * Verify all expected columns exist on meeting_requests.
     */
    public function test_meeting_requests_has_all_columns(): void
    {
        $expectedColumns = [
            'id', 'classroom_id', 'student_id', 'guardian_id', 'staff_id',
            'purpose', 'purpose_detail', 'meeting_notes', 'meeting_guidance',
            'related_plan_id', 'related_monitoring_id',
            'candidate_dates', 'confirmed_date', 'status',
            'confirmed_by', 'confirmed_at', 'is_completed', 'completed_at',
            'guardian_counter_message', 'staff_counter_message',
            'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('meeting_requests', $column),
                "Column {$column} should exist on meeting_requests"
            );
        }
    }

    /**
     * Verify model fillable includes new columns.
     */
    public function test_model_fillable_includes_new_columns(): void
    {
        $model = new \App\Models\MeetingRequest();
        $fillable = $model->getFillable();

        $this->assertContains('confirmed_by', $fillable);
        $this->assertContains('confirmed_at', $fillable);
        $this->assertContains('is_completed', $fillable);
        $this->assertContains('completed_at', $fillable);
        $this->assertContains('guardian_counter_message', $fillable);
        $this->assertContains('staff_counter_message', $fillable);
    }

    /**
     * Verify model casts.
     */
    public function test_model_casts_are_correct(): void
    {
        $model = new \App\Models\MeetingRequest();
        $casts = $model->getCasts();

        $this->assertEquals('boolean', $casts['is_completed'] ?? null);
        $this->assertEquals('datetime', $casts['confirmed_at'] ?? null);
        $this->assertEquals('datetime', $casts['completed_at'] ?? null);
    }
}
