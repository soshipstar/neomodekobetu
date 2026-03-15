<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();

            $table->string('purpose', 255)->comment('面談目的');
            $table->text('purpose_detail')->nullable()->comment('面談目的詳細');
            $table->text('meeting_notes')->nullable()->comment('面談メモ');
            $table->text('meeting_guidance')->nullable()->comment('面談当日のご案内');
            $table->unsignedBigInteger('related_plan_id')->nullable()->comment('関連する個別支援計画ID');
            $table->unsignedBigInteger('related_monitoring_id')->nullable()->comment('関連するモニタリングID');

            $table->jsonb('candidate_dates')->nullable()->comment('候補日時JSON配列');
            $table->timestampTz('confirmed_date')->nullable();
            $table->string('status', 30)->default('pending')->comment('pending, guardian_counter, staff_counter, confirmed, cancelled');

            $table->timestampsTz();

            $table->index('classroom_id');
            $table->index('student_id');
            $table->index('guardian_id');
            $table->index('staff_id');
            $table->index('status');
            $table->index('confirmed_date');
        });

        DB::statement("ALTER TABLE meeting_requests ADD CONSTRAINT meeting_requests_status_check CHECK (status IN ('pending', 'guardian_counter', 'staff_counter', 'confirmed', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_requests');
    }
};
