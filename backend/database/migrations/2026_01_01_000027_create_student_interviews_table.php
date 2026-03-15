<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->date('interview_date');
            $table->foreignId('interviewer_id')->constrained('users')->cascadeOnDelete();
            $table->text('interview_content')->nullable();
            $table->text('child_wish')->nullable();
            $table->boolean('check_school')->default(false);
            $table->text('check_school_notes')->nullable();
            $table->boolean('check_home')->default(false);
            $table->text('check_home_notes')->nullable();
            $table->boolean('check_troubles')->default(false);
            $table->text('check_troubles_notes')->nullable();
            $table->text('other_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('classroom_id');
            $table->index('interview_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_interviews');
    }
};
