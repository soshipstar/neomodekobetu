<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->date('record_date');
            $table->string('activity_name', 255)->nullable();
            $table->text('common_activity')->nullable()->comment('共通活動');
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('record_date');
            $table->index('staff_id');
            $table->index('classroom_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_records');
    }
};
