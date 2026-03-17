<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S-005: Add classroom_id to facility_evaluation_periods.
     *
     * The legacy MySQL table had classroom_id but it was stripped during
     * conversion (listed in STRIP_COLUMNS). This re-adds it so that
     * evaluations can be scoped per classroom.
     *
     * Also drops the unique constraint on fiscal_year since multiple
     * classrooms can have periods for the same fiscal year.
     */
    public function up(): void
    {
        Schema::table('facility_evaluation_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_evaluation_periods', 'classroom_id')) {
                $table->foreignId('classroom_id')->nullable()->after('id')
                    ->constrained('classrooms')->cascadeOnDelete();
            }
        });

        // Drop the unique constraint on fiscal_year since multiple classrooms
        // can have evaluation periods for the same fiscal year.
        try {
            Schema::table('facility_evaluation_periods', function (Blueprint $table) {
                $table->dropUnique(['fiscal_year']);
            });
        } catch (\Exception $e) {
            // Constraint may not exist
        }

        // Add a composite unique constraint instead
        try {
            Schema::table('facility_evaluation_periods', function (Blueprint $table) {
                $table->unique(['fiscal_year', 'classroom_id'], 'fep_fiscal_year_classroom_unique');
            });
        } catch (\Exception $e) {
            // Constraint may already exist
        }
    }

    public function down(): void
    {
        try {
            Schema::table('facility_evaluation_periods', function (Blueprint $table) {
                $table->dropUnique('fep_fiscal_year_classroom_unique');
            });
        } catch (\Exception $e) {
            // ignore
        }

        Schema::table('facility_evaluation_periods', function (Blueprint $table) {
            if (Schema::hasColumn('facility_evaluation_periods', 'classroom_id')) {
                $table->dropForeign(['classroom_id']);
                $table->dropColumn('classroom_id');
            }
        });

        try {
            Schema::table('facility_evaluation_periods', function (Blueprint $table) {
                $table->unique('fiscal_year');
            });
        } catch (\Exception $e) {
            // ignore
        }
    }
};
