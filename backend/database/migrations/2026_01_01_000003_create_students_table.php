<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('student_name', 100);
            $table->string('grade_level', 20)->default('elementary')->comment('elementary, junior_high, high_school');
            $table->integer('grade_adjustment')->default(0);
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('birth_date')->nullable();
            $table->date('support_start_date')->nullable();
            $table->date('kakehashi_initial_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('active')->comment('trial, active, short_term, withdrawn');
            $table->date('withdrawal_date')->nullable();
            $table->boolean('scheduled_monday')->default(false);
            $table->boolean('scheduled_tuesday')->default(false);
            $table->boolean('scheduled_wednesday')->default(false);
            $table->boolean('scheduled_thursday')->default(false);
            $table->boolean('scheduled_friday')->default(false);
            $table->boolean('scheduled_saturday')->default(false);
            $table->boolean('scheduled_sunday')->default(false);
            $table->string('password_plain', 255)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->boolean('hide_initial_monitoring')->default(false);
            $table->date('desired_start_date')->nullable()->comment('希望開始日');
            $table->integer('desired_weekly_count')->nullable()->comment('希望週回数');
            $table->text('waiting_notes')->nullable()->comment('待機メモ');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_grade_level_check CHECK (grade_level IN ('preschool','elementary','elementary_1','elementary_2','elementary_3','elementary_4','elementary_5','elementary_6','junior_high','junior_high_1','junior_high_2','junior_high_3','high_school','high_school_1','high_school_2','high_school_3'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('trial', 'active', 'short_term', 'withdrawn', 'waiting'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
