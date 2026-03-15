<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_capacity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->smallInteger('day_of_week')->comment('0=日曜, 1=月曜, ..., 6=土曜');
            $table->integer('max_capacity')->default(10);
            $table->boolean('is_open')->default(true);
            $table->timestampsTz();

            $table->unique(['classroom_id', 'day_of_week'], 'unique_classroom_day');
        });

        DB::statement("ALTER TABLE classroom_capacity ADD CONSTRAINT classroom_capacity_day_check CHECK (day_of_week >= 0 AND day_of_week <= 6)");
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_capacity');
    }
};
