<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S-002: Ensure monitoring_details has plan_detail_id and timestamps.
     *
     * These were already added in 2026_03_11_100000_add_missing_legacy_columns,
     * but this migration ensures idempotency in case that migration was not run.
     */
    public function up(): void
    {
        Schema::table('monitoring_details', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_details', 'plan_detail_id')) {
                $table->foreignId('plan_detail_id')->nullable()->constrained('support_plan_details')->nullOnDelete();
            }
            if (! Schema::hasColumn('monitoring_details', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_details', function (Blueprint $table) {
            if (Schema::hasColumn('monitoring_details', 'plan_detail_id')) {
                $table->dropForeign(['plan_detail_id']);
                $table->dropColumn('plan_detail_id');
            }
            if (Schema::hasColumn('monitoring_details', 'created_at')) {
                $table->dropColumn(['created_at', 'updated_at']);
            }
        });
    }
};
