<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old check constraint and recreate with new status
        DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS students_status_check");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status::text = ANY (ARRAY['trial','active','short_term','withdrawn','waiting','pre_withdrawal']))");

        // Add withdrawal_reason column
        Schema::table('students', function (Blueprint $table) {
            $table->text('withdrawal_reason')->nullable()->after('waiting_notes');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('withdrawal_reason');
        });

        DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS students_status_check");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status::text = ANY (ARRAY['trial','active','short_term','withdrawn','waiting']))");
    }
};
