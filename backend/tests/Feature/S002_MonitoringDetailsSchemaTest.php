<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class S002_MonitoringDetailsSchemaTest extends TestCase
{
    /**
     * S-002: Verify monitoring_details has plan_detail_id column.
     */
    public function test_monitoring_details_has_plan_detail_id_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('monitoring_details', 'plan_detail_id'),
            'Column plan_detail_id should exist on monitoring_details'
        );
    }

    /**
     * S-002: Verify monitoring_details has timestamps.
     */
    public function test_monitoring_details_has_timestamps(): void
    {
        $this->assertTrue(
            Schema::hasColumn('monitoring_details', 'created_at'),
            'Column created_at should exist on monitoring_details'
        );
        $this->assertTrue(
            Schema::hasColumn('monitoring_details', 'updated_at'),
            'Column updated_at should exist on monitoring_details'
        );
    }

    /**
     * Verify all expected columns exist.
     */
    public function test_monitoring_details_has_all_columns(): void
    {
        $expectedColumns = [
            'id', 'monitoring_id', 'domain', 'achievement_level',
            'comment', 'next_action', 'sort_order',
            'plan_detail_id', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('monitoring_details', $column),
                "Column {$column} should exist on monitoring_details"
            );
        }
    }

    /**
     * Verify model fillable includes plan_detail_id.
     */
    public function test_model_fillable_includes_plan_detail_id(): void
    {
        $model = new \App\Models\MonitoringDetail();
        $fillable = $model->getFillable();

        $this->assertContains('plan_detail_id', $fillable);
    }
}
