<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_response_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->date('absence_date');
            $table->foreignId('absence_notification_id')->nullable()->constrained('absence_notifications')->nullOnDelete();
            $table->string('absence_reason', 255)->nullable();
            $table->text('response_content')->nullable()->comment('欠席時対応の内容');
            $table->text('contact_method')->nullable()->comment('連絡方法（電話・メール等）');
            $table->text('contact_content')->nullable()->comment('連絡内容');
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sent')->default(false);
            $table->timestampTz('sent_at')->nullable();
            $table->boolean('guardian_confirmed')->default(false);
            $table->timestampTz('guardian_confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index(['classroom_id', 'absence_date']);
            $table->index('student_id');
            $table->unique(['student_id', 'absence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_response_records');
    }
};
