<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class S004_FacilityEvaluationPeriodsSchemaTest extends TestCase
{
    /**
     * S-005: Verify facility_evaluation_periods has classroom_id column.
     */
    public function test_facility_evaluation_periods_has_classroom_id_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('facility_evaluation_periods', 'classroom_id'),
            'Column classroom_id should exist on facility_evaluation_periods'
        );
    }

    /**
     * Verify all expected columns exist.
     */
    public function test_facility_evaluation_periods_has_all_columns(): void
    {
        $expectedColumns = [
            'id', 'classroom_id', 'fiscal_year', 'title', 'status',
            'guardian_deadline', 'staff_deadline', 'created_by',
            'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('facility_evaluation_periods', $column),
                "Column {$column} should exist on facility_evaluation_periods"
            );
        }
    }
}
