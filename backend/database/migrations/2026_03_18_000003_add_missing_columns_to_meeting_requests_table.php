<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S-003: Ensure meeting_requests has all required columns.
     *
     * These were already added in 2026_03_11_100000_add_missing_legacy_columns,
     * but this migration ensures idempotency in case that migration was not run.
     */
    public function up(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_requests', 'confirmed_by')) {
                $table->string('confirmed_by')->nullable();
            }
            if (! Schema::hasColumn('meeting_requests', 'confirmed_at')) {
                $table->timestampTz('confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_requests', 'is_completed')) {
                $table->boolean('is_completed')->default(false);
            }
            if (! Schema::hasColumn('meeting_requests', 'completed_at')) {
                $table->timestampTz('completed_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_requests', 'guardian_counter_message')) {
                $table->text('guardian_counter_message')->nullable();
            }
            if (! Schema::hasColumn('meeting_requests', 'staff_counter_message')) {
                $table->text('staff_counter_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table) {
            $cols = ['confirmed_by', 'confirmed_at', 'is_completed', 'completed_at', 'guardian_counter_message', 'staff_counter_message'];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('meeting_requests', $c));
            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
