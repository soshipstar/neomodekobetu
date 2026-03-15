<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_holiday_activities', function (Blueprint $table) {
            $table->id();
            $table->date('activity_date');
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['activity_date', 'classroom_id'], 'unique_activity_classroom');
            $table->index('activity_date');
            $table->index('classroom_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holiday_activities');
    }
};
