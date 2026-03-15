<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_support_plans', function (Blueprint $table) {
            $table->id();
            $table->string('activity_name');
            $table->date('activity_date');
            $table->enum('plan_type', ['normal', 'event', 'other'])->default('normal');
            $table->string('target_grade')->nullable()->comment('comma-delimited: preschool,elementary,junior_high,high_school');
            $table->text('activity_purpose')->nullable();
            $table->text('activity_content')->nullable();
            $table->text('tags')->nullable()->comment('comma-delimited');
            $table->string('day_of_week')->nullable()->comment('comma-delimited');
            $table->text('five_domains_consideration')->nullable();
            $table->text('other_notes')->nullable();
            $table->integer('total_duration')->default(180)->comment('minutes');
            $table->json('activity_schedule')->nullable()->comment('JSON array of schedule items');
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->onDelete('cascade');
            $table->timestamps();

            $table->index(['classroom_id', 'activity_date']);
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_support_plans');
    }
};
